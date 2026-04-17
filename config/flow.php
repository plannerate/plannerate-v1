<?php

use App\Support\Workflow\FlowRoleChecker;

/**
 * Override do pacote laravel-raptor-flow.
 * Usando null para que as tabelas flow_* sejam criadas na conexão default,
 * que o TenantDatabaseManager aponta para o banco do tenant antes de rodar migrations.
 * Em contexto landlord o default aponta para o banco landlord (staging).
 * Em contexto tenant (após middleware) o default aponta para o banco do tenant.
 */

return [
    'connection' => config('raptor.database.landlord_connection_name', 'landlord'),

    // Mantém compatibilidade com URLs esperadas pelo frontend: /flow/executions/{execution}/...
    'route_prefix' => env('FLOW_ROUTE_PREFIX', 'flow'),

    'route_middleware' => ['web', 'auth', 'workflow.enabled'],

    'features' => [
        // Mantém o comportamento atual de sync de participantes por etapa com rollout controlável.
        'sync_config_step_participants' => env('FLOW_SYNC_CONFIG_STEP_PARTICIPANTS', true),
        // Usa usuários sugeridos no metadata do FlowStepTemplate ao configurar etapas.
        'resolve_template_suggested_users' => env('FLOW_RESOLVE_TEMPLATE_SUGGESTED_USERS', true),
        // Fallback para usuários padrão definidos em presets quando a etapa não recebe users explícitos.
        'resolve_preset_default_users' => env('FLOW_RESOLVE_PRESET_DEFAULT_USERS', false),
    ],

    'policy' => [
        /**
         * Callback de verificação de role para o FlowExecutionPolicy.
         * Recebe ($user, $roleId) e retorna bool.
         * Quando definido e a etapa possui default_role_id, bloqueia usuários sem a role
         * antes de qualquer outra verificação (inclusive sem bypass administrativo).
         */
        'check_role' => [FlowRoleChecker::class, 'check'],
    ],

    'events' => [
        'enabled' => true,
        'subscriber' => App\Listeners\Workflow\FlowExecutionDomainSubscriber::class,
    ],
];

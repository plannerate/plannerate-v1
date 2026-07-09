<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\WorkflowTemplate;
use App\Support\Authorization\PermissionName;
use App\Support\Authorization\RbacType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class LandlordKanbanStageRolesSeeder extends Seeder
{
    /**
     * Slugs das etapas cujos perfis são administrativos (contam no limite de
     * usuários do plano). O limite de cada um é definido por plano (plan_items).
     *
     * @var list<string>
     */
    private const ADMINISTRATIVE_SLUGS = [
        'aprovacao-da-area-de-gc',
        'revisao-de-dimensoes',
        'revisao-de-imagens',
        'revisao-periodica',
    ];

    /**
     * Permissões concedidas a TODAS as etapas do kanban.
     *
     * Base mínima para o participante enxergar o kanban, abrir o planograma
     * (leitura) e mover o card pela sua etapa no fluxo.
     *
     * @var list<string>
     */
    private const COMMON_PERMISSIONS = [
        PermissionName::TENANT_DASHBOARD_VIEW,
        PermissionName::TENANT_KANBAN_VIEW_ANY,
        PermissionName::TENANT_KANBAN_EXECUTIONS_MOVE,
        PermissionName::TENANT_PLANOGRAMS_VIEW_ANY,
        PermissionName::TENANT_PLANOGRAMS_VIEW,
        PermissionName::TENANT_GONDOLAS_VIEW_ANY,
        PermissionName::TENANT_GONDOLAS_VIEW,
        PermissionName::TENANT_EDITOR_PLANOGRAMS_VIEW_ANY,
        PermissionName::TENANT_PLANOGRAM_TEMPLATES_VIEW_ANY,
        PermissionName::TENANT_PLANOGRAM_TEMPLATES_VIEW,
    ];

    /**
     * Cria um perfil (Role) do tipo tenant para cada etapa padrão do workflow kanban
     * e já sincroniza as permissões coerentes com a função de cada etapa.
     *
     * Os perfis são criados apenas no landlord (tenant_id = null), com os mesmos nomes
     * das etapas de getDefaultTemplates(). Nenhum perfil é atribuído a usuários — apenas
     * passam a existir, com suas permissões, para uso posterior nos tenants.
     */
    public function run(): void
    {
        // Perfis com tenant_id = null vivem no escopo landlord; zera o time atual
        // do spatie/permission para não vazar o contexto de um tenant.
        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId(null);

        try {
            $permissionsBySlug = $this->permissionsBySlug();

            foreach (WorkflowTemplate::getDefaultTemplates() as $template) {
                $name = $template['name'];
                $slug = Str::slug($name);

                $role = Role::query()->firstOrCreate(
                    [
                        'system_name' => 'kanban-'.$slug,
                        'guard_name' => 'web',
                        'type' => RbacType::TENANT,
                        'tenant_id' => null,
                    ],
                    [
                        'name' => $name,
                    ],
                );

                // Marca (idempotente) os perfis administrativos que contam no
                // limite de usuários do plano.
                $role->forceFill([
                    'is_administrative' => in_array($slug, self::ADMINISTRATIVE_SLUGS, true),
                ])->save();

                $extra = $permissionsBySlug[$slug] ?? [];
                $role->syncPermissions([...self::COMMON_PERMISSIONS, ...$extra]);
            }
        } finally {
            setPermissionsTeamId($previousTeamId);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /**
     * Permissões extras por etapa, além da base comum.
     *
     * A chave é o slug do nome da etapa (mesmo usado no system_name, sem o prefixo
     * "kanban-"). Etapas de criação/revisão recebem permissões de edição; etapas de
     * aprovação/execução/acompanhamento recebem apenas leituras do que precisam avaliar.
     *
     * @return array<string, list<string>>
     */
    private function permissionsBySlug(): array
    {
        return [
            // Criação inicial: monta planograma e gôndolas, autogera e inicia o fluxo.
            // É o "especialista" que também cria/edita os templates de planograma.
            'criacao-do-planograma' => [
                PermissionName::TENANT_PLANOGRAMS_CREATE,
                PermissionName::TENANT_PLANOGRAMS_UPDATE,
                PermissionName::TENANT_PLANOGRAM_TEMPLATES_CREATE,
                PermissionName::TENANT_PLANOGRAM_TEMPLATES_UPDATE,
                PermissionName::TENANT_PLANOGRAM_TEMPLATES_DELETE,
                PermissionName::TENANT_GONDOLAS_CREATE,
                PermissionName::TENANT_GONDOLAS_UPDATE,
                PermissionName::TENANT_GONDOLAS_AUTOGENERATE,
                PermissionName::TENANT_PRODUCTS_VIEW_ANY,
                PermissionName::TENANT_PRODUCTS_VIEW,
                PermissionName::TENANT_KANBAN_EXECUTIONS_START,
            ],

            // Revisão de imagens: ajusta o layout e as imagens dos produtos.
            'revisao-de-imagens' => [
                PermissionName::TENANT_PLANOGRAMS_UPDATE,
                PermissionName::TENANT_GONDOLAS_UPDATE,
                PermissionName::TENANT_PRODUCTS_VIEW_ANY,
                PermissionName::TENANT_PRODUCTS_VIEW,
                PermissionName::TENANT_PRODUCTS_UPDATE,
            ],

            // Revisão de dimensões: confere e ajusta medidas/dimensões.
            'revisao-de-dimensoes' => [
                PermissionName::TENANT_PLANOGRAMS_UPDATE,
                PermissionName::TENANT_GONDOLAS_UPDATE,
                PermissionName::TENANT_DIMENSIONS_VIEW_ANY,
                PermissionName::TENANT_DIMENSIONS_UPDATE,
            ],

            // Aprovação comercial: avalia margem/objetivos → consulta vendas (leitura).
            'aprovacao-comercial' => [
                PermissionName::TENANT_SALES_VIEW_ANY,
                PermissionName::TENANT_SALES_VIEW,
            ],

            // Aprovação da área de GC: valida alinhamento de categoria (leitura).
            'aprovacao-da-area-de-gc' => [
                PermissionName::TENANT_CATEGORIES_VIEW_ANY,
                PermissionName::TENANT_CATEGORIES_VIEW,
            ],

            // Execução na loja: implementação física → consulta lojas (leitura).
            'execucao-loja' => [
                PermissionName::TENANT_STORES_VIEW_ANY,
                PermissionName::TENANT_STORES_VIEW,
            ],

            // Revisão periódica: acompanha desempenho (vendas) e inicia novo ciclo.
            'revisao-periodica' => [
                PermissionName::TENANT_SALES_VIEW_ANY,
                PermissionName::TENANT_SALES_VIEW,
                PermissionName::TENANT_KANBAN_EXECUTIONS_START,
            ],
        ];
    }
}

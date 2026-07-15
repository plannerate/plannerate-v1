<?php

return [
    'templates' => [
        'title' => 'Templates de workflow',
        'description' => 'Gerencie as etapas do workflow kanban deste tenant.',
        'navigation' => 'Templates',
        'create_template' => 'Nova etapa',
        'edit_template' => 'Editar etapa',
        'no_template' => 'Nenhuma etapa encontrada para este tenant.',
        'empty_state_description' => 'Crie a primeira etapa do workflow para este tenant.',
        'seed_default_templates' => 'Criar templates padrão',
        'search' => 'Buscar por nome...',
        'statuses' => [
            'all' => 'Todos',
            'draft' => 'Rascunho',
            'published' => 'Publicado',
        ],
        'fields' => [
            'name' => 'Nome',
            'slug' => 'Slug',
            'description' => 'Descrição',
            'suggested_order' => 'Ordem sugerida',
            'estimated_duration_days' => 'Duração estimada (dias)',
            'color' => 'Cor',
            'icon' => 'Ícone',
            'is_required_by_default' => 'Obrigatória por padrão',
            'status' => 'Status',
            'next_step' => 'Próxima etapa',
            'previous_step' => 'Etapa anterior',
            'suggested_users' => 'Usuários sugeridos',
        ],
        'messages' => [
            'created' => 'Etapa criada com sucesso.',
            'updated' => 'Etapa atualizada com sucesso.',
            'deleted' => 'Etapa removida com sucesso.',
            'force_deleted' => 'Etapa excluída permanentemente com sucesso.',
            'restored' => 'Etapa restaurada com sucesso.',
            'seeded' => 'Templates padrão criados com sucesso.',
        ],
    ],
];

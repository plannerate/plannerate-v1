<?php

return [
    'navigation' => 'Planos',
    'title' => 'Planos',
    'description' => 'Gerencie os planos disponíveis para os tenants.',
    'actions' => [
        'new' => 'Novo plano',
        'edit' => 'Editar plano',
    ],
    'fields' => [
        'name' => 'Nome',
        'description' => 'Descrição',
        'price_cents' => 'Preço',
        'user_limit' => 'Limite de usuários',
        'is_active' => 'Ativo',
        'tenants_count' => 'Tenants',
    ],
    'messages' => [
        'created' => 'Plano criado com sucesso.',
        'updated' => 'Plano atualizado com sucesso.',
        'deleted' => 'Plano removido com sucesso.',
        'in_use' => 'Não é possível excluir um plano que possui tenants vinculados.',
    ],
];

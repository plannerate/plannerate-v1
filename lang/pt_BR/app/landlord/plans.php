<?php

return [
    'navigation' => 'Planos',
    'title' => 'Planos',
    'description' => 'Gerencie os planos disponiveis para os tenants.',
    'actions' => [
        'new' => 'Novo plano',
        'edit' => 'Editar plano',
    ],
    'fields' => [
        'name' => 'Nome',
        'description' => 'Descricao',
        'price_cents' => 'Preco',
        'user_limit' => 'Limite de usuarios',
        'is_active' => 'Ativo',
        'tenants_count' => 'Tenants',
    ],
    'messages' => [
        'created' => 'Plano criado com sucesso.',
        'updated' => 'Plano atualizado com sucesso.',
        'deleted' => 'Plano removido com sucesso.',
        'in_use' => 'Nao e possivel excluir um plano que possui tenants vinculados.',
    ],
];

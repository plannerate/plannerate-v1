<?php

return [
    'navigation' => 'Planos',
    'title' => 'Planos',
    'description' => 'Gerencie os planos disponíveis para os clientes.',
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
        'clients_count' => 'Clientes associados',
    ],
    'role_limits' => [
        'title' => 'Limites por perfil administrativo',
        'hint' => 'Quantidade máxima de usuários por perfil neste plano. Em branco = ilimitado.',
        'unlimited_placeholder' => 'Em branco = ilimitado',
        'item_label' => 'Limite de usuários: :role',
    ],
    'messages' => [
        'created' => 'Plano criado com sucesso.',
        'updated' => 'Plano atualizado com sucesso.',
        'deleted' => 'Plano removido com sucesso.',
        'in_use' => 'Não é possível excluir um plano que possui clientes vinculados.',
    ],
];

<?php

return [
    'navigation' => 'Modulos',
    'title' => 'Modulos',
    'description' => 'Gerencie os modulos disponiveis para os tenants.',
    'actions' => [
        'new' => 'Novo modulo',
        'edit' => 'Editar modulo',
    ],
    'fields' => [
        'name' => 'Nome',
        'description' => 'Descricao',
        'is_active' => 'Ativo',
        'tenants_count' => 'Tenants',
    ],
    'messages' => [
        'created' => 'Modulo criado com sucesso.',
        'updated' => 'Modulo atualizado com sucesso.',
        'deleted' => 'Modulo removido com sucesso.',
        'in_use' => 'Nao e possivel excluir um modulo que possui tenants vinculados.',
    ],
];

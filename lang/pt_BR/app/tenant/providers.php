<?php

return [
    'navigation' => 'Fornecedores',
    'title' => 'Fornecedores',
    'description' => 'Gerencie os fornecedores.',
    'actions' => [
        'new' => 'Novo fornecedor',
        'edit' => 'Editar fornecedor',
    ],
    'fields' => [
        'name' => 'Nome',
        'code' => 'Código',
        'email' => 'E-mail',
        'phone' => 'Telefone',
        'cnpj' => 'CNPJ',
        'is_default' => 'Padrão',
        'description' => 'Descrição',
    ],
    'messages' => [
        'created' => 'Fornecedor criado com sucesso.',
        'updated' => 'Fornecedor atualizado com sucesso.',
        'deleted' => 'Fornecedor removido com sucesso.',
        'force_deleted' => 'Fornecedor excluído definitivamente.',
        'restored' => 'Fornecedor restaurado com sucesso.',
    ],
];

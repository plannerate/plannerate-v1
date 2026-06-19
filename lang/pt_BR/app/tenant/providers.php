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
        'code' => 'Codigo',
        'email' => 'E-mail',
        'phone' => 'Telefone',
        'cnpj' => 'CNPJ',
        'is_default' => 'Padrao',
        'description' => 'Descricao',
    ],
    'messages' => [
        'created' => 'Fornecedor criado com sucesso.',
        'updated' => 'Fornecedor atualizado com sucesso.',
        'deleted' => 'Fornecedor removido com sucesso.',
    ],
];

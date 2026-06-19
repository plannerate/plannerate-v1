<?php

return [
    'navigation' => 'Permissoes',
    'title' => 'Permissoes',
    'description' => 'Gerencie o catalogo global de permissoes.',
    'actions' => [
        'new' => 'Nova permissao',
        'edit' => 'Editar permissao',
    ],
    'fields' => [
        'name' => 'Nome',
        'type' => 'Tipo',
    ],
    'messages' => [
        'created' => 'Permissao criada com sucesso.',
        'updated' => 'Permissao atualizada com sucesso.',
        'deleted' => 'Permissao removida com sucesso.',
        'protected' => 'Esta permissao e protegida e nao pode ser alterada.',
    ],
];

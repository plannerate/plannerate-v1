<?php

return [
    'navigation' => 'Links úteis',
    'title' => 'Links úteis',
    'description' => 'Gerencie links que podem ser exibidos no dashboard dos tenants.',
    'actions' => [
        'new' => 'Novo link',
        'edit' => 'Editar link',
    ],
    'fields' => [
        'name' => 'Nome',
        'url' => 'Link',
        'logo' => 'Logo',
        'description' => 'Descrição',
        'show_on_tenant_dashboard' => 'Mostrar para tenants',
    ],
    'messages' => [
        'created' => 'Link útil criado com sucesso.',
        'updated' => 'Link útil atualizado com sucesso.',
        'deleted' => 'Link útil removido com sucesso.',
        'force_deleted' => 'Link útil excluído permanentemente com sucesso.',
        'restored' => 'Link útil restaurado com sucesso.',
    ],
];

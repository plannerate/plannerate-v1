<?php

return [
    'navigation' => 'Links uteis',
    'title' => 'Links uteis',
    'description' => 'Gerencie links que podem ser exibidos no dashboard dos tenants.',
    'actions' => [
        'new' => 'Novo link',
        'edit' => 'Editar link',
    ],
    'fields' => [
        'name' => 'Nome',
        'url' => 'Link',
        'logo' => 'Logo',
        'description' => 'Descricao',
        'show_on_tenant_dashboard' => 'Mostrar para tenants',
    ],
    'messages' => [
        'created' => 'Link util criado com sucesso.',
        'updated' => 'Link util atualizado com sucesso.',
        'deleted' => 'Link util removido com sucesso.',
    ],
];

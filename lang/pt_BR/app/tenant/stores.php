<?php

return [
    'navigation' => 'Lojas',
    'title' => 'Lojas',
    'description' => 'Gerencie as lojas.',
    'actions' => [
        'new' => 'Nova loja',
        'edit' => 'Editar loja',
    ],
    'tabs' => [
        'identificacao' => 'Identificação',
        'endereco' => 'Endereço',
        'mapa_da_loja' => 'Mapa Da Loja',
    ],
    'fields' => [
        'name' => 'Nome',
        'document' => 'CNPJ',
        'code' => 'Código',
        'phone' => 'Telefone',
        'email' => 'E-mail',
        'status' => 'Status',
        'description' => 'Descrição',
        'map' => 'Mapa Da Loja',
    ],
    'status_draft' => 'Rascunho',
    'status_published' => 'Publicado',
    'hints' => [
        'map' => 'Envie a planta da loja e marque as áreas como gôndolas, ilhas, entradas e checkouts.',
    ],
    'messages' => [
        'created' => 'Loja criada com sucesso.',
        'updated' => 'Loja atualizada com sucesso.',
        'deleted' => 'Loja removida com sucesso.',
    ],
];

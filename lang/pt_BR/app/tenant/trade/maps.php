<?php

return [
    'navigation' => 'Mapas',
    'title' => 'Plantas de loja',
    'description' => 'Gerencie as plantas das lojas e posicione os espaços.',
    'actions' => [
        'new' => 'Novo mapa',
        'edit' => 'Editar mapa',
        'configure' => 'Configurar espaços',
        'save_layout' => 'Salvar layout',
        'add_space' => 'Adicionar ao mapa',
        'remove_space' => 'Remover do mapa',
    ],
    'fields' => [
        'name' => 'Nome',
        'store' => 'Loja',
        'description' => 'Descrição',
        'image' => 'Planta (imagem)',
        'status' => 'Status',
        'spaces_count' => 'Espaços',
    ],
    'editor' => [
        'available_spaces' => 'Espaços disponíveis',
        'placed_spaces' => 'Espaços no mapa',
        'no_available' => 'Nenhum espaço disponível para esta loja.',
        'no_placed' => 'Nenhum espaço posicionado ainda.',
        'zoom_in' => 'Aproximar',
        'zoom_out' => 'Afastar',
        'reset_view' => 'Restaurar visão',
        'rotate' => 'Girar',
        'unsaved' => 'Há alterações não salvas.',
        'hint' => 'Arraste para mover, use as alças para redimensionar e girar.',
    ],
    'placeholders' => [
        'select_store' => 'Selecione a loja',
    ],
    'messages' => [
        'created' => 'Mapa criado com sucesso.',
        'updated' => 'Mapa atualizado com sucesso.',
        'deleted' => 'Mapa removido com sucesso.',
        'force_deleted' => 'Mapa excluído definitivamente.',
        'restored' => 'Mapa restaurado com sucesso.',
        'layout_saved' => 'Layout do mapa salvo com sucesso.',
    ],
];

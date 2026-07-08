<?php

return [
    'title' => 'Mercadológico',
    'description' => 'Gerencie a árvore de categorias deste tenant. Arraste para reorganizar e mova produtos entre categorias.',
    'navigation' => 'Mercadológico',

    'tree' => [
        'roots_title' => 'Categorias',
        'empty' => 'Nenhuma categoria cadastrada para este tenant.',
        'loading' => 'Carregando…',
        'children_count' => ':count subcategoria|:count subcategorias',
        'products_count' => ':count produto|:count produtos',
        'view_products' => 'Ver produtos',
        'placeholder_badge' => 'Placeholder',
        'expand' => 'Expandir',
        'collapse' => 'Recolher',
        'move_to_root' => 'Solte aqui para mover para a raiz',
    ],

    'move' => [
        'confirm_title' => 'Mover categoria',
        'confirm_message' => 'Mover ":name" e todo o seu conteúdo para ":target"?',
        'confirm_message_root' => 'Mover ":name" para a raiz do mercadológico?',
        'confirm_impact' => ':descendants subcategorias e :products produtos serão movidos junto.',
        'confirm' => 'Mover',
        'cancel' => 'Cancelar',
    ],

    'products' => [
        'modal_title' => 'Produtos de :name',
        'search' => 'Buscar por nome, EAN ou código ERP…',
        'empty' => 'Nenhum produto nesta categoria.',
        'selected' => ':count selecionado(s)',
        'move_to' => 'Mover para…',
        'move_selected' => 'Mover selecionados',
        'select_target' => 'Selecione a categoria de destino',
        'open_target_hint' => 'Abra outra categoria como destino',
        'load_more' => 'Carregar mais',
        'close' => 'Fechar',
        'drag_hint' => 'Arraste os produtos para outra janela para movê-los',
        'not_leaf_warning' => 'A categoria de destino não é uma folha da árvore.',
        'columns' => [
            'name' => 'Produto',
            'ean' => 'EAN',
            'codigo_erp' => 'Código ERP',
        ],
    ],

    'actions' => [
        'new_root' => 'Nova categoria',
        'add_child' => 'Adicionar subcategoria',
        'rename' => 'Editar',
        'delete' => 'Excluir',
        'menu' => 'Ações',
        'undo' => 'Desfazer',
        'redo' => 'Refazer',
    ],

    'form' => [
        'create_root_title' => 'Nova categoria',
        'create_child_title' => 'Nova subcategoria de :parent',
        'edit_title' => 'Editar categoria',
        'name' => 'Nome',
        'name_placeholder' => 'Nome da categoria',
        'codigo' => 'Código ERP',
        'codigo_placeholder' => 'Opcional',
        'status' => 'Status',
        'status_draft' => 'Rascunho',
        'status_published' => 'Publicado',
        'status_importer' => 'Importado',
        'save' => 'Salvar',
        'cancel' => 'Cancelar',
    ],

    'delete' => [
        'confirm_title' => 'Excluir categoria',
        'confirm_message' => 'Excluir a categoria ":name"?',
        'confirm' => 'Excluir',
        'cancel' => 'Cancelar',
    ],

    'messages' => [
        'moved' => 'Categoria movida com sucesso.',
        'products_moved' => 'Nenhum produto movido.|:count produto movido com sucesso.|:count produtos movidos com sucesso.',
        'created' => 'Categoria criada com sucesso.',
        'updated' => 'Categoria atualizada com sucesso.',
        'deleted' => 'Categoria excluída com sucesso.',
        'restored' => 'Categoria restaurada.',
        'undone' => 'Ação desfeita.',
        'redone' => 'Ação refeita.',
        'action_failed' => 'Não foi possível concluir a ação.',
    ],

    'errors' => [
        'move_into_self' => 'Não é possível mover uma categoria para dentro dela mesma.',
        'move_into_descendant' => 'Não é possível mover uma categoria para dentro de um de seus descendentes.',
        'max_depth_exceeded' => 'A movimentação excede a profundidade máxima do mercadológico (:max níveis).',
        'delete_has_children' => 'A categoria possui subcategorias. Mova ou exclua as subcategorias antes.',
        'delete_has_products' => 'A categoria possui produtos vinculados. Mova os produtos antes de excluir.',
    ],
];

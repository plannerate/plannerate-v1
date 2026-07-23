<?php

return [
    'navigation' => 'Tipos de espaço',
    'title' => 'Tipos de espaço',
    'description' => 'Gerencie os tipos de espaço promocional e seus prefixos.',
    'actions' => [
        'new' => 'Novo tipo',
        'edit' => 'Editar tipo',
        'import' => 'Importar da biblioteca',
        'activate' => 'Ativar',
        'deactivate' => 'Desativar',
    ],
    'fields' => [
        'name' => 'Nome',
        'slug' => 'Slug',
        'description' => 'Descrição',
        'suggested_price' => 'Valor sugerido',
        'billing_mode' => 'Modo de cobrança',
        'suggested_width' => 'Largura sugerida (cm)',
        'suggested_height' => 'Altura sugerida (cm)',
        'suggested_depth' => 'Profundidade sugerida (cm)',
        'is_active' => 'Ativo',
        'sort_order' => 'Ordem',
        'image' => 'Imagem de exemplo',
        'prefixes' => 'Prefixos',
        'default_prefix' => 'Prefixo padrão',
    ],
    'stats' => [
        'total' => 'Total',
        'active' => 'Ativos',
        'inactive' => 'Inativos',
    ],
    'labels' => [
        'negotiable' => 'Negociável',
        'not_defined' => 'Não definido',
    ],
    'library' => [
        'title' => 'Importar da biblioteca',
        'description' => 'Selecione os tipos de espaço do catálogo para adicionar ao seu tenant.',
        'already_added' => 'Já adicionado',
        'import_selected' => 'Importar selecionados',
        'empty' => 'Nenhum item disponível na biblioteca.',
    ],
    'messages' => [
        'created' => 'Tipo de espaço criado com sucesso.',
        'updated' => 'Tipo de espaço atualizado com sucesso.',
        'deleted' => 'Tipo de espaço removido com sucesso.',
        'force_deleted' => 'Tipo de espaço excluído definitivamente.',
        'restored' => 'Tipo de espaço restaurado com sucesso.',
        'imported' => ':count tipo(s) importado(s) da biblioteca.',
    ],
];

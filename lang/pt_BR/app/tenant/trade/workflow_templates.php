<?php

return [
    'navigation' => 'Templates de workflow',
    'title' => 'Templates de etapas',
    'description' => 'Modelos reutilizáveis de etapas de workflow, agrupados por categoria e aplicados às atividades.',

    'actions' => [
        'new' => 'Novo template',
        'edit' => 'Editar template',
        'duplicate' => 'Duplicar',
        'toggle' => 'Ativar/Desativar',
        'apply' => 'Aplicar',
    ],

    'fields' => [
        'name' => 'Nome',
        'category' => 'Categoria',
        'color' => 'Cor',
        'duration' => 'Duração (dias)',
        'suggested_order' => 'Ordem sugerida',
        'description' => 'Descrição',
        'instructions' => 'Instruções',
        'is_required_by_default' => 'Obrigatória por padrão',
        'is_active' => 'Ativa',
    ],

    'placeholders' => [
        'all_categories' => 'Todas as categorias',
    ],

    'messages' => [
        'created' => 'Template criado com sucesso.',
        'updated' => 'Template atualizado com sucesso.',
        'deleted' => 'Template excluído com sucesso.',
        'force_deleted' => 'Template excluído definitivamente.',
        'restored' => 'Template restaurado com sucesso.',
        'duplicated' => 'Template duplicado. Revise antes de ativar.',
        'toggled' => 'Situação do template atualizada.',
        'in_use' => 'Não é possível excluir um template com etapas ativas em execução.',
        'category_empty' => 'Nenhum template ativo na categoria selecionada.',
    ],
];

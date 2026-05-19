<?php

return [
    'name' => 'Modelos de Planograma',
    'description' => 'Modelos de planograma pré-construídos para ajudar a criar planogramas rapidamente.',

    // Template types
    'templates' => [
        'gondola' => [
            'name' => 'Gôndola',
            'description' => 'Um modelo de gôndola tradicional com prateleiras ajustáveis.',
        ],
        'endcap' => [
            'name' => 'Endcap',
            'description' => 'Um modelo de endcap para destacar produtos em áreas de alto tráfego.',
        ],
        'island' => [
            'name' => 'Ilha',
            'description' => 'Um modelo de ilha para exibir produtos em um layout aberto.',
        ],
    ],

    // Form fields translations (for PlanogramTemplateFormFields.vue)
    'fields' => [
        'code' => 'Código',
        'name' => 'Nome',
        'department' => 'Departamento',
        'description' => 'Descrição',
        'status' => 'Status',
    ],

    'status' => [
        'active' => 'Ativo',
        'inactive' => 'Inativo',
    ],

    // Wizard steps
    'wizard' => [
        'step1_label' => 'Dados básicos',
        'step1_description' => 'Código, nome e departamento',
        'step2_label' => 'Slots',
        'step2_description' => 'Grade de gôndola',
        'step3_label' => 'Produtos',
        'step3_description' => 'Mix do template',
        'configure_slots_button' => 'Configurar Slots →',
        'back_to_basics_button' => 'Voltar — Dados básicos',
        'back_to_slots_button' => 'Voltar — Slots',
        'finish_button' => 'Finalizar e sair',
        'next_slots_button' => 'Avançar — Slots →',
        'next_products_button' => 'Avançar — Produtos →',
    ],

    // Grid labels (GondolaGrid.vue)
    'grid' => [
        'module_label' => 'Módulo #',
        'shelf_label' => 'Prat #',
        'add_button' => 'Adicionar',
    ],

    // Slot editor modal (SlotEditorModal.vue)
    'slot_editor' => [
        'title' => 'Configurar slot',
        'module' => 'Módulo #',
        'shelf' => 'Prat #',
        'grouping_label' => 'Agrupamento de exposição',
        'grouping_required' => '*',
        'grouping_hint' => 'Chave que vincula o slot aos produtos',
        'grouping_example' => 'Ex: LAVA ROUPAS PÓ PACOTE',
        'category_label' => 'Categoria',
        'category_example' => 'Ex: LAVA ROUPAS',
        'subcategory_label' => 'Subcategoria',
        'subcategory_example' => 'Ex: PÓ PACOTE',
        'min_facings_label' => 'Frentes mínimas',
        'priority_label' => 'Prioridade',
        'priority_hint' => '1 = mais importante',
        'price_order_label' => 'Ordem por preço',
        'price_order_options' => [
            'none' => 'Sem ordenação',
            'asc' => 'Mais barato primeiro',
            'desc' => 'Mais caro primeiro',
        ],
        'size_order_label' => 'Ordem por tamanho',
        'size_order_options' => [
            'none' => 'Sem ordenação',
            'asc' => 'Menor primeiro',
            'desc' => 'Maior primeiro',
        ],
        'brand_exposure_label' => 'Exposição por marca',
        'flavor_exposure_label' => 'Exposição por fragrância',
        'exposure_options' => [
            'vertical' => 'Vertical',
            'horizontal' => 'Horizontal',
            'mixed' => 'Misto',
        ],
        'space_fallback_label' => 'Se faltar espaço',
        'space_fallback_options' => [
            'reduce_c' => 'Remover curva C primeiro',
            'reduce_facings' => 'Reduzir frentes para 1',
            'skip' => 'Deixar incompleto',
        ],
        'target_stock_label' => 'Usar estoque alvo',
        'cancel_button' => 'Cancelar',
        'save_button' => 'Salvar slot',
    ],

    // Slot card (SlotCard.vue)
    'slot_card' => [
        'price_order' => [
            'asc' => '↑ preço',
            'desc' => '↓ preço',
        ],
        'min_facings_label' => 'min',
        'facings_abbr' => 'f',
        'priority_prefix' => 'p',
    ],

    // Product search panel (ProductSearchPanel.vue)
    'product_search' => [
        'search_placeholder' => 'Buscar por EAN, nome ou marca...',
        'grouping_placeholder' => 'Agrupamento de destino',
        'no_groupings_hint' => 'Configure os slots (etapa 2) primeiro',
        'searching' => 'Buscando...',
        'no_results' => 'Nenhum produto encontrado',
        'search_hint' => 'Digite para buscar produtos',
        'add_button' => 'Adicionar',
        'product_singular' => 'produto',
        'product_plural' => 'produtos',
        'selected' => 'selecionados',
        'grouping_none' => 'Nenhum agrupamento',
    ],

    // Product table (TemplateProductTable.vue)
    'product_table' => [
        'count_singular' => 'produto no template',
        'count_plural' => 'produtos no template',
        'download_button' => 'Baixar modelo',
        'import_button' => 'Importar planilha',
        'columns' => [
            'ean' => 'EAN',
            'description' => 'Descrição',
            'brand' => 'Marca',
            'grouping' => 'Agrupamento',
            'package_type' => 'Embalagem',
            'package_content' => 'Conteúdo',
            'dimensions' => 'Dimensões',
            'status' => 'Status',
        ],
        'empty_message' => 'Nenhum produto adicionado ainda',
        'empty_value' => '—',
        'status_in_mix' => 'No mix',
        'status_out_of_mix' => 'Fora do mix',
    ],

    // Messages
    'messages' => [
        'slot_saved' => 'Slot salvo com sucesso',
        'slot_removed' => 'Slot removido',
        'product_added' => 'Produtos adicionados',
        'product_removed' => 'Produto removido',
        'import_success' => 'Planilha importada com sucesso',
    ],
];

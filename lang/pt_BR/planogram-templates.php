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
        'gondola_category' => 'Categoria da gôndola',
        'gondola_category_hint' => 'Define a categoria principal que será trabalhada nos slots desta gôndola.',
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
        'step3_slots_label' => 'Revisar slots',
        'step3_slots_description' => 'Selecione um slot e veja os produtos relacionados',
        'step3_review_label' => 'Revisão',
        'step3_review_description' => 'Visualize slots e produtos relacionados',
        'configure_slots_button' => 'Configurar Slots →',
        'back_to_basics_button' => 'Voltar — Dados básicos',
        'back_to_slots_button' => 'Voltar — Slots',
        'finish_button' => 'Finalizar e sair',
        'next_slots_button' => 'Avançar — Slots →',
        'next_products_button' => 'Avançar — Produtos →',
        'next_to_review_button' => 'Próximo — Revisão de slots',
        'step_label' => 'Etapa',
    ],

    // Grid labels (GondolaGrid.vue)
    'grid' => [
        'module_label' => 'Módulo -',
        'shelf_label' => 'Prat -',
        'add_button' => 'Adicionar',
    ],

    // Module selector buttons (ModuleSelectorButtons.vue)
    'module_selector' => [
        'add_tooltip' => 'Adicionar configuração para novo número de módulos',
        'module_singular' => 'módulo',
        'module_plural' => 'módulos',
    ],

    // Module defaults modal (ModuleDefaultsModal.vue)
    'module_defaults' => [
        'title' => 'Configuração padrão — Módulo',
        'category_label' => 'Categoria padrão',
        'category_hint' => 'Novos slots deste módulo já abrem com essa categoria selecionada.',
        'max_facings_label' => 'Frentes máximas',
        'max_facings_hint' => 'Teto de expansão por SKU',
        'save_button' => 'Salvar padrão',
    ],

    // Subtemplate global settings card (SubtemplateSettingsCard.vue)
    'subtemplate_settings' => [
        'title' => 'Configurações do subtemplate',
        'hint' => 'Valem para todos os :count módulo(s) deste subtemplate — não são configuráveis por módulo.',
        'save_button' => 'Salvar configurações',
        'saved' => 'Configurações do subtemplate salvas',
    ],

    // Facing expansion options (shared: ModuleDefaultsModal + SlotEditorFields)
    'facing_expansion' => [
        'label' => 'Expansão de frentes',
        'hint_module' => 'Como usar espaço livre acima do mínimo',
        'hint_slot' => 'Como usar espaço livre',
        'none' => 'Não expandir',
        'score' => 'Por score ABC / vendas',
        'current_stock' => 'Por estoque atual',
        'target_stock' => 'Por déficit de estoque',
        'equal' => 'Distribuição igual',
    ],

    // Flow direction section (ModuleDefaultsModal.vue)
    'flow_direction' => [
        'title' => 'Sentido de leitura',
        'description' => 'Define a direção do fluxo do cliente na frente da gôndola. Afeta a posição física dos produtos: "preço crescente no fluxo" coloca o produto mais barato no início do fluxo.',
        'left_to_right' => 'Esquerda → Direita',
        'left_to_right_default' => '(padrão)',
        'right_to_left' => 'Direita → Esquerda',
    ],

    // Layout orientation section (ModuleDefaultsModal.vue)
    'layout_orientation' => [
        'title' => 'Disposição dos produtos',
        'description' => 'Horizontal: cada prateleira distribui seus produtos de forma independente. Vertical: quando a categoria ocupa várias prateleiras do módulo, cada marca forma uma coluna alinhada atravessando as prateleiras (blocagem por marca).',
        'horizontal' => 'Horizontal',
        'horizontal_default' => '(padrão)',
        'vertical' => 'Vertical (blocagem por marca)',
        'regenerate_hint' => 'Alterar a disposição exige regerar o planograma para reorganizar as colunas.',
    ],

    // Zone priority section (ModuleDefaultsModal.vue)
    'zone_priority' => [
        'title' => 'Priorização por zona térmica',
        'description' => 'Define qual critério de ordenação é aplicado aos produtos em prateleiras quentes (olhos / mãos) e frias (alta / chão). Não filtra produtos — apenas reordena dentro do slot.',
        'hot_zone_label' => 'Zona quente (olhos + mãos)',
        'hot_zone_hint' => 'Eye + Hand: área nobre da gôndola',
        'cold_zone_label' => 'Zona fria (alto + chão)',
        'cold_zone_hint' => 'High + Low: área de menor visibilidade',
        'no_criteria' => 'Sem critério (padrão)',
        'hot' => [
            'maior_margem' => 'Maior margem',
            'maior_giro' => 'Maior giro (vendas)',
            'maior_valor_vendido' => 'Maior valor vendido',
            'curva_a' => 'Curva A primeiro',
        ],
        'cold' => [
            'menor_margem' => 'Menor margem',
            'complementar_fria' => 'Complementar / sazonais',
            'maior_volume' => 'Maior volume físico',
            'menor_prioridade' => 'Menor prioridade geral',
        ],
    ],

    // Slot editor modal (SlotEditorModal.vue)
    'slot_editor' => [
        'title' => 'Configurar slot',
        'module' => 'Módulo - ',
        'shelf' => 'Prat - ',
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
        'priority_hint' => 'Informativo — não altera o posicionamento',
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
            'remove_dog' => 'Remover retardatários primeiro',
        ],
        'target_stock_label' => 'Usar estoque alvo',
        'cancel_button' => 'Cancelar',
        'save_button' => 'Salvar slot',
    ],

    // Slot editor sections (SlotEditorFields.vue)
    'slot_editor_fields' => [
        'category' => [
            'title' => 'Categoria',
            'description' => 'Define quais produtos entram neste slot. Selecionar uma categoria pai inclui automaticamente todos os produtos das subcategorias filhas.',
        ],
        'role' => [
            'label' => 'Papel da categoria',
            'description' => 'Orienta a posição macro e a estratégia do bloco na gôndola. "Herdar" usa o papel configurado na própria categoria.',
        ],
        'facings' => [
            'title' => 'Dimensionamento de frentes',
            'description' => 'Controla quantas frentes cada SKU ocupa (mínimo e teto) e como o espaço livre é redistribuído quando a gôndola tem menos produtos do que o previsto.',
            'max_label' => 'Frentes máx.',
            'max_hint' => 'Teto por SKU',
        ],
        'ordering' => [
            'title' => 'Ordenação e exposição visual',
            'description' => 'Determina a sequência de exibição dos produtos dentro do slot e como grupos de marca ou fragrância se organizam fisicamente na prateleira.',
        ],
        'share_limits' => [
            'title' => 'Limites de participação',
            'description' => 'Tetos percentuais que evitam que um único SKU, marca ou subcategoria domine o slot durante a expansão de frentes. Deixe em branco para sem limite.',
            'max_sku_label' => 'Máx. % por SKU',
            'max_sku_hint' => '% do slot por produto',
            'max_brand_label' => 'Máx. % por marca',
            'max_brand_hint' => '% do slot por marca',
            'max_subcat_label' => 'Máx. % subcat.',
            'max_subcat_hint' => '% por subcategoria',
        ],
    ],

    // Category role options (SlotEditorFields.vue)
    'role_options' => [
        'inherit' => 'Herdar da categoria',
        'destino' => 'Destino — gera tráfego, área nobre',
        'rotina' => 'Rotina — exposição equilibrada, centro',
        'conveniencia' => 'Conveniência — leitura simples, acesso fácil',
        'impulso' => 'Impulso — área quente, maior visibilidade',
        'sazonal' => 'Sazonal — destaque temporário',
        'complementar' => 'Complementar — zona fria, área de associação',
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
        'no_category' => 'Sem categoria',
        'role_configured_here' => 'Papel configurado neste slot',
        'role_from_category' => 'Papel da categoria',
        'rejected_singular' => 'rejeitado',
        'rejected_plural' => 'rejeitados',
        'analyze_tooltip' => 'Analisar produtos do slot',
        'roles' => [
            'destino' => 'Destino',
            'rotina' => 'Rotina',
            'conveniencia' => 'Conv.',
            'impulso' => 'Impulso',
            'sazonal' => 'Sazonal',
            'complementar' => 'Compl.',
        ],
        'expansion' => [
            'score' => 'ABC',
            'current_stock' => 'Estoque',
            'target_stock' => 'Déficit',
            'equal' => 'Igual',
        ],
    ],

    // Slot category select (SlotCategorySelect.vue)
    'category_select' => [
        'base_template_hint' => 'categoria base do template',
        'no_subcategories' => 'Sem subcategorias — o slot usará toda a categoria base.',
        'level_placeholder' => 'Nível :n…',
        'no_selection_hint' => 'Sem seleção abaixo → usa a categoria base (todos os produtos da gôndola neste slot).',
    ],

    // Review slots list (ReviewSlotsList.vue)
    'review_list' => [
        'title' => 'Slots criados',
        'shelves_count' => ':count prateleiras',
        'shelf_label' => 'Prateleira :n',
        'edit_slot_tooltip' => 'Editar slot',
        'empty_message' => 'Nenhum slot criado para este módulo.',
        'no_category' => 'Sem categoria',
    ],

    // Review panel (ReviewSlotProductsPanel.vue)
    'review_panel' => [
        'title' => 'Análise de alocação',
        'update_images_button' => 'Atualizar imagens',
        'zone_hot' => 'quente',
        'zone_cold' => 'fria',
        'zone_neutral' => 'neutra',
        'full_simulation' => 'simulação completa',
        'select_slot_hint' => 'Selecione um slot para iniciar a análise.',
        'analyzing' => 'Analisando produtos...',
        'no_analysis' => 'Nenhum dado de análise para este slot.',
        'filter_placeholder' => 'Filtrar por nome, EAN ou código ERP',
        'summary' => [
            'total_products' => 'Total na categoria',
            'previous_slots' => 'Prateleiras anteriores',
            'placed' => 'Entrou aqui',
            'other_slot' => 'Outro slot',
            'rejected' => 'Fora',
            'free_width' => 'Livre (cm)',
        ],
        'columns' => [
            'product' => 'Produto',
            'status' => 'Status',
            'reason' => 'Motivo',
            'abc' => 'ABC',
            'sales' => 'Venda',
            'dimensions' => 'Dimensões',
            'facing' => 'Facing',
            'position_cm' => 'Pos. (cm)',
            'width_cm' => 'Larg. (cm)',
        ],
        'no_image' => 'Sem imagem',
        'ean_label' => 'EAN',
        'brand_label' => 'Marca',
        'erp_code_label' => 'Cód. ERP',
        'has_sales_yes' => 'Sim',
        'has_sales_no' => 'Não',
        'status_other_slot' => 'outro slot',
        'mandatory_badge' => 'OBR',
        'mandatory_tooltip' => 'Produto obrigatório',
    ],

    // Slot review drawer (SlotReviewDrawer.vue)
    'review_drawer' => [
        'title' => 'Análise de slot',
    ],

    // Visual criteria editor (VisualCriteriaEditor.vue)
    'visual_criteria' => [
        'title' => 'Critérios de ordenação visual',
        'description_legacy' => 'Usando ordenação padrão (preço / tamanho / marca).',
        'description_custom' => 'Critérios ativos — arraste para reordenar. O mais à esquerda domina.',
        'customize_button' => 'Personalizar',
        'use_default_button' => 'Usar padrão',
        'empty_message' => 'Nenhum critério ativo — adicione abaixo ou reverta para o padrão.',
        'direction_asc' => 'Crescente — clique para inverter',
        'direction_desc' => 'Decrescente — clique para inverter',
        'direction_none' => 'Sem direção — clique para definir',
        'remove_criterion_tooltip' => 'Remover critério',
        'abc_locked_tooltip' => 'Curva ABC é sempre o primeiro critério e não pode ser movida',
        'add_label' => 'Adicionar:',
        'packaging_order' => [
            'title' => 'Ordem dos tipos de embalagem',
            'description' => 'Arraste para reordenar. Tipos não listados vão para o fim.',
            'empty_message' => 'Nenhum tipo adicionado — todos os produtos ficam juntos sem distinção de embalagem.',
            'remove_tooltip' => 'Remover tipo',
            'add_placeholder' => 'Ex: caixa, sache, pet, lata…',
            'add_button' => '+ Adicionar',
        ],
        'criteria_labels' => [
            'marca' => 'Marca',
            'preco' => 'Preço',
            'tamanho' => 'Tamanho',
            'score_abc' => 'Curva ABC',
            'margem' => 'Margem',
            'embalagem' => 'Embalagem',
            'tipo' => 'Tipo de produto',
            'sabor' => 'Sabor',
            'atributo' => 'Atributo de sortimento',
        ],
        'list_aria_label' => 'Critérios de ordenação (mais à esquerda = maior prioridade)',
        'chip_aria_label' => ':label, prioridade :n',
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
        'already_added' => 'Já adicionado',
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

    // Slots page (Slots.vue)
    'slots_page' => [
        'head_title' => 'Slots — :code',
        'breadcrumb' => 'Slots',
        'step2_description' => 'Etapa 2 — configure as categorias por módulo e prateleira',
        'category_label' => 'Categoria: :name',
        'export_button' => 'Exportar planilha',
        'import_button' => 'Importar planilha',
        'modules_label' => 'Módulos:',
        'remove_subtemplate_tooltip' => 'Remover este subtemplate',
        'module_defaults_button' => 'Configuração padrão do módulo',
        'shelves_label' => 'Prateleiras:',
        'no_subtemplate_message' => 'Nenhum subtemplate configurado para :count módulo(s). Como deseja criar?',
        'no_subtemplate_message_plural' => 'Nenhum subtemplate configurado para :count módulos. Como deseja criar?',
        'create_empty_button' => 'Criar do zero',
        'clone_from_button' => 'Copiar de :count módulos',
        'next_button' => 'Próximo — Revisão de slots',
        'confirm_remove_slot_title' => 'Remover slot?',
        'confirm_remove_slot_description' => 'Este slot será removido desta posição do template.',
        'confirm_remove' => 'Remover',
        'confirm_remove_subtemplate_title_singular' => 'Remover 1 módulo?',
        'confirm_remove_subtemplate_title_plural' => 'Remover :count módulos?',
        'confirm_remove_subtemplate_description_singular' => 'Todos os slot deste módulo será removido permanentemente. Esta ação não pode ser desfeita.',
        'confirm_remove_subtemplate_description_plural' => 'Todos os slots dos :count módulos serão removidos permanentemente. Esta ação não pode ser desfeita.',
        'confirm_swap_title' => 'Trocar slots?',
        'confirm_swap_description' => 'A posição de destino já está ocupada. Os dois slots serão trocados.',
        'confirm_swap' => 'Trocar',
        'copy_shelves_title' => 'Copiar configuração para as outras prateleiras?',
        'copy_shelves_description' => 'A mesma configuração (incluindo a categoria) será aplicada às prateleiras vazias deste módulo. Você pode ajustar cada uma depois.',
        'copy_module_title' => 'Copiar configuração deste módulo para os outros?',
        'copy_module_description' => 'Os slots deste módulo serão replicados para os módulos ainda vazios.',
        'copy_action_all' => 'Copiar e fechar',
        'copy_action_next_shelf' => 'Copiar e editar a próxima',
        'copy_action_dismiss' => 'Agora não',
        'copy_action_next_module' => 'Próximo módulo',
        'copy_action_all_modules' => 'Todos os módulos vazios',
        'alteration_toast' => 'Slot atualizado — planogramas gerados precisam de: :level',
    ],

    // Review page (Review.vue)
    'review_page' => [
        'head_title' => 'Revisão — :code',
        'breadcrumb' => 'Revisão',
        'step3_description' => 'Etapa 3 — revise os slots e os produtos relacionados',
        'modules_label' => 'Módulos:',
        'back_button' => 'Voltar — Slots',
        'next_button' => 'Próximo — Produtos',
    ],

    // Show page (Show.vue)
    'show_page' => [
        'head_title' => 'Template :code',
        'cannot_undo' => 'Esta ação não pode ser desfeita.',
        'badge_hot_zone' => 'Zona quente: :label',
        'badge_cold_zone' => 'Zona fria: :label',
    ],

    // Form page (Form.vue)
    'form_page' => [
        'category_required_tooltip' => 'Selecione uma categoria antes de configurar os slots',
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

<?php

return [
    'package' => [
        'migrations' => [
            // Caminho usado por plannerate:migrations:sync
            'client_path' => env('PLANNERATE_PACKAGE_CLIENT_MIGRATIONS_PATH', 'database/migrations/clients'),
        ],
    ],
    'auto_planogram' => [
        /*
         * Parâmetros físicos do posicionamento na prateleira.
         */
        'placement' => [
            /*
             * Folga (em cm) entre produtos vizinhos na mesma prateleira.
             *
             * O motor empacotava os produtos encostados (folga zero), o que é
             * fisicamente irreal: na loja há uma pequena folga entre facings para o
             * repositor conseguir tirar/repor a peça. Ignorá-la faz o planograma
             * prometer mais produto do que a prateleira comporta de verdade.
             *
             * A folga só é cobrada ENTRE produtos — não sobra folga no fim da prateleira.
             *
             * Default 0.0 = comportamento anterior (nada muda até o cliente calibrar).
             */
            'product_spacing_cm' => env('PLANNERATE_PRODUCT_SPACING_CM', 0.0),

            /*
             * Meta de ocupação das prateleiras (0-1).
             *
             * Quanto da largura útil a gôndola deveria usar para ser considerada "fechada".
             * Hoje é usada para MEDIR (o relatório da geração aponta quantas prateleiras
             * ficaram abaixo do alvo); agir para fechar o vão é o objetivo das Fases 2 e 3
             * do plano (docs/gondola-precisao-automatica/).
             */
            'target_occupancy_rate' => env('PLANNERATE_TARGET_OCCUPANCY_RATE', 0.90),

            /*
             * Empacotador da prateleira: 'knapsack' (padrão) ou 'greedy' (motor antigo).
             *
             * 'knapsack' resolve a prateleira inteira de uma vez (programação dinâmica), com as
             * frentes como variável livre e os rejeitados por espaço reconsiderados — é o que
             * fecha o vão que o guloso deixava aberto. Tudo que o guloso colocaria entra como
             * obrigatório no modelo, então nenhum SKU é perdido em relação ao motor antigo.
             *
             * 'greedy' restaura o first-fit + round-robin sem precisar de deploy, caso alguma
             * gôndola real saia pior do que na versão anterior.
             */
            'packer' => env('PLANNERATE_SHELF_PACKER', 'knapsack'),

            /*
             * Até onde um produto rejeitado por falta de espaço pode andar no overflow:
             * 'strict' | 'siblings' (padrão) | 'any'.
             *
             * Medido numa gôndola real: 257cm de prateleira VAZIA convivendo com 11 produtos
             * rejeitados por falta de espaço. Não faltava espaço — faltava PERMISSÃO. A
             * categoria sem produto para encher a prateleira dela segurava o vão, e a categoria
             * que transbordava não podia usá-lo, porque o overflow só realocava dentro da MESMA
             * categoria.
             *
             * 'strict'   — só a própria categoria (e descendentes). Blocagem intacta, gôndola
             *              aberta. É o comportamento anterior.
             * 'siblings' — também as categorias IRMÃS (mesmo pai no mercadológico). Fecha a maior
             *              parte do vão sem virar bagunça na gaveta: irmãs já ficam juntas na loja
             *              (LÍQUIDO ao lado de GEL, ambas filhas de CUIDADO COM O BANHEIRO).
             * 'any'      — qualquer categoria do planograma. Fecha tudo, mas quebra a blocagem.
             */
            'overflow_scope' => env('PLANNERATE_OVERFLOW_SCOPE', 'siblings'),

            /*
             * Número de filhos diretos acima do qual uma categoria intermediária deixa de ser
             * expandida — só entra em ação quando o setting "agrupar por subcategoria"
             * (PlacementSettings::$groupBySubcategory) está ligado.
             *
             * Medido numa gôndola real: "LIMPADOR" tem 30 subcategorias-filha. Expandir até a
             * folha dá 1 slot (≈1 prateleira) por filha — nenhuma delas nunca acumula as 2+
             * prateleiras consecutivas que a blocagem vertical exige, e a categoria inteira
             * (383cm de demanda real) fica fatiada em fragmentos que se espalham pela gôndola
             * via overflow. Acima do limiar, o nó intermediário volta a ser tratado como UM
             * slot só — a soma de toda a demanda dos descendentes concorre por espaço como uma
             * unidade, podendo formar bloco vertical de verdade (as marcas ainda formam colunas
             * dentro do bloco, via TemplatePlacementEngine).
             *
             * Nós com poucos filhos (ex.: "Flocão" → [De Milho, De Arroz]) continuam expandindo
             * normalmente — cada um é uma distinção real que merece prateleira própria.
             */
            'subcategory_group_threshold' => env('PLANNERATE_SUBCATEGORY_GROUP_THRESHOLD', 8),
        ],

        /*
         * Parâmetros do cálculo de Estoque Alvo por classe ABC.
         *
         * service_levels: nível de serviço usado no z-score do estoque de segurança.
         * coverage_days : dias de cobertura do estoque mínimo (média diária × dias).
         *
         * Defaults preservam a regra da planilha VBA original (docs/ESTOQUE-ALVO.md):
         * classe A repõe com mais frequência (menos dias, NS menor). Tenants que
         * preferem o padrão de mercado (A com NS mais alto) podem sobrescrever aqui.
         */
        'target_stock' => [
            'service_levels' => [
                'A' => 0.7,
                'B' => 0.8,
                'C' => 0.9,
            ],
            'coverage_days' => [
                'A' => 2,
                'B' => 5,
                'C' => 7,
            ],

            /*
             * Teto de unidades em profundidade ("de fundo") consideradas por frente ao
             * converter estoque alvo → frentes no placement (use_target_stock).
             *
             * A capacidade física pura (floor(shelf_depth / product_depth)) costuma ser
             * irrealista para exposição (ex.: 8 unidades de fundo numa prateleira de 40cm),
             * fazendo produtos de alvo baixo travarem em 1 frente e deixarem a prateleira
             * vazia. Este teto limita a premissa de profundidade a um valor de exposição
             * típico, gerando mais frentes para alvos pequenos sem deixar de respeitar o alvo.
             *
             * null = sem teto (usa a capacidade física pura).
             */
            'max_facing_depth' => 3,
        ],
    ],
    'defaults' => [
        'gondola' => [
            'gondolaName' => 'GND-2602-1841',
            'location' => 'Corredor  03',
            'side' => 'A',
            'scaleFactor' => 3,
            'flow' => 'left_to_right',
            'height' => 200,
            'width' => 100,
            'numModules' => 4,
            'baseHeight' => 20,
            'baseWidth' => 100,
            'baseDepth' => 50,
            'rackWidth' => 4,
            'holeHeight' => 3,
            'holeWidth' => 2,
            'holeSpacing' => 2,
            'shelfHeight' => 4,
            'shelfWidth' => 100,
            'shelfDepth' => 40,
            'numShelves' => 4,
            'productType' => 'normal',
            'notes' => null,
        ],
    ],
];

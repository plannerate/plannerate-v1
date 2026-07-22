<?php

use App\Models\EanReference;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\AppNotification;

return [
    'timeout' => env('INTEGRATION_TIMEOUT', 60),

    'fetch_delay' => env('INTEGRATION_FETCH_DELAY', 3),

    /*
     * Janela (em dias) sempre re-buscada no sync incremental, mesmo quando os
     * dias já têm registros: um dia com fetch interrompido no meio (só parte
     * das páginas persistida) contaria como "completo" e nunca seria
     * completado. A re-busca é idempotente (upsert por id determinístico).
     */
    'recheck_days' => env('INTEGRATION_RECHECK_DAYS', 3),

    /*
     * TTL (segundos) do cache do bearer token obtido via token_mode "fetch".
     * Sem cache, cada página buscada disparava um request de token. Em 401 o
     * cache é invalidado e a tentativa seguinte busca um token novo.
     */
    'token_cache_seconds' => env('INTEGRATION_TOKEN_CACHE_SECONDS', 300),

    'import_clear_tables' => [
        'products' => ['product_store', 'products'],
        'sales' => ['sales'],
    ],

    /*
     * Models da aplicação que o motor precisa alcançar via Eloquent. O pipeline de
     * importação não usa nenhum deles (opera sobre Connection/arrays); são os pontos de
     * lookup avulso, sync com a base de EANs e notificação de conclusão.
     *
     * O model de tenant NÃO entra aqui: vem de `multitenancy.tenant_model` (Spatie).
     */
    'models' => [
        'product' => Product::class,
        'store' => Store::class,
        'user' => User::class,
        'ean_reference' => EanReference::class,
    ],

    /*
     * Critério de "loja importável", aplicado pelo ConfiguredStoresProvider: nome de um
     * scope local do model de loja (string vazia = sem filtro) e a coluna que guarda o
     * CNPJ/CPF enviado ao ERP. Quem precisar de outra regra rebinda o contrato
     * App\Services\Integrations\Contracts\StoresProvider no container.
     */
    'store_scope' => 'published',

    'store_document_column' => 'document',

    /*
     * Notificação disparada ao fim de comandos de manutenção (sync:link-sales).
     * Precisa aceitar os argumentos nomeados `title`, `message` e `type`.
     * `null` desliga o envio.
     */
    'notification' => AppNotification::class,

    /*
     * Nomes reais das tabelas do tenant que o motor lê e escreve. O motor é genérico,
     * mas alcança tabelas do domínio da aplicação; a chave é o papel, o valor é o nome
     * no banco. Resolvido por App\Services\Integrations\Support\IntegrationTables.
     */
    'tables' => [
        'products' => 'products',
        'sales' => 'sales',
        'stores' => 'stores',
        'categories' => 'categories',
        'layers' => 'layers',
        'product_store' => 'product_store',
        'monthly_sales_summaries' => 'monthly_sales_summaries',
    ],

    /*
     * Chaves naturais protegidas por índice único além da PK, usadas pelo
     * TenantNaturalKeyReconciler para realinhar o id determinístico do lote com a linha
     * que já é dona daquela chave. As colunas espelham o índice único do banco, sem o
     * tenant_id (aplicado como filtro à parte).
     *
     * `soft_deletes` precisa refletir a realidade da tabela: com true, a linha apagada
     * dona da chave é reusada e restaurada; com false (ou ausente), o reconciler insere
     * uma linha nova e o índice único parcial estoura duplicate key.
     */
    'natural_keys' => [
        'products' => ['columns' => ['ean'], 'soft_deletes' => true],
        'sales' => ['columns' => ['store_id', 'codigo_erp', 'sale_date', 'promotion'], 'soft_deletes' => true],
    ],

    'field_map_tables' => [
        'products' => [
            'label' => 'Produtos',
            'columns' => [
                'tenant_id',
                'user_id',
                'image_id',
                'category_id',
                'client_id',
                'name',
                'slug',
                'ean',
                'codigo_erp',
                'stackable',
                'perishable',
                'flammable',
                'hangable',
                'no_sales',
                'no_purchases',
                'description',
                'sales_status',
                'sales_purchases',
                'status',
                'sync_source',
                'resolution_status',
                'resolution_details',
                'sync_at',
                'url',
                'type',
                'reference',
                'fragrance',
                'flavor',
                'color',
                'brand',
                'subbrand',
                'packaging_type',
                'packaging_size',
                'measurement_unit',
                'packaging_content',
                'unit_measure',
                'auxiliary_description',
                'additional_information',
                'sortiment_attribute',
                'width',
                'height',
                'depth',
                'weight',
                'unit',
                'has_dimensions',
                'dimension_status',
                'current_stock',
                'last_purchase_date',
            ],
        ],
        'sales' => [
            'label' => 'Vendas',
            'columns' => [
                'tenant_id',
                'store_id',
                'product_id',
                'ean',
                'codigo_erp',
                'acquisition_cost',
                'sale_price',
                'total_profit_margin',
                'sale_date',
                'promotion',
                'total_sale_quantity',
                'total_sale_value',
                'margem_contribuicao',
                'extra_data',
            ],
        ],
        'stores' => [
            'label' => 'Lojas',
            'columns' => [
                'tenant_id',
                'user_id',
                'name',
                'document',
                'slug',
                'code',
                'phone',
                'email',
                'status',
                'description',
                'map_image_path',
                'map_regions',
            ],
        ],
    ],
];

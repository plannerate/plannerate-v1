<?php

use App\Models\IntegrationApi;
use Illuminate\Database\Migrations\Migration;

/**
 * Adiciona o bloco `requests.lookups` (busca pontual por produto) ao blueprint Sysmo.
 *
 * O bloco `lookups` é lido apenas pela busca sob demanda de um único produto
 * (SingleProductFetchService) e é IGNORADO pelo import em massa, que só percorre
 * `requests.paths`. Reaproveita os field_maps já existentes de products/sales
 * para não haver divergência de mapeamento. Idempotente e guardado: não faz nada
 * se o blueprint Sysmo não existir ou se `lookups` já estiver presente.
 */
return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        $api = IntegrationApi::query()->where('slug', 'sysmo')->first();

        if ($api === null) {
            return;
        }

        $requests = $api->requests ?? [];

        if (data_get($requests, 'lookups') !== null) {
            return;
        }

        $productsFieldMap = (array) data_get($requests, 'paths.products.field_map', []);
        $salesFieldMap = (array) data_get($requests, 'paths.sales.field_map', []);

        $requests['lookups'] = [
            'product' => [
                'target_table' => 'products',
                'fallback_path' => '/hubprodutos.consultar_produto',
                'method' => 'post',
                'lookup_field' => 'produto',
                'lookup_key' => 'codigo_erp',
                'store_field' => 'empresa',
                'store_key' => 'code',
                'single_item' => true,
                'extra_params' => ['somente_precos' => 'N'],
                'response' => ['items_path' => ''],
                'unique_by' => ['ean'],
                'field_map' => $productsFieldMap,
            ],
            'sales' => [
                'target_table' => 'sales',
                'fallback_path' => '/hubvendas.vendas_produtos',
                'method' => 'post',
                'lookup_field' => 'produto',
                'lookup_key' => 'ean',
                'store_field' => 'empresa',
                'store_key' => 'document',
                'extra_params' => ['tipo_consulta' => 'produto'],
                'date_fields' => ['start' => 'data_inicial', 'end' => 'data_final'],
                'initial_days' => 200,
                'response' => ['items_path' => 'dados'],
                'unique_by' => ['codigo_erp', 'sale_date', 'promotion'],
                'include_store_in_id' => true,
                'field_map' => $salesFieldMap,
            ],
        ];

        $api->requests = $requests;
        $api->save();
    }

    public function down(): void
    {
        $api = IntegrationApi::query()->where('slug', 'sysmo')->first();

        if ($api === null) {
            return;
        }

        $requests = $api->requests ?? [];

        if (! array_key_exists('lookups', $requests)) {
            return;
        }

        unset($requests['lookups']);

        $api->requests = $requests;
        $api->save();
    }
};

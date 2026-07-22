<?php

use App\Models\IntegrationApi;
use Illuminate\Database\Migrations\Migration;

/**
 * Passa `current_stock` e `last_purchase_date` do blueprint RP Info a alimentarem
 * apenas `product_store`, não mais a coluna homônima de `products`.
 *
 * Motivo: as duas métricas são POR UNIDADE no feed (estoque da loja; última compra
 * daquela filial). O id do produto deriva de tenant+ean — sem loja — então as duas
 * cadeias de importação gravam na mesma linha de `products` e vence a última a
 * terminar. Medido na RP Info: 67 de 100 produtos da primeira página têm estoque
 * diferente entre Matriz e Filial.
 *
 * O `field_map` continua igual: o valor precisa ser mapeado para chegar ao
 * registro. O que muda é `pivot_only_targets`, que faz o
 * TenantUpsertRecordPreparer removê-lo antes do upsert de `products`, e o
 * `update_columns` da pivot, sem o qual o upsert só tocaria `updated_at` e o
 * estoque congelaria no valor do primeiro import.
 *
 * Depende da migration de tenant que cria as colunas em `product_store`
 * (`2026_07_21_000100_add_store_scoped_metrics_to_product_store_table`).
 *
 * Guardada e idempotente.
 */
return new class extends Migration
{
    protected $connection = 'landlord';

    private const SLUG = 'rpinfo';

    /** @var array<int, string> */
    private const STORE_SCOPED_TARGETS = ['current_stock', 'last_purchase_date'];

    public function up(): void
    {
        $this->updateBlueprint(self::STORE_SCOPED_TARGETS, self::STORE_SCOPED_TARGETS);
    }

    public function down(): void
    {
        $this->updateBlueprint([], []);
    }

    /**
     * @param  array<int, string>  $pivotOnlyTargets
     * @param  array<int, string>  $pivotUpdateColumns
     */
    private function updateBlueprint(array $pivotOnlyTargets, array $pivotUpdateColumns): void
    {
        $api = IntegrationApi::query()->where('slug', self::SLUG)->first();

        if ($api === null) {
            return;
        }

        $requests = $api->requests ?? [];

        if (data_get($requests, 'paths.products') === null) {
            return;
        }

        if ($pivotOnlyTargets === []) {
            data_forget($requests, 'paths.products.pivot_only_targets');
        } else {
            data_set($requests, 'paths.products.pivot_only_targets', $pivotOnlyTargets);
        }

        $pivots = (array) data_get($requests, 'paths.products.pivot_tables', []);

        foreach ($pivots as $index => $pivot) {
            if ((string) data_get($pivot, 'table') !== 'product_store') {
                continue;
            }

            if ($pivotUpdateColumns === []) {
                unset($pivots[$index]['update_columns']);

                continue;
            }

            $pivots[$index]['update_columns'] = $pivotUpdateColumns;
        }

        data_set($requests, 'paths.products.pivot_tables', array_values($pivots));

        // Update pela query base, sem eventos: o HasSlug regenera o slug a partir
        // do `name` em TODO save (inclusive update), e "RP Info" viraria
        // "rp-info" — renomeando o blueprint que esta própria migration procura.
        IntegrationApi::query()
            ->whereKey($api->getKey())
            ->toBase()
            ->update(['requests' => json_encode($requests), 'updated_at' => now()]);
    }
};

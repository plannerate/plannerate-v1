<?php

use App\Models\IntegrationApi;
use Illuminate\Database\Migrations\Migration;

/**
 * Fecha a migração de `current_stock` / `last_purchase_date` para `product_store`
 * nos blueprints que ainda gravavam nas colunas homônimas de `products`.
 *
 * O rpinfo já tinha sido migrado em
 * `2026_07_21_000003_move_rpinfo_store_metrics_to_pivot`; sysmo e gescooper ficaram
 * para trás. Com os leitores lendo só da pivot (ver
 * `App\Models\Traits\HasStoreScopedMetrics`), um blueprint que ainda escreve em
 * `products` deixa o tenant com estoque nulo na tela — daí fechar os três de uma vez
 * em vez de carregar um fallback para sempre.
 *
 * Só entra na lista o que o blueprint realmente mapeia: gescooper não traz estoque,
 * então mover `current_stock` lá seria ruído. Cada um recebe exatamente os alvos
 * store-scoped que aparecem no seu próprio `field_map`.
 *
 * Guardada e idempotente: blueprint ausente ou sem `paths.products` é ignorado.
 */
return new class extends Migration
{
    protected $connection = 'landlord';

    /**
     * Alvos que são POR LOJA no feed e passam a alimentar apenas `product_store`.
     *
     * @var array<int, string>
     */
    private const STORE_SCOPED_TARGETS = ['current_stock', 'last_purchase_date'];

    /** @var array<int, string> */
    private const SLUGS = ['sysmo', 'gescooper'];

    public function up(): void
    {
        foreach (self::SLUGS as $slug) {
            $this->updateBlueprint($slug, true);
        }
    }

    public function down(): void
    {
        foreach (self::SLUGS as $slug) {
            $this->updateBlueprint($slug, false);
        }
    }

    private function updateBlueprint(string $slug, bool $enable): void
    {
        $api = IntegrationApi::query()->where('slug', $slug)->first();

        if ($api === null) {
            return;
        }

        $requests = $api->requests ?? [];

        if (data_get($requests, 'paths.products') === null) {
            return;
        }

        $targets = $enable ? $this->mappedStoreScopedTargets($requests) : [];

        if ($targets === []) {
            data_forget($requests, 'paths.products.pivot_only_targets');
        } else {
            data_set($requests, 'paths.products.pivot_only_targets', $targets);
        }

        $pivots = (array) data_get($requests, 'paths.products.pivot_tables', []);

        foreach ($pivots as $index => $pivot) {
            if ((string) data_get($pivot, 'table') !== 'product_store') {
                continue;
            }

            if ($targets === []) {
                unset($pivots[$index]['update_columns']);

                continue;
            }

            $pivots[$index]['update_columns'] = $targets;
        }

        data_set($requests, 'paths.products.pivot_tables', array_values($pivots));

        // Update pela query base, sem eventos: o HasSlug regenera o slug a partir do
        // `name` em TODO save (inclusive update), renomeando o blueprint que esta
        // própria migration procura.
        IntegrationApi::query()
            ->whereKey($api->getKey())
            ->toBase()
            ->update(['requests' => json_encode($requests), 'updated_at' => now()]);
    }

    /**
     * Interseção entre os alvos store-scoped e o que o blueprint mapeia de fato.
     *
     * @param  array<string, mixed>  $requests
     * @return array<int, string>
     */
    private function mappedStoreScopedTargets(array $requests): array
    {
        $mapped = array_map(
            static fn (mixed $field): string => (string) data_get($field, 'target'),
            (array) data_get($requests, 'paths.products.field_map', []),
        );

        return array_values(array_intersect(self::STORE_SCOPED_TARGETS, $mapped));
    }
};

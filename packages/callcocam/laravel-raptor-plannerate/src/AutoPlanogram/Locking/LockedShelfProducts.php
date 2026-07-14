<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Locking;

use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\Segment;

/**
 * Produtos que já ocupam prateleiras travadas da gôndola.
 *
 * O motor não reposiciona esses produtos (os slots das prateleiras travadas são filtrados antes
 * do placement). Se eles continuassem no pool de candidatos, seriam posicionados TAMBÉM em outra
 * prateleira — o mesmo SKU aparecendo duas vezes na gôndola, uma vez travado e outra não.
 *
 * Classe própria (e não um método privado do runner) porque é uma invariante que precisa de prova:
 * o erro aqui não quebra nada visivelmente, só duplica produto.
 */
final class LockedShelfProducts
{
    /**
     * @return array<string, true> Mapa product_id => true, para lookup O(1).
     */
    public function forGondola(Gondola $gondola): array
    {
        $gondola->loadMissing('sections.shelves');

        $lockedShelfIds = $gondola->sections
            ->flatMap(fn ($section) => $section->shelves->filter(fn ($shelf) => (bool) $shelf->is_locked)->pluck('id'))
            ->filter()
            ->values();

        if ($lockedShelfIds->isEmpty()) {
            return [];
        }

        return Layer::query()
            ->whereIn('segment_id', Segment::query()->whereIn('shelf_id', $lockedShelfIds)->select('id'))
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->mapWithKeys(fn (string $productId): array => [$productId => true])
            ->all();
    }
}

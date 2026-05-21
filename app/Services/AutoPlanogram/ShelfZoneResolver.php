<?php

namespace App\Services\AutoPlanogram;

use App\Enums\ShelfLevel;

/**
 * Resolve a zona térmica de uma prateleira a partir de sua posição física.
 *
 * Zona quente: Eye + Hand → produtos de maior valor estratégico.
 * Zona fria: High + Low → produtos complementares ou de menor rotatividade.
 */
final class ShelfZoneResolver
{
    /**
     * @param  int  $shelfPosition  Posição física: 0 = topo, (numShelves-1) = chão
     * @param  int  $numShelves  Total de prateleiras na gôndola
     * @return 'hot'|'cold'|'neutral'
     */
    public static function resolve(int $shelfPosition, int $numShelves): string
    {
        $level = ShelfLevel::fromShelfPosition($shelfPosition, $numShelves);

        return match ($level) {
            ShelfLevel::Eye, ShelfLevel::Hand => 'hot',
            ShelfLevel::High, ShelfLevel::Low => 'cold',
        };
    }
}

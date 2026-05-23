<?php

namespace App\Services\AutoPlanogram\DTO;

use App\Enums\CategoryRole;

/**
 * Uma entrada do plano de slots sintetizado pelo SlotPlanBuilder.
 * Cada instância representa uma (módulo, prateleira) com a categoria alocada.
 */
final readonly class SlotPlanEntry
{
    /**
     * @param  list<array{key: string, direction: string}>  $visualCriteria  ABC sempre primeiro.
     * @param  'hot'|'cold'|'neutral'  $zone
     */
    public function __construct(
        public string $categoryId,
        public int $moduleNumber,
        public int $shelfOrder,
        public int $minFacings,
        public array $visualCriteria,
        public string $zone,
        public ?CategoryRole $roleOverride = null,
        public ?int $maxFacings = null,
        public ?string $facingExpansion = null,
        public bool $useTargetStock = false,
        public ?string $spaceFallback = null,
        public ?int $maxSharePerSku = null,
        public ?int $maxSharePerBrand = null,
        public ?int $maxSharePerSubcategory = null,
    ) {}
}

<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO;

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductWidthResolver;
use Illuminate\Support\Collection;

/**
 * ProductBlock com ordem de sequência definida pelo resolver de adjacência.
 */
final readonly class OrderedBlock
{
    public function __construct(
        public ProductBlock $block,
        /** Posição na sequência horizontal da gôndola (0-based) */
        public int $sequenceOrder,
    ) {}

    /**
     * @param  Collection<int, ScoredProduct>  $children
     */
    public function withChildren(Collection $children, ?ProductWidthResolver $widthResolver = null): self
    {
        return new self(
            block: $this->block->withChildren($children, $widthResolver),
            sequenceOrder: $this->sequenceOrder,
        );
    }
}

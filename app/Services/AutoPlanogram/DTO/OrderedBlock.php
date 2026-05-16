<?php

namespace App\Services\AutoPlanogram\DTO;

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
}

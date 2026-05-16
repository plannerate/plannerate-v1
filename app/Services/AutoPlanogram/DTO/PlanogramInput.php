<?php

namespace App\Services\AutoPlanogram\DTO;

use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Illuminate\Support\Collection;

/**
 * Entrada para o processo de geração do planograma.
 *
 * Carrega todos os dados necessários para o pipeline:
 * planograma, gôndola, produtos do tenant e configurações.
 */
final readonly class PlanogramInput
{
    public function __construct(
        /** ULID do planograma */
        public string $planogramId,
        /** ULID da gôndola */
        public string $gondolaId,
        /** ULID do tenant */
        public string $tenantId,
        /**
         * Produtos já filtrados por categoria para esta gôndola.
         *
         * @var Collection<int, Product>
         */
        public Collection $products,
        /**
         * Sections (módulos) da gôndola com shelves carregadas.
         *
         * @var Collection<int, Section>
         */
        public Collection $sections,
        public PlacementSettings $settings,
    ) {}
}

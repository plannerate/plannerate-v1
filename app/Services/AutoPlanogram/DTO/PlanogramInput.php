<?php

namespace App\Services\AutoPlanogram\DTO;

use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
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
        /**
         * Categoria-base do planograma (âncora do escopo para validação no modo automático).
         * Null em contextos legados ou de teste onde o planograma não está disponível.
         */
        public ?string $planogramCategoryId = null,
    ) {}
}

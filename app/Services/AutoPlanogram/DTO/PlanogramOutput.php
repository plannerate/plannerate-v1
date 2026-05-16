<?php

namespace App\Services\AutoPlanogram\DTO;

use Illuminate\Support\Collection;

/**
 * Resultado da geração do planograma.
 *
 * @phpstan-type OutputArray array{gondola_id: string, total_allocated: int, total_unallocated: int, validation: array{passed: bool, warnings: list<string>}}
 */
final readonly class PlanogramOutput
{
    public function __construct(
        public string $gondolaId,
        /**
         * Todos os segmentos colocados nas prateleiras.
         *
         * @var Collection<int, PlacedSegment>
         */
        public Collection $placedSegments,
        public ValidationReport $validationReport,
    ) {}

    public function totalAllocated(): int
    {
        return $this->placedSegments->count();
    }

    /**
     * @return OutputArray
     */
    public function toArray(): array
    {
        return [
            'gondola_id' => $this->gondolaId,
            'total_allocated' => $this->totalAllocated(),
            'total_unallocated' => 0,
            'validation' => $this->validationReport->toArray(),
        ];
    }
}

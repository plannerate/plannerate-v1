<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO;

use Callcocam\LaravelRaptorPlannerate\Enums\PlacementFailureReason;
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
        /** @var Collection<int, array{product: mixed, reason: PlacementFailureReason}> */
        public Collection $rejectedProducts,
        public ValidationReport $validationReport,
        /** Tipo de score aplicado: 'composite', 'abc' ou 'neutral' (sem dados de venda no modo template) */
        public string $scoreType = 'composite',
        /** @var list<array<string, mixed>> Per-slot space analysis (template mode only) */
        public array $slotAnalysis = [],
        /** @var list<array<string, mixed>> Actionable suggestions (template mode only) */
        public array $suggestions = [],
        public bool $modulesMismatch = false,
        public int $templateModules = 0,
        public int $gondolaModules = 0,
        public ?string $subtemplateId = null,
        /** @var array{allocated: list<array<string, mixed>>, rejected: list<array<string, mixed>>, alerts: list<array<string, mixed>>} Justificativa por produto (template mode only) */
        public array $explanationReport = [],
        /** @var list<string> IDs de planogram_template_slots removidos por não ter produto (modo automático) */
        public array $emptySlotIds = [],
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
            'total_unallocated' => $this->rejectedProducts->count(),
            'validation' => $this->validationReport->toArray(),
        ];
    }
}

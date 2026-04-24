<?php

namespace Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\SectionGenerate;

/**
 * Resultado da geração por sections (orquestrador).
 */
readonly class SectionGenerateResultDTO
{
    public function __construct(
        public int $sectionsProcessed,
        public int $totalAllocated,
        public int $totalUnallocated,
        public string $generatedAt,
        /** @var array<string, float|int> */
        public array $qualityMetrics = [],
        /** @var array<int, array<string, int|string|float|array<string, int>>> */
        public array $sectionDiagnostics = [],
    ) {}
}

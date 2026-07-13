<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO;

use Callcocam\LaravelRaptorPlannerate\Enums\PlacementFailureReason;
use Illuminate\Support\Collection;

final readonly class PlacementResult
{
    public function __construct(
        /** @var Collection<int, PlacedSegment> */
        public Collection $placedSegments,
        /** @var Collection<int, array{product: mixed, reason: PlacementFailureReason}> */
        public Collection $rejectedProducts,
        /** @var list<array<string, mixed>> Per-slot space analysis (template mode only) */
        public array $slotAnalysis = [],
        public bool $modulesMismatch = false,
        public int $templateModules = 0,
        public int $gondolaModules = 0,
        public ?string $subtemplateId = null,
        /** @var array{allocated: list<array<string, mixed>>, rejected: list<array<string, mixed>>, alerts: list<array<string, mixed>>} Justificativa por produto (template mode only) */
        public array $explanationReport = [],
        /** @var list<string> IDs de planogram_template_slots sem candidatos nesta geração (sem produto para a categoria do slot) */
        public array $emptySlotIds = [],
        /**
         * Ocupação por PRATELEIRA FÍSICA, medida sobre os segmentos finais.
         *
         * O slotAnalysis acima é montado durante o laço de slots — ANTES do overflow pass. Tudo
         * que o overflow coloca nunca entra na conta dele. Resultado medido numa gôndola real:
         * a gôndola foi de 83,3% para 87,0% de ocupação e o relatório continuou cravado em
         * 76,8%, porque os produtos acrescentados vieram do overflow. A métrica não mexia.
         *
         * Esta análise é a verdade física: soma o que ficou em cada prateleira, no fim de tudo,
         * contra a largura real dela. É o número que bate com a gôndola que o usuário vê.
         *
         * @var list<array{shelf_id: string, section_id: string, largura_total: float, largura_usada: float, largura_livre: float, percentual_uso: int, segmentos: int}>
         */
        public array $shelfAnalysis = [],
    ) {}
}

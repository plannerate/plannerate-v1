<?php

namespace App\Services\AutoPlanogram;

use App\Services\AutoPlanogram\Adjacency\AdjacencyResolverInterface;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use App\Services\AutoPlanogram\DTO\PlanogramOutput;
use App\Services\AutoPlanogram\Grouping\BlockGrouperInterface;
use App\Services\AutoPlanogram\Placement\PlacementEngineInterface;
use App\Services\AutoPlanogram\Placement\PlanogramWriterInterface;
use App\Services\AutoPlanogram\Scoring\ProductScorerInterface;
use App\Services\AutoPlanogram\Validation\PlanogramValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orquestrador do pipeline de geração automática de planogramas.
 *
 * Pipeline:
 * 1. Score — pontua produtos por importância
 * 2. Group — agrupa em blocos coesos
 * 3. Adjacency — ordena blocos respeitando regras de adjacência
 * 4. Placement — distribui blocos pelas prateleiras
 * 5. Validation — verifica integridade do resultado
 * 6. Write — persiste no banco em transação
 */
final class AutoPlanogramService
{
    public function __construct(
        private readonly ProductScorerInterface $scorer,
        private readonly BlockGrouperInterface $grouper,
        private readonly AdjacencyResolverInterface $adjacency,
        private readonly PlacementEngineInterface $placement,
        private readonly PlanogramValidator $validator,
        private readonly PlanogramWriterInterface $writer,
    ) {}

    public function generate(PlanogramInput $input): PlanogramOutput
    {
        Log::info('AutoPlanogramService: iniciando geração', [
            'gondola_id' => $input->gondolaId,
            'planogram_id' => $input->planogramId,
            'products_count' => $input->products->count(),
            'strategy' => $input->settings->strategy,
        ]);

        $scored = $this->scorer->score($input->products, $input->settings);

        Log::info('AutoPlanogramService: produtos pontuados', ['count' => $scored->count()]);

        $blocks = $this->grouper->group($scored, $input->settings);
        $ordered = $this->adjacency->resolve($blocks, $input->settings);
        $placed = $this->placement->place($ordered, $input->sections, $input->settings);

        Log::info('AutoPlanogramService: segmentos posicionados', ['count' => $placed->count()]);

        $report = $this->validator->validate($placed, $input);

        DB::transaction(function () use ($input, $placed) {
            $this->writer->write($input->gondolaId, $input->sections, $placed);
        });

        Log::info('AutoPlanogramService: geração concluída', [
            'gondola_id' => $input->gondolaId,
            'segments_placed' => $placed->count(),
            'validation_passed' => $report->passed,
        ]);

        return new PlanogramOutput($input->gondolaId, $placed, $report);
    }
}

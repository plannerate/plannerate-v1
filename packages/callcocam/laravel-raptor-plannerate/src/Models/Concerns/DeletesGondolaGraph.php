<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Models\Concerns;

use App\Models\WorkflowGondolaExecution;
use App\Models\WorkflowHistory;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\GondolaAnalysis;
use Callcocam\LaravelRaptorPlannerate\Models\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramRejectedProduct;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Segment;
use Callcocam\LaravelRaptorPlannerate\Models\Shelf;

/**
 * Cascade de deleção do grafo completo da gôndola.
 *
 * Ao deletar uma gôndola, remove (soft delete) toda a hierarquia física
 * (sections → shelves → segments → layers), as análises persistidas, o histórico
 * de workflow vinculado e os produtos rejeitados da geração automática.
 *
 * Extraído do booted() do model Gondola para deixar a responsabilidade explícita
 * e testável de forma isolada.
 */
trait DeletesGondolaGraph
{
    /**
     * Registra o listener de deleting. Convenção boot{Trait} do Eloquent —
     * chamado automaticamente quando o model que usa o trait é inicializado.
     */
    protected static function bootDeletesGondolaGraph(): void
    {
        static::deleting(function (Gondola $gondola): void {
            $sectionIds = Section::where('gondola_id', $gondola->id)->pluck('id');
            $shelfIds = Shelf::whereIn('section_id', $sectionIds)->pluck('id');
            $segmentIds = Segment::whereIn('shelf_id', $shelfIds)->pluck('id');

            Layer::whereIn('segment_id', $segmentIds)->delete();
            Segment::whereIn('shelf_id', $shelfIds)->delete();
            Shelf::whereIn('section_id', $sectionIds)->delete();
            Section::where('gondola_id', $gondola->id)->delete();

            GondolaAnalysis::where('gondola_id', $gondola->id)->delete();

            $executionIds = WorkflowGondolaExecution::where('gondola_id', $gondola->id)->pluck('id');
            WorkflowHistory::whereIn('workflow_gondola_execution_id', $executionIds)->delete();
            WorkflowGondolaExecution::where('gondola_id', $gondola->id)->delete();

            PlanogramRejectedProduct::where('gondola_id', $gondola->id)->delete();
        });
    }
}

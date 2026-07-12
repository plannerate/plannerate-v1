<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation;

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramGenerationRun;
use Callcocam\LaravelRaptorPlannerate\Services\Generation\GenerationRunPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página do relatório da geração da gôndola.
 *
 * O relatório (capacidade, rejeitados, sugestões, validação) era despejado inteiro
 * embaixo do canvas do editor, empurrando o planograma para fora da tela. Aqui ele
 * ganha página própria — o editor só mostra a barra-resumo com link para cá.
 *
 * Sem `?run=`, exibe a última execução; com, exibe a execução pedida (histórico).
 */
class GenerationReportPageController extends Controller
{
    public function __construct(
        private readonly GenerationRunPresenter $presenter,
    ) {}

    public function show(Request $request, string $gondola): Response
    {
        $gondolaModel = Gondola::query()
            ->with(['planogram:id,name'])
            ->findOrFail($gondola);

        $this->authorize('view', $gondolaModel);

        // Desempate por id: o ULID é ordenável por tempo de criação, e duas execuções
        // podem cair no mesmo segundo — sem ele, `latest()` devolveria qualquer uma.
        $runs = PlanogramGenerationRun::query()
            ->where('gondola_id', $gondolaModel->id)
            ->latest()
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $requestedRunId = $request->string('run')->toString();

        $selected = $requestedRunId !== ''
            ? $runs->firstWhere('id', $requestedRunId)
            : $runs->first();

        return Inertia::render('tenant/editor/GenerationReport', [
            'gondola' => [
                'id' => $gondolaModel->id,
                'name' => $gondolaModel->name,
                'planogram_id' => $gondolaModel->planogram_id,
                'planogram_name' => $gondolaModel->planogram?->name,
                'generation_mode' => $gondolaModel->generation_mode,
            ],
            'run' => $selected ? $this->presenter->detail($selected) : null,
            'runs' => $runs->map(fn (PlanogramGenerationRun $run) => $this->presenter->summary($run))->values(),
            'editorUrl' => route('tenant.planograms.gondolas.editor', ['record' => $gondolaModel->id], false),
        ]);
    }
}

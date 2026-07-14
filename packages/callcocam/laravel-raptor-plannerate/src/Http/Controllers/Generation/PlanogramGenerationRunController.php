<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation;

use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunStatus;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramGenerationRun;
use Callcocam\LaravelRaptorPlannerate\Services\Generation\GenerationRunPresenter;
use Illuminate\Http\JsonResponse;

/**
 * Consulta do histórico de execuções da geração de planograma.
 *
 * Existe porque o resultado da geração deixou de ser síncrono: o job persiste o
 * relatório no PlanogramGenerationRun e a UI o busca aqui — tanto para exibir logo
 * após a conclusão quanto para reabrir execuções antigas e comparar ocupação.
 */
class PlanogramGenerationRunController extends Controller
{
    public function __construct(
        private readonly GenerationRunPresenter $presenter,
    ) {}

    /**
     * Histórico de execuções da gôndola (mais recentes primeiro), sem os relatórios
     * completos — só o resumo que a listagem precisa.
     */
    public function index(string $gondola): JsonResponse
    {
        $runs = PlanogramGenerationRun::query()
            ->applied()
            ->where('gondola_id', $gondola)
            ->latest()
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (PlanogramGenerationRun $run) => $this->presenter->summary($run));

        return response()->json(['data' => $runs]);
    }

    /**
     * Detalhe de uma execução — inclui os relatórios completos (capacity/validation).
     */
    public function show(string $gondola, string $run): JsonResponse
    {
        $model = PlanogramGenerationRun::query()
            ->where('gondola_id', $gondola)
            ->findOrFail($run);

        return response()->json(['data' => $this->presenter->detail($model)]);
    }

    /**
     * Última execução da gôndola — o editor chama isto ao abrir para hidratar a
     * barra-resumo da geração, que antes vinha do flash do Inertia.
     *
     * Devolve `data: null` (200) quando a gôndola nunca foi gerada.
     */
    public function latest(string $gondola): JsonResponse
    {
        $run = PlanogramGenerationRun::query()
            ->applied()
            ->where('gondola_id', $gondola)
            ->latest()
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'data' => $run ? $this->presenter->detail($run) : null,
        ]);
    }

    /**
     * Sinaliza se a gôndola tem geração em andamento — usado pela UI para exibir
     * o estado "gerando..." ao reabrir o editor antes do job terminar.
     */
    public function pending(string $gondola): JsonResponse
    {
        $pending = PlanogramGenerationRun::query()
            ->applied()
            ->where('gondola_id', $gondola)
            ->whereIn('status', [GenerationRunStatus::Queued, GenerationRunStatus::Running])
            ->latest()
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'data' => $pending ? $this->presenter->summary($pending) : null,
        ]);
    }
}

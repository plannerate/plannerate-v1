<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\ExecutionDivergenceStatus;
use App\Enums\ExecutionDivergenceType;
use App\Enums\ExecutionEvidenceType;
use App\Http\Controllers\Controller;
use App\Models\WorkflowExecutionDivergence;
use App\Models\WorkflowExecutionEvidence;
use App\Models\WorkflowGondolaExecution;
use App\Services\WorkflowExecutionLayerService;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Mutações da camada de Execução em Loja acopladas à tela de print read-only.
 *
 * Todas as ações retornam `back()` para que o Inertia recarregue a tela de
 * print; a camada de execução então refaz o partial reload do payload opcional.
 */
class GondolaExecutionLayerController extends Controller
{
    use InteractsWithTenantContext;

    public function __construct(
        private readonly WorkflowExecutionLayerService $service,
    ) {}

    /**
     * Registra uma evidência (foto/arquivo) na execução.
     */
    public function storeEvidence(Request $request, WorkflowGondolaExecution $execution): RedirectResponse
    {
        $this->authorize('execute', $execution);

        $validated = $request->validate([
            'type' => ['required', Rule::enum(ExecutionEvidenceType::class)],
            'module_label' => ['nullable', 'string', 'max:255'],
            'product_id' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,heic,webp', 'max:10240'],
        ]);

        $this->service->addEvidence($execution, $request->user(), $request->file('file'), $validated);

        return back();
    }

    /**
     * Remove uma evidência da execução.
     */
    public function destroyEvidence(
        Request $request,
        WorkflowGondolaExecution $execution,
        WorkflowExecutionEvidence $evidence
    ): RedirectResponse {
        $this->authorize('execute', $execution);

        abort_if(
            (string) $evidence->workflow_gondola_execution_id !== (string) $execution->id,
            422,
            'A evidência não pertence a esta execução.'
        );

        $this->service->removeEvidence($execution, $evidence, $request->user());

        return back();
    }

    /**
     * Registra uma divergência na execução.
     */
    public function storeDivergence(Request $request, WorkflowGondolaExecution $execution): RedirectResponse
    {
        $this->authorize('execute', $execution);

        $validated = $request->validate([
            'type' => ['required', Rule::enum(ExecutionDivergenceType::class)],
            'module_label' => ['nullable', 'string', 'max:255'],
            'shelf_label' => ['nullable', 'string', 'max:255'],
            'position_label' => ['nullable', 'string', 'max:255'],
            'product_id' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,heic,webp', 'max:10240'],
        ]);

        $this->service->addDivergence(
            $execution,
            $request->user(),
            $validated,
            $request->file('photos', [])
        );

        return back();
    }

    /**
     * Atualiza o estado de uma divergência (justificar/resolver/analisar).
     */
    public function updateDivergence(
        Request $request,
        WorkflowGondolaExecution $execution,
        WorkflowExecutionDivergence $divergence
    ): RedirectResponse {
        $this->authorize('execute', $execution);

        abort_if(
            (string) $divergence->workflow_gondola_execution_id !== (string) $execution->id,
            422,
            'A divergência não pertence a esta execução.'
        );

        $validated = $request->validate([
            'status' => ['required', Rule::enum(ExecutionDivergenceStatus::class)],
            'resolution_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->service->updateDivergence($execution, $divergence, $request->user(), $validated);

        return back();
    }

    /**
     * Conclui a execução em loja (após validar evidências e divergências).
     */
    public function complete(Request $request, WorkflowGondolaExecution $execution): RedirectResponse
    {
        // A conclusão da Execução em Loja é do executor responsável (não exige
        // a permissão de gestor); o gate `execute` cobre responsável/permitido/gestor.
        $this->authorize('execute', $execution);

        $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        $this->service->complete($execution, $request->user(), $request->string('notes')->toString() ?: null);

        return back();
    }
}

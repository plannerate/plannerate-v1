<?php

namespace App\Http\Controllers\Tenant\Editor;

use App\Enums\WorkflowExecutionStatus;
use App\Models\Gondola as AppGondola;
use App\Models\WorkflowGondolaExecution;
use App\Support\Tenancy\InteractsWithTenantContext;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;

class EditorPlanogramController extends GondolaController
{
    use InteractsWithTenantContext;

    /**
     * Abre o editor visual da gôndola.
     *
     * Bloqueio por etapa: se a gôndola tiver uma execução de workflow ativa
     * cuja etapa esteja em modo somente leitura (access_mode = view), o editor
     * não é aberto — o usuário é redirecionado para a visualização em PDF.
     * Impede burlar o controle digitando a URL do editor diretamente.
     */
    public function edit(string $record)
    {
        $execution = WorkflowGondolaExecution::query()
            ->where('gondola_id', $record)
            ->where('status', WorkflowExecutionStatus::Active)
            ->with(['step:id,workflow_template_id,access_mode', 'step.template:id,access_mode'])
            ->orderByDesc('started_at')
            ->first();

        if ($execution !== null && ! $execution->allowsEditing()) {
            return redirect()->route('export.gondola.view', ['gondola' => $record]);
        }

        return parent::edit($record);
    }

    /**
     * Retorna App\Models\Gondola para que relações como generationOverrides estejam disponíveis.
     */
    protected function findGondolaOrFail(string $id): Gondola
    {
        $gondola = AppGondola::find($id);

        if (! $gondola) {
            abort(403);
        }

        return $gondola;
    }

    protected function getBackRoute(Gondola $gondola): string
    {
        return route('tenant.planograms.index', [
            'record' => $gondola->planogram_id,
        ], false);
    }
}

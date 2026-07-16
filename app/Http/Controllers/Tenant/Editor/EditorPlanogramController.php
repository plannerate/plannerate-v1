<?php

namespace App\Http\Controllers\Tenant\Editor;

use App\Enums\GondolaEditDecision;
use App\Models\Gondola as AppGondola;
use App\Support\Tenancy\InteractsWithTenantContext;
use App\Support\Workflow\GondolaEditGate;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;

class EditorPlanogramController extends GondolaController
{
    use InteractsWithTenantContext;

    /**
     * Abre o editor visual da gôndola.
     *
     * Controle de acesso (quando o módulo Kanban está ativo): só é possível
     * abrir o editor de uma gôndola iniciada e pelo próprio usuário que a
     * iniciou. Caso contrário, retorna 403 — impede burlar o controle digitando
     * a URL do editor diretamente. Exceção de UX: se a gôndola foi iniciada pelo
     * próprio usuário mas a etapa atual é somente-leitura (access_mode = view),
     * redireciona para a visualização em PDF em vez de negar.
     */
    public function edit(string $record)
    {
        $decision = app(GondolaEditGate::class)->decide(auth()->user(), $record);

        if ($decision === GondolaEditDecision::ReadOnlyStep) {
            return redirect()->route('export.gondola.view', ['gondola' => $record]);
        }

        abort_unless($decision->allowsEditing(), 403);

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

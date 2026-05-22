<?php

namespace App\Http\Controllers\Tenant\Editor;

use App\Models\Gondola as AppGondola;
use App\Support\Tenancy\InteractsWithTenantContext;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;

class EditorPlanogramController extends GondolaController
{
    use InteractsWithTenantContext;

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

    protected function getBackRoute(Gondola $gondola, ?string $subdomain = null): string
    {
        unset($subdomain);

        return route('tenant.planograms.index', [
            'record' => $gondola->planogram_id,
        ], false);
    }
}

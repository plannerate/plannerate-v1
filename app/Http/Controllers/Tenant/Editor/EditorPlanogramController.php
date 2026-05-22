<?php

namespace App\Http\Controllers\Tenant\Editor;

use App\Support\Tenancy\InteractsWithTenantContext;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;

class EditorPlanogramController extends GondolaController
{
    use InteractsWithTenantContext;

    protected function getBackRoute(Gondola $gondola, ?string $subdomain = null): string
    {
        unset($subdomain);

        return route('tenant.planograms.index', [
            'record' => $gondola->planogram_id,
        ], false);
    }
}

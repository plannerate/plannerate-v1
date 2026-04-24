<?php

namespace App\Http\Controllers\Tenant\Editor;
 
use App\Support\Tenancy\InteractsWithTenantContext;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController; 
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;

class EditorPlanogramController extends GondolaController
{
    use InteractsWithTenantContext;
 
    protected function getBackRoute(Gondola $gondola): string
    {
        return route('tenant.planograms.index', [
            'subdomain' => $this->tenantSubdomain(),
            'record' => $gondola->planogram_id], false);
    }
}

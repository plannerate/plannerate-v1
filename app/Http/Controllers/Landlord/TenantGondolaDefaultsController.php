<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\UpdateTenantGondolaDefaultsRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TenantGondolaDefaultsController extends Controller
{
    /**
     * Campos do padrão de gôndola editáveis pelo landlord.
     *
     * Espelham config('plannerate.defaults.gondola'), exceto campos
     * por-instância (gondolaName auto-gerado e notes).
     *
     * @var list<string>
     */
    private const STANDARD_FIELDS = [
        'location',
        'side',
        'scaleFactor',
        'flow',
        'height',
        'width',
        'numModules',
        'baseHeight',
        'baseWidth',
        'baseDepth',
        'rackWidth',
        'holeHeight',
        'holeWidth',
        'holeSpacing',
        'shelfHeight',
        'shelfWidth',
        'shelfDepth',
        'numShelves',
        'productType',
    ];

    /**
     * Renderiza o formulário do padrão de gôndola do tenant.
     */
    public function edit(Tenant $tenant): Response
    {
        $this->authorize('update', $tenant);

        $systemDefaults = $this->systemDefaults();
        $tenantGondola = is_array($tenant->settings['gondola'] ?? null) ? $tenant->settings['gondola'] : [];

        return Inertia::render('landlord/tenants/GondolaDefaults', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
            ],
            // Padrão de sistema com o padrão salvo do tenant por cima.
            'defaults' => array_merge($systemDefaults, array_intersect_key($tenantGondola, $systemDefaults)),
            // Padrão do Plannerate puro — alimenta o botão "Restaurar padrão".
            'system_defaults' => $systemDefaults,
        ]);
    }

    /**
     * Persiste o padrão de gôndola no settings JSON do tenant, preservando
     * outras chaves de settings.
     */
    public function update(UpdateTenantGondolaDefaultsRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $settings['gondola'] = $request->validated();

        $tenant->update(['settings' => $settings]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.tenants.gondola_defaults.messages.updated'),
        ]);

        return back();
    }

    /**
     * Padrão de gôndola do Plannerate, limitado aos campos editáveis.
     *
     * @return array<string, mixed>
     */
    private function systemDefaults(): array
    {
        $config = (array) config('plannerate.defaults.gondola', []);

        return array_intersect_key($config, array_flip(self::STANDARD_FIELDS));
    }
}

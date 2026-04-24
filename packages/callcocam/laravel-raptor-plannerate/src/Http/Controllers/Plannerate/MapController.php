<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Plannerate;

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Concerns\HasPlanogramTabs;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Store;
use Illuminate\Http\Request;
use Inertia\Response;

class MapController extends Controller
{
    use HasPlanogramTabs;

    /**
     * Display maps view with stores and gondolas
     */
    public function index(Request $request): Response
    {
        $canEditStore = (bool) $request->user()?->can('tenant.stores.edit');

        // Carrega lojas com mapas
        $stores = Store::query()
            ->where('status', 'published')
            ->whereNotNull('map_image_path')
            ->when(config('app.current_client_id'), function ($query) {
                $query->where('client_id', config('app.current_client_id'));
            })
            ->with(['address', 'client'])
            ->get();

        // Processa os dados das lojas e enriquece com informações das gôndolas
        $storesWithGondolas = $stores->map(function (Store $store) use ($canEditStore) {
            // Primeiro, processa as regiões para adicionar gôndolas
            $processedRegions = null;
            if ($store->map_regions && is_array($store->map_regions)) {
                $processedRegions = collect($store->map_regions)->map(function ($region) {
                    // Busca a gôndola vinculada através do linked_map_gondola_id
                    $gondola = Gondola::with('planogram')->where('linked_map_gondola_id', $region['id'])->first();
                    if ($gondola) {
                        $region['gondola'] = [
                            'id' => $gondola->id,
                            'name' => $gondola->name,
                            'slug' => $gondola->slug,
                            'planogram_id' => $gondola->planogram_id,
                            'planogram_name' => $gondola->planogram?->name,
                            'edit_url' => route('tenant.plannerates.editor.gondolas.edit', [
                                'planogram' => $gondola->planogram_id,
                                'record' => $gondola->id,
                            ]),
                        ];
                    }

                    return $region;
                })->toArray();
            }

            // Agora converte para array e sobrescreve com as regiões processadas
            $storeData = $store->toArray();

            if ($processedRegions) {
                $storeData['map_regions'] = $processedRegions;

                // IMPORTANTE: Sobrescreve o maps_integration.regions com as regiões processadas
                if (isset($storeData['maps_integration'])) {
                    $storeData['maps_integration']['regions'] = $processedRegions;
                }
            }

            $storeData['can_edit_store'] = $canEditStore;

            return $storeData;
        });

        // Breadcrumbs
        $breadcrumbs = [
            [
                'label' => 'Painel de controle',
                'url' => route('dashboard', [], false),
            ],
            [
                'label' => 'Planogramas',
                'url' => route('tenant.planograms.index', [], false),
            ],
            [
                'label' => 'Mapas',
                'url' => null,
            ],
        ];

        return inertia('admin/tenant/plannerates/maps/index', [
            'stores' => $storesWithGondolas,
            'message' => 'Visualização de Mapas das Lojas',
            'resourceName' => 'map',
            'resourcePluralName' => 'maps',
            'resourceLabel' => 'Mapa',
            'resourcePluralLabel' => 'Mapas',
            'maxWidth' => 'full',
            'breadcrumbs' => $breadcrumbs,
            'tabs' => $this->planogramTabs('maps'),
        ]);
    }
}

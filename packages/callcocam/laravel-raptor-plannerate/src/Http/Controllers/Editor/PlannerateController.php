<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor;

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Store;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlannerateController extends Controller
{
    public function show(Request $request, $record): Response
    {
        $record = Planogram::findOrFail($record);

        // Carrega apenas a estrutura das gôndolas sem produtos (otimizado)
        $record->load(['gondolas.sections.shelves']);

        // Converte para array e adiciona store (cross-database)
        $recordArray = $record->toArray();

        // Carrega dados do mapa da store se existir (está no banco landlord)
        if ($record->store_id) {
            $store = Store::find($record->store_id);
            if ($store) {
                $recordArray['store'] = [
                    'id' => $store->id,
                    'name' => $store->name,
                    'map_image_path' => $store->map_image_path,
                    'map_regions' => $store->map_regions,
                ];
            }
        }

        // Monta filtros com planogram_id fixo
        $filters = $this->buildFilters($request, $record->id);

        return Inertia::render('tenant/plannerates/index', [
            'filters' => $filters,
            'record' => $recordArray,
            'users' => User::select('id', 'name')->get(),
            'breadcrumbs' => $this->buildBreadcrumbs($record),
        ]);
    }

    /**
     * Constrói os filtros a partir da requisição.
     */
    protected function buildFilters(Request $request, string $planogramId): array
    {
        return [
            'planogram_id' => $planogramId,
            'loja_id' => $request->input('loja_id'),
            'user_id' => $request->input('user_id'),
            'status' => $request->input('status'),
        ];
    }

    /**
     * Constrói o breadcrumb para navegação.
     */
    protected function buildBreadcrumbs(Planogram $record): array
    {
        return [
            [
                'label' => 'Painel de controle',
                'url' => route('dashboard', [], false),
            ],
            [
                'label' => 'Planogramas',
                'url' => route('tenant.planograms.index', [], false),
            ],
            [
                'label' => $record->name,
                'url' => null,
            ],
        ];
    }
}

<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor;

use App\Models\Tenant;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PlanogramApiController extends Controller
{
    /**
     * Lista todos os planogramas do tenant atual
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = Tenant::current()?->getKey();

        if (! is_string($tenantId) || $tenantId === '') {
            return response()->json([
                'data' => [],
            ]);
        }

        $query = Planogram::query()
            ->where('tenant_id', $tenantId)
            ->select('id', 'name', 'description', 'status')
            ->orderBy('name');

        $planograms = $query->get();

        return response()->json([
            'data' => $planograms,
        ]);
    }

    /**
     * Lista todas as gôndolas de um planograma
     */
    public function gondolas(Request $request, string $planogramId): JsonResponse
    {
        $tenantId = Tenant::current()?->getKey();

        if (! is_string($tenantId) || $tenantId === '') {
            return response()->json([
                'data' => [],
            ]);
        }

        $gondolas = Gondola::query()
            ->where('tenant_id', $tenantId)
            ->where('planogram_id', $planogramId)
            ->select('id', 'name', 'planogram_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $gondolas,
        ]);
    }
}

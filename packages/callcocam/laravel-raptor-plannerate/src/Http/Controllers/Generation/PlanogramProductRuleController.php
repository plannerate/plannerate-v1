<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation;

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramProductRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanogramProductRuleController extends Controller
{
    /**
     * Lista todas as regras do tenant (mandatory + blocked).
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;

        $rules = PlanogramProductRule::where('tenant_id', $tenantId)
            ->with('product:id,name,ean', 'subcategory:id,name')
            ->orderBy('type')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (PlanogramProductRule $rule) => [
                'id' => $rule->id,
                'type' => $rule->type->value,
                'type_label' => $rule->type->label(),
                'product_id' => $rule->product_id,
                'product_name' => $rule->product?->name,
                'product_ean' => $rule->product?->ean,
                'brand' => $rule->brand,
                'subcategory_id' => $rule->subcategory_id,
                'subcategory_name' => $rule->subcategory?->name,
                'reason' => $rule->reason,
                'created_at' => $rule->created_at?->toDateTimeString(),
            ]);

        return response()->json($rules);
    }

    /**
     * Cria uma nova regra (mandatory ou blocked) para o tenant.
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:mandatory,blocked'],
            'product_id' => ['nullable', 'string', 'max:26'],
            'brand' => ['nullable', 'string', 'max:255'],
            'subcategory_id' => ['nullable', 'string', 'max:26'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if (empty($validated['product_id']) && empty($validated['brand']) && empty($validated['subcategory_id'])) {
            return response()->json([
                'message' => 'Informe pelo menos um alvo: produto, marca ou subcategoria.',
            ], 422);
        }

        $rule = PlanogramProductRule::create([
            'tenant_id' => $tenantId,
            ...$validated,
        ]);

        return response()->json(['id' => $rule->id], 201);
    }

    /**
     * Remove uma regra existente.
     */
    public function destroy(PlanogramProductRule $planogramProductRule): JsonResponse
    {
        $planogramProductRule->delete();

        return response()->json(null, 204);
    }
}

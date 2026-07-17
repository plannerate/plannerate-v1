<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantDimensionShareToken;
use App\Services\DimensionShare\IssueDimensionShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Emissão e revogação de links públicos de correção de dimensões, disparados a
 * partir da página autenticada /dimensions.
 */
class DimensionShareTokenController extends Controller
{
    public function __construct(private readonly IssueDimensionShareService $service) {}

    /**
     * Gera um novo link público. Responde JSON (consumido via useHttp no diálogo),
     * pois o front precisa da URL de volta sem navegar.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('update', new Product);

        $validated = $request->validate([
            'category_id' => ['nullable', 'string'],
        ]);

        $tenant = Tenant::current();

        if (! $tenant instanceof Tenant) {
            abort(404);
        }

        $categoryId = $validated['category_id'] ?? null;
        $categoryId = is_string($categoryId) && $categoryId !== '' ? $categoryId : null;

        $categoryName = $categoryId !== null
            ? Category::query()->whereKey($categoryId)->value('name')
            : null;

        ['token' => $token, 'shareUrl' => $shareUrl] = $this->service->issue(
            $tenant,
            $categoryId,
            is_string($categoryName) ? $categoryName : null,
            $request->user(),
            $request,
        );

        return response()->json([
            'token_id' => $token->id,
            'url' => $shareUrl,
            'category_name' => $token->category_name,
            'expires_at' => $token->expires_at?->toIso8601String(),
        ]);
    }

    /**
     * Revoga um link existente do tenant atual.
     */
    public function destroy(Request $request, string $token): JsonResponse
    {
        $this->authorize('update', new Product);

        $tenant = Tenant::current();

        if (! $tenant instanceof Tenant) {
            abort(404);
        }

        $model = TenantDimensionShareToken::query()
            ->whereKey($token)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $model->revoke();

        return response()->json(['ok' => true]);
    }
}

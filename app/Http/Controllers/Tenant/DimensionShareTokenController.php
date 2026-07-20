<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreDimensionShareTokenRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantDimensionShareToken;
use App\Services\DimensionShare\DimensionShareScope;
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
    public function store(StoreDimensionShareTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $tenant = Tenant::current();

        if (! $tenant instanceof Tenant) {
            abort(404);
        }

        $categoryId = $this->nameOf($validated['category_id'] ?? null);

        // O nome é denormalizado só para rotular o escopo na UI — o link e a página
        // pública resolvem tudo pelo ID.
        $scope = DimensionShareScope::make(
            categoryId: $categoryId,
            categoryName: $categoryId !== null
                ? $this->nameOf(Category::query()->whereKey($categoryId)->value('name'))
                : null,
        );

        ['token' => $token, 'shareUrl' => $shareUrl] = $this->service->issue(
            $tenant,
            $scope,
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

    private function nameOf(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
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

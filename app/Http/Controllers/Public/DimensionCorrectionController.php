<?php

namespace App\Http\Controllers\Public;

use App\Enums\DimensionStatus;
use App\Http\Controllers\Concerns\InteractsWithCategoryFilter;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ValidateDimensionShareToken;
use App\Http\Requests\Public\UpdatePublicDimensionRequest;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantDimensionShareToken;
use App\Services\DimensionShare\EanReferenceBackfiller;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página pública (sem login) de correção de dimensões de produtos, acessada via
 * link com token. Lista apenas os produtos sem dimensão do escopo do token e
 * permite preencher altura/largura/profundidade de cada um.
 */
class DimensionCorrectionController extends Controller
{
    use InteractsWithCategoryFilter;

    /**
     * Quantidade de produtos por lote (paginação por cursor, à prova de "drift"
     * quando itens saem da lista ao serem salvos).
     */
    private const BATCH = 20;

    public function show(Request $request, string $code): Response
    {
        $token = $this->token($request);
        $tenant = Tenant::current();

        $after = trim((string) $request->query('after', ''));

        $items = $this->missingProductsQuery($token)
            ->orderBy('id')
            ->when($after !== '', fn (Builder $query) => $query->where('id', '>', $after))
            ->limit(self::BATCH + 1)
            ->get(['id', 'ean', 'name', 'codigo_erp', 'width', 'height', 'depth']);

        $hasMore = $items->count() > self::BATCH;
        $items = $items->take(self::BATCH);

        return Inertia::render('public/DimensionCorrection', [
            'code' => $code,
            'tenantName' => $tenant?->name,
            'categoryLabel' => $token->category_name,
            'products' => $items->map(fn (Product $product): array => [
                'id' => $product->id,
                'ean' => $product->ean,
                'name' => $product->name,
                'codigo_erp' => $product->codigo_erp,
                // Valores atuais (pré-preenchem o formulário para não re-digitar/sobrescrever).
                'width' => $product->width,
                'height' => $product->height,
                'depth' => $product->depth,
            ])->values(),
            'nextCursor' => $hasMore ? $items->last()?->id : null,
            'totalRemaining' => $this->missingProductsQuery($token)->count(),
        ]);
    }

    public function update(UpdatePublicDimensionRequest $request, string $code, string $product, EanReferenceBackfiller $backfiller): JsonResponse
    {
        $this->token($request);

        $model = Product::query()->whereKey($product)->firstOrFail();

        $validated = $request->validated();

        $width = (float) $validated['width'];
        $height = (float) $validated['height'];
        $depth = (float) $validated['depth'];

        $model->update([
            'width' => $width,
            'height' => $height,
            'depth' => $depth,
            'unit' => 'cm',
            'has_dimensions' => true,
            'dimension_publish_status' => 'published',
            'dimension_status' => DimensionStatus::Approved,
            'dimension_source' => 'manual_share',
        ]);

        // Aproveita a medida do cliente para popular a referência de EAN, se estiver vazia.
        // Best-effort: uma falha aqui não deve derrubar o salvamento do produto.
        try {
            $backfiller->fillIfEmpty($model->ean, $width, $height, $depth);
        } catch (\Throwable $exception) {
            Log::warning('Correção de dimensões: falha ao popular EanReference', [
                'product_id' => $model->id,
                'ean' => $model->ean,
                'error' => $exception->getMessage(),
            ]);
        }

        return response()->json(['ok' => true, 'id' => $model->id]);
    }

    /**
     * Query base dos produtos sem dimensão válida dentro do escopo (categoria) do token.
     *
     * Considera "sem dimensão" todo produto cuja ALTURA ou LARGURA não seja um número
     * positivo (null ou <= 0) — os campos essenciais. Não exclui rascunhos: a intenção
     * é capturar todos os produtos que precisam de medida.
     */
    private function missingProductsQuery(TenantDimensionShareToken $token): Builder
    {
        $categoryIds = $this->categoryAndDescendantIds((string) ($token->category_id ?? ''));

        return Product::query()
            ->when($categoryIds !== [], fn (Builder $query) => $query->whereIn('category_id', $categoryIds))
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('height')->orWhere('height', '<=', 0)
                    ->orWhereNull('width')->orWhere('width', '<=', 0);
            });
    }

    private function token(Request $request): TenantDimensionShareToken
    {
        $token = $request->attributes->get(ValidateDimensionShareToken::TOKEN_ATTRIBUTE);

        if (! $token instanceof TenantDimensionShareToken) {
            abort(403);
        }

        return $token;
    }
}

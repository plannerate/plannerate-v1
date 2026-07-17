<?php

namespace App\Http\Controllers\Tenant;

use App\Contracts\ProductImageAiEditor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\FetchRepositoryProductImageRequest;
use App\Http\Requests\Tenant\ProcessProductImageRequest;
use App\Http\Requests\Tenant\UploadProductImageRequest;
use App\Jobs\ProcessProductImageWithAiJob;
use App\Models\Product;
use App\Models\ProductImageAiOperation;
use App\Services\ProductImageStandardizer;
use App\Services\ProductRepositoryImageResolver;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageController extends Controller
{
    use InteractsWithTenantContext;

    public function __construct(
        protected ProductRepositoryImageResolver $repositoryImageResolver,
        protected ProductImageAiEditor $productImageAiEditor,
        protected ProductImageStandardizer $imageStandardizer
    ) {}

    public function upload(UploadProductImageRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $file = $request->file('file');
        $productId = trim((string) $request->input('product_id'));
        $product = null;

        if ($productId !== '') {
            $product = Product::query()
                ->whereKey($productId)
                ->where('tenant_id', $tenantId)
                ->first();

            if (! $product) {
                return response()->json([
                    'message' => __('Produto não encontrado para este tenant.'),
                ], 404);
            }

            $this->authorize('update', $product);
        }

        $binary = (string) $file->getContent();
        $identifier = $product?->id ?? (string) Str::ulid();

        // Regra 1: com o disco S3 (do) disponível, arquiva o arquivo original.
        $originalUrl = null;
        if ($this->doDiskAvailable()) {
            $extension = $file->extension() ?: $file->getClientOriginalExtension() ?: 'bin';
            $originalPath = "products/uploads/original/{$tenantId}/{$identifier}.{$extension}";
            Storage::disk('do')->put($originalPath, $binary);
            $originalUrl = Storage::disk('do')->url($originalPath);
        }

        // Regra 2: cópia local padronizada (WebP ≤ teto) — é a servida na gôndola.
        $width = ($product && is_numeric($product->width)) ? (float) $product->width : null;
        $height = ($product && is_numeric($product->height)) ? (float) $product->height : null;
        $standardized = $this->imageStandardizer->encode($binary, $width, $height);

        $publicPath = "products/uploads/{$tenantId}/{$identifier}.webp";
        Storage::disk('public')->put($publicPath, $standardized);

        if ($product) {
            $product->update([
                'url' => $publicPath,
            ]);
        }

        return response()->json([
            'path' => $publicPath,
            'public_url' => Storage::disk('public')->url($publicPath),
            'original_url' => $originalUrl,
        ]);
    }

    /**
     * Detecta se o disco S3 (do) está acessível — mesmo probe usado pelo
     * resolver de repositório. Uma falha de credencial/rede lança e cai no
     * catch, então só arquivamos o original quando o disco responde.
     */
    protected function doDiskAvailable(): bool
    {
        try {
            Storage::disk('do')->exists('__probe__');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function destroy(string $product): JsonResponse
    {
        $tenantId = (string) $this->tenantId();
        $productModel = Product::query()
            ->whereKey($product)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $productModel) {
            return response()->json([
                'message' => __('Produto não encontrado para este tenant.'),
            ], 404);
        }

        $this->authorize('update', $productModel);

        $productModel->update([
            'url' => null,
        ]);

        return response()->json([
            'message' => __('Imagem removida com sucesso.'),
            'product_id' => (string) $productModel->id,
        ]);
    }

    public function process(ProcessProductImageRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $path = (string) $request->string('path');
        $allowedPrefixes = [
            "products/uploads/{$tenantId}/",
            'repositorioimagens/frente/',
            'repositorioimages/frente/',
        ];

        $isAllowedPath = collect($allowedPrefixes)
            ->contains(fn (string $prefix): bool => Str::startsWith($path, $prefix));

        if (! $isAllowedPath || ! Storage::disk('public')->exists($path)) {
            return response()->json([
                'message' => __('app.tenant.products.form.image_ai.invalid_source'),
            ], 422);
        }

        $operation = ProductImageAiOperation::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $request->user()?->getAuthIdentifier(),
            'source_path' => $path,
            'status' => 'queued',
        ]);

        ProcessProductImageWithAiJob::dispatch($operation->id);

        return response()->json([
            'id' => $operation->id,
            'status' => $operation->status,
        ], 202);
    }

    public function status(Request $request, string $operation): JsonResponse
    {
        $operationId = trim($operation);
        $operation = ProductImageAiOperation::query()
            ->where('id', $operationId)
            ->orWhereRaw('LOWER(id) = ?', [Str::lower($operationId)])
            ->first();

        if (! $operation) {
            return response()->json([
                'id' => $operationId,
                'status' => 'queued',
                'path' => null,
                'public_url' => null,
                'error_message' => null,
                'can_retry' => false,
                'is_owner' => true,
            ]);
        }

        if (Str::lower((string) $operation->tenant_id) !== Str::lower((string) $this->tenantId())) {
            abort(404);
        }

        $path = $operation->output_path ?? $operation->source_path;

        return response()->json([
            'id' => $operation->id,
            'status' => $operation->status,
            'path' => $path,
            'public_url' => $path !== '' ? Storage::disk('public')->url($path) : null,
            'error_message' => $operation->error_message,
            'can_retry' => $operation->status === 'failed',
            'is_owner' => $operation->user_id === $request->user()?->getAuthIdentifier(),
        ]);
    }

    public function fetchFromRepository(FetchRepositoryProductImageRequest $request): JsonResponse
    {
        $ean = (string) $request->string('ean');
        $processWithAi = $request->boolean('process_with_ai');
        $result = $this->repositoryImageResolver->resolveByEan($ean);
        $resolutionDebug = $this->repositoryImageResolver->lastResolutionDebug();

        if ($result === null) {
            Log::warning('ProductImageController.fetchFromRepository: imagem nao encontrada', [
                'tenant_id' => $this->tenantId(),
                'user_id' => $request->user()?->getAuthIdentifier(),
                'ean' => $ean,
                'resolution_debug' => $resolutionDebug,
            ]);

            return response()->json([
                'message' => __('app.tenant.products.form.image_repository.not_found'),
                'debug' => $resolutionDebug,
            ], 404);
        }

        if ($processWithAi && isset($result['path']) && is_string($result['path'])) {
            try {
                $processedTargetPath = sprintf(
                    'products/processed/%s/%s-%s.webp',
                    $this->tenantId(),
                    $ean !== '' ? $ean : 'ean',
                    Str::lower((string) Str::ulid()),
                );
                $processedPath = $this->productImageAiEditor->process($result['path'], $processedTargetPath);
                $result['path'] = $processedPath;
                $result['public_url'] = Storage::disk('public')->url($processedPath);
                $result['ai_processed'] = true;
            } catch (\Throwable $exception) {
                Log::warning('ProductImageController.fetchFromRepository: falha ao processar imagem com IA', [
                    'tenant_id' => $this->tenantId(),
                    'user_id' => $request->user()?->getAuthIdentifier(),
                    'ean' => $ean,
                    'path' => $result['path'],
                    'error' => $exception->getMessage(),
                ]);
                $result['ai_processed'] = false;
                $result['ai_error'] = $exception->getMessage();
            }
        }

        return response()->json($result);
    }
}

<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ProcessProductImageRequest;
use App\Http\Requests\Tenant\UploadProductImageRequest;
use App\Jobs\ProcessProductImageWithAiJob;
use App\Models\ProductImageAiOperation;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageController extends Controller
{
    use InteractsWithTenantContext;

    public function upload(UploadProductImageRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $file = $request->file('file');
        $path = $file->store("products/uploads/{$tenantId}", 'public');

        return response()->json([
            'path' => $path,
            'public_url' => Storage::disk('public')->url($path),
        ]);
    }

    public function process(ProcessProductImageRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $path = (string) $request->string('path');
        $expectedPrefix = "products/uploads/{$tenantId}/";

        if (! Str::startsWith($path, $expectedPrefix) || ! Storage::disk('public')->exists($path)) {
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

    public function status(Request $request, ProductImageAiOperation $operation): JsonResponse
    {
        if ($operation->tenant_id !== $this->tenantId()) {
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
}

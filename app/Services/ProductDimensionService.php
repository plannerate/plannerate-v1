<?php

namespace App\Services;

use App\Enums\DimensionStatus;
use App\Jobs\ResearchProductDimensionsJob;
use App\Models\Product;
use App\Models\User;

class ProductDimensionService
{
    public function research(Product $product): void
    {
        $tenantId = (string) ($product->tenant_id ?? '');

        $product->update(['dimension_status' => DimensionStatus::Pending]);

        ResearchProductDimensionsJob::dispatch($product->id, $tenantId);
    }

    public function approve(Product $product, User $user): void
    {
        $product->update([
            'dimension_status' => DimensionStatus::Approved,
            'dimension_approved_by' => $user->id,
            'dimension_approved_at' => now(),
        ]);
    }

    public function reject(Product $product, User $user, string $reason): void
    {
        $warnings = (array) ($product->dimension_warnings ?? []);
        $warnings[] = "Rejeitado por {$user->name}: {$reason}";

        $product->update([
            'dimension_status' => DimensionStatus::Rejected,
            'dimension_warnings' => $warnings,
            'dimension_reasoning' => $reason,
        ]);
    }

    public function dispatchPendingBatch(int $limit = 50): int
    {
        $products = Product::needingResearch()
            ->whereNotNull('tenant_id')
            ->select(['id', 'tenant_id'])
            ->limit($limit)
            ->get();

        foreach ($products as $product) {
            ResearchProductDimensionsJob::dispatch($product->id, (string) $product->tenant_id);
        }

        return $products->count();
    }
}

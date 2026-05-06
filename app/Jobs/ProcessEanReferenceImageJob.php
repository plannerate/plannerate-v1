<?php

namespace App\Jobs;

use App\Models\EanReference;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\ProductRepositoryImageResolver;
use App\Support\Modules\ModuleSlug;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class ProcessEanReferenceImageJob implements NotTenantAware, ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $tenantIds
     */
    public function __construct(
        public string $eanReferenceId,
        public bool $force = false,
        public array $tenantIds = []
    ) {}

    public function handle(ProductRepositoryImageResolver $imageResolver): void
    {
        $reference = EanReference::query()->find($this->eanReferenceId);

        if (! $reference) {
            return;
        }

        $normalizedEan = EanReference::normalizeEan((string) $reference->ean);
        if ($normalizedEan === '') {
            return;
        }

        $result = $imageResolver->resolveByEan($normalizedEan, force: $this->force);
        $path = is_array($result) ? ($result['path'] ?? null) : null;

        if (! is_string($path) || $path === '') {
            return;
        }

        $reference->image_front_url = $path;
        $reference->save();

        $tenants = Tenant::query()
            ->where('status', 'active')
            ->whereHasActiveModule(ModuleSlug::IMAGE_BANK)
            ->whereNotNull('database')
            ->where('database', '!=', '')
            ->when($this->tenantIds !== [], fn ($query) => $query->whereIn('id', $this->tenantIds))
            ->get(['id', 'name', 'database']);

        $totalProductsUpdated = 0;

        foreach ($tenants as $tenant) {
            $tenantName = (string) ($tenant->name ?? $tenant->id);
            $updatedForTenant = $tenant->execute(function () use ($normalizedEan, $path): int {
                $updated = Product::query()
                    ->where('ean', $normalizedEan)
                    ->where(function ($query) use ($path): void {
                        $query->whereNull('url')->orWhere('url', '!=', $path);
                    })
                    ->update(['url' => $path, 'updated_at' => now()]);

                if ($updated > 0) {
                    return $updated;
                }

                $fallbackUpdated = 0;

                Product::query()
                    ->whereNotNull('ean')
                    ->where(function ($query) use ($path): void {
                        $query->whereNull('url')->orWhere('url', '!=', $path);
                    })
                    ->select(['id', 'ean'])
                    ->chunkById(1000, function ($products) use ($normalizedEan, $path, &$fallbackUpdated): void {
                        $idsToUpdate = $products
                            ->filter(fn (Product $product): bool => EanReference::normalizeEan((string) $product->ean) === $normalizedEan)
                            ->pluck('id')
                            ->all();

                        if ($idsToUpdate !== []) {
                            $fallbackUpdated += DB::table('products')
                                ->whereIn('id', $idsToUpdate)
                                ->update(['url' => $path, 'updated_at' => now()]);
                        }
                    });

                return $fallbackUpdated;
            });

            $updatedForTenantCount = is_int($updatedForTenant) ? $updatedForTenant : 0;
            $totalProductsUpdated += $updatedForTenantCount;

            Log::info('ProcessEanReferenceImageJob tenant sync', [
                'ean_reference_id' => $this->eanReferenceId,
                'ean' => $normalizedEan,
                'tenant_id' => (string) $tenant->id,
                'tenant_name' => $tenantName,
                'updated_count' => $updatedForTenantCount,
            ]);
        }

        Log::info('ProcessEanReferenceImageJob completed', [
            'ean_reference_id' => $this->eanReferenceId,
            'ean' => $normalizedEan,
            'path' => $path,
            'tenants_count' => $tenants->count(),
            'updated_total' => $totalProductsUpdated,
            'force' => $this->force,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessEanReferenceImageJob falhou', [
            'ean_reference_id' => $this->eanReferenceId,
            'force' => $this->force,
            'error' => $exception->getMessage(),
        ]);
    }
}

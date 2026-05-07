<?php

namespace App\Services;

use App\Models\EanReference;
use App\Models\Product;
use App\Models\Tenant;
use App\Support\Modules\ModuleSlug;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EanReferenceImageSyncService
{
    /**
     * Sync a single EAN's image path to tenant products.
     * Used by ProcessEanReferenceImageJob after downloading an image.
     *
     * When $onlyEmpty is false, updates products with null OR different url.
     * When $onlyEmpty is true, updates only products with null url.
     *
     * @param  list<string>  $tenantIds  Empty = all IMAGE_BANK active tenants.
     */
    public function syncOne(string $normalizedEan, string $path, array $tenantIds = [], bool $onlyEmpty = false): int
    {
        $tenants = $this->resolveTenants($tenantIds);
        $total = 0;

        foreach ($tenants as $tenant) {
            $updated = $tenant->execute(fn (): int => $this->syncOneForTenant($normalizedEan, $path, $onlyEmpty));
            $total += is_int($updated) ? $updated : 0;
        }

        return $total;
    }

    /**
     * Bulk sync: propagate image_front_url from all EanReferences (with image set) to tenant
     * products with empty url. Optimized for mass operations — loads the full EAN map once
     * and processes per-tenant in chunks instead of querying per EAN per tenant.
     *
     * Complexity: O(tenants × products_with_null_url / chunk_size) queries.
     *
     * @param  list<string>  $tenantIds  Empty = all IMAGE_BANK active tenants.
     * @param  callable(string $ean, int $updated): void  $onProgress
     */
    public function syncAll(array $tenantIds = [], ?callable $onProgress = null): int
    {
        $tenants = $this->resolveTenants($tenantIds);

        if ($tenants->isEmpty()) {
            return 0;
        }

        $eanMap = $this->buildEanMap();

        if ($eanMap === []) {
            return 0;
        }

        $total = 0;

        foreach ($tenants as $tenant) {
            $updated = $tenant->execute(fn (): int => $this->syncAllForTenant($eanMap, $onProgress));
            $total += is_int($updated) ? $updated : 0;
        }

        return $total;
    }

    /**
     * Single EAN sync for one tenant. Tries direct match first, then falls back to
     * normalized EAN comparison via chunk (handles EANs stored with dashes, spaces, etc.).
     */
    private function syncOneForTenant(string $normalizedEan, string $path, bool $onlyEmpty): int
    {
        $updated = Product::query()
            ->where('ean', $normalizedEan)
            ->when(
                $onlyEmpty,
                fn ($q) => $q->whereNull('url'),
                fn ($q) => $q->where(fn ($inner) => $inner->whereNull('url')->orWhere('url', '!=', $path)),
            )
            ->update(['url' => $path, 'updated_at' => now()]);

        if ($updated > 0) {
            return $updated;
        }

        $fallback = 0;

        Product::query()
            ->whereNotNull('ean')
            ->when(
                $onlyEmpty,
                fn ($q) => $q->whereNull('url'),
                fn ($q) => $q->where(fn ($inner) => $inner->whereNull('url')->orWhere('url', '!=', $path)),
            )
            ->select(['id', 'ean'])
            ->chunkById(1000, function ($products) use ($normalizedEan, $path, &$fallback): void {
                $ids = $products
                    ->filter(fn (Product $product): bool => EanReference::normalizeEan((string) $product->ean) === $normalizedEan)
                    ->pluck('id')
                    ->all();

                if ($ids !== []) {
                    $fallback += Product::query()
                        ->whereIn('id', $ids)
                        ->update(['url' => $path, 'updated_at' => now()]);
                }
            });

        return $fallback;
    }

    /**
     * Bulk sync for one tenant. Scans all products with null url in chunks, normalizes
     * their EAN in PHP, looks up the path in $eanMap, and bulk-updates grouped by path.
     *
     * @param  array<string, string>  $eanMap  normalizedEan => storagePath
     * @param  callable(string $ean, int $updated): void  $onProgress
     */
    private function syncAllForTenant(array $eanMap, ?callable $onProgress): int
    {
        $totalUpdated = 0;

        Product::query()
            ->whereNull('url')
            ->whereNotNull('ean')
            ->select(['id', 'ean'])
            ->chunkById(1000, function ($products) use ($eanMap, $onProgress, &$totalUpdated): void {
                // normalizedEan => list<id>
                $byEan = [];

                foreach ($products as $product) {
                    $normalizedEan = EanReference::normalizeEan((string) $product->ean);
                    if (isset($eanMap[$normalizedEan])) {
                        $byEan[$normalizedEan][] = $product->id;
                    }
                }

                foreach ($byEan as $normalizedEan => $ids) {
                    $path = $eanMap[$normalizedEan];

                    $count = Product::query()
                        ->whereIn('id', $ids)
                        ->update(['url' => $path, 'updated_at' => now()]);

                    $totalUpdated += $count;

                    if ($onProgress !== null && $count > 0) {
                        $onProgress($normalizedEan, $count);
                    }
                }
            });

        return $totalUpdated;
    }

    /**
     * Loads all EanReferences with image_front_url into a map keyed by normalized EAN.
     * Called once per syncAll invocation.
     *
     * @return array<string, string> normalizedEan => storagePath
     */
    private function buildEanMap(): array
    {
        return DB::connection('landlord')
            ->table('ean_references')
            ->whereNull('deleted_at')
            ->whereNotNull('image_front_url')
            ->where('image_front_url', '!=', '')
            ->select(['ean', 'image_front_url'])
            ->get()
            ->mapWithKeys(fn (object $ref): array => [
                EanReference::normalizeEan((string) $ref->ean) => (string) $ref->image_front_url,
            ])
            ->filter()
            ->all();
    }

    /**
     * @param  list<string>  $tenantIds
     * @return Collection<int, Tenant>
     */
    private function resolveTenants(array $tenantIds): Collection
    {
        return Tenant::query()
            ->where('status', 'active')
            ->whereHasActiveModule(ModuleSlug::IMAGE_BANK)
            ->whereNotNull('database')
            ->where('database', '!=', '')
            ->when($tenantIds !== [], fn ($q) => $q->whereIn('id', $tenantIds))
            ->get(['id', 'name', 'database']);
    }
}

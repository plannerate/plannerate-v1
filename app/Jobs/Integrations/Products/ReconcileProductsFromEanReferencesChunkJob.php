<?php

namespace App\Jobs\Integrations\Products;

use App\Models\EanReference;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class ReconcileProductsFromEanReferencesChunkJob implements NotTenantAware, ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 120;

    public int $tries = 8;

    public int $uniqueFor = 3600;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 15, 30, 60, 120];

    /**
     * @param  array<int, string>  $productIds
     */
    public function __construct(
        public string $tenantId,
        public array $productIds,
        public string $tenantConnectionName,
        public bool $executeInTenantContext = true,
    ) {}

    public function handle(): void
    {
        if ($this->tenantId === '' || $this->productIds === []) {
            return;
        }

        $tenant = Tenant::query()->whereKey($this->tenantId)->first();
        if (! $tenant) {
            return;
        }

        $run = function (): void {
            $connection = DB::connection($this->tenantConnectionName);
            $now = Carbon::now();

            $products = $connection->table('products')
                ->where('tenant_id', $this->tenantId)
                ->whereIn('id', $this->productIds)
                ->get([
                    'id',
                    'ean',
                    'category_id',
                    'description',
                    'brand',
                    'subbrand',
                    'packaging_type',
                    'packaging_size',
                    'measurement_unit',
                ]);

            $eanValues = $products
                ->pluck('ean')
                ->filter(fn (mixed $ean): bool => is_string($ean) && trim($ean) !== '')
                ->map(fn (string $ean): string => trim($ean))
                ->unique()
                ->values()
                ->all();

            if ($eanValues === []) {
                return;
            }

            $references = EanReference::query()
                ->where('tenant_id', $this->tenantId)
                ->whereIn('ean', $eanValues)
                ->get()
                ->keyBy('ean');

            foreach ($products as $product) {
                $ean = is_string($product->ean ?? null) ? trim($product->ean) : null;
                if ($ean === null || $ean === '') {
                    continue;
                }

                $reference = $references->get($ean);
                if (! $reference instanceof EanReference) {
                    continue;
                }

                $updates = [];
                foreach ([
                    'category_id' => $reference->category_id,
                    'description' => $reference->reference_description,
                    'brand' => $reference->brand,
                    'subbrand' => $reference->subbrand,
                    'packaging_type' => $reference->packaging_type,
                    'packaging_size' => $reference->packaging_size,
                    'measurement_unit' => $reference->measurement_unit,
                ] as $column => $value) {
                    if ($value !== null && ($product->{$column} ?? null) !== $value) {
                        $updates[$column] = $value;
                    }
                }

                if ($updates === []) {
                    continue;
                }

                $updates['updated_at'] = $now;

                $connection->table('products')
                    ->where('id', (string) $product->id)
                    ->update($updates);
            }
        };

        try {
            if ($this->executeInTenantContext) {
                $tenant->execute($run);

                return;
            }

            $run();
        } catch (QueryException $exception) {
            if ($this->isLockTimeout($exception)) {
                $this->release(20);

                return;
            }

            throw $exception;
        }
    }

    public function uniqueId(): string
    {
        $ids = $this->productIds;
        sort($ids);

        return sprintf(
            'tenant:%s:products:%s',
            $this->tenantId,
            md5(json_encode($ids) ?: ''),
        );
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('reconcile-products-'.$this->tenantId))
                ->releaseAfter(10)
                ->expireAfter(180),
        ];
    }

    private function isLockTimeout(QueryException $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return str_contains($message, 'lock wait timeout exceeded')
            || str_contains($message, 'deadlock found');
    }
}

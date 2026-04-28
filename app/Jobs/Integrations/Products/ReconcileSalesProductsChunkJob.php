<?php

namespace App\Jobs\Integrations\Products;

use App\Models\Tenant;
use App\Services\Integrations\Support\SyncSalesProductReferencesService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class ReconcileSalesProductsChunkJob implements NotTenantAware, ShouldBeUnique, ShouldQueue
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
     * @param  array<int, string>  $erpCodes
     */
    public function __construct(
        public string $tenantId,
        public array $erpCodes,
        public string $tenantConnectionName,
        public bool $executeInTenantContext = true,
    ) {}

    public function handle(
        SyncSalesProductReferencesService $syncSalesProductReferencesService,
    ): void {
        if ($this->tenantId === '' || $this->erpCodes === []) {
            return;
        }

        $tenant = Tenant::query()->whereKey($this->tenantId)->first();
        if (! $tenant) {
            return;
        }

        $run = function () use ($syncSalesProductReferencesService): void {
            $syncSalesProductReferencesService->syncByCodigoErp(
                tenantConnectionName: $this->tenantConnectionName,
                tenantId: $this->tenantId,
                erpCodes: $this->erpCodes,
                now: Carbon::now(),
            );
        };

        try {
            if ($this->executeInTenantContext) {
                $tenant->execute($run);

                return;
            }

            $run();
        } catch (QueryException $exception) {
            $message = mb_strtolower($exception->getMessage());
            if (str_contains($message, 'lock wait timeout exceeded') || str_contains($message, 'deadlock found')) {
                $this->release(20);

                return;
            }

            throw $exception;
        }
    }

    public function uniqueId(): string
    {
        $codes = $this->erpCodes;
        sort($codes);

        return sprintf(
            'tenant:%s:sales:%s',
            $this->tenantId,
            md5(json_encode($codes) ?: ''),
        );
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('reconcile-sales-'.$this->tenantId))
                ->releaseAfter(10)
                ->expireAfter(180),
        ];
    }
}

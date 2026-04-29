<?php

namespace App\Jobs\Cleanup;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class CleanupOldSalesJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 900;

    /**
     * @param  array<int, string>  $saleIds
     */
    public function __construct(
        public string $tenantId,
        public array $saleIds,
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if (! $tenant || $this->saleIds === []) {
            return;
        }

        $tenant->execute(function (): void {
            $connection = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));

            foreach (array_chunk($this->saleIds, 1000) as $chunk) {
                DB::connection($connection)
                    ->table('sales')
                    ->where('tenant_id', $this->tenantId)
                    ->whereIn('id', $chunk)
                    ->delete();
            }
        });
    }
}

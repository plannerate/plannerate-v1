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

class RestoreSoldProductsJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    /**
     * @param  array<int, string>  $productIds
     */
    public function __construct(
        public string $tenantId,
        public array $productIds,
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if (! $tenant || $this->productIds === []) {
            return;
        }

        $tenant->execute(function (): void {
            $connection = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));

            foreach (array_chunk($this->productIds, 500) as $chunk) {
                DB::connection($connection)
                    ->table('products')
                    ->where('tenant_id', $this->tenantId)
                    ->whereIn('id', $chunk)
                    ->whereNotNull('deleted_at')
                    ->update([
                        'deleted_at' => null,
                        'updated_at' => now(),
                    ]);
            }
        });
    }
}

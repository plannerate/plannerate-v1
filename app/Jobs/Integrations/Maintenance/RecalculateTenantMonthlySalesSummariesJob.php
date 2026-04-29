<?php

namespace App\Jobs\Integrations\Maintenance;

use App\Models\Tenant;
use App\Services\Integrations\Support\RecalculateMonthlySalesSummariesService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class RecalculateTenantMonthlySalesSummariesJob implements NotTenantAware, ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(
        public string $tenantId,
        public ?string $month = null,
    ) {}

    public function handle(RecalculateMonthlySalesSummariesService $recalculateMonthlySalesSummariesService): void
    {
        $tenant = Tenant::query()
            ->whereKey($this->tenantId)
            ->where('status', 'active')
            ->first();

        if (! $tenant) {
            return;
        }

        $summary = $recalculateMonthlySalesSummariesService->recalculate($tenant, $this->month);

        Log::info('Monthly sales summaries recalculated.', $summary);
    }
}

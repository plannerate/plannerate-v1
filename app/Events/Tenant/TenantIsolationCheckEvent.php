<?php

namespace App\Events\Tenant;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TenantIsolationCheckEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $currentTenantId,
        public string $tenantSlug,
        public string $resource,
        public string $testedAt,
        public string $status,
    ) {}

    /**
     * @return array<int, Channel|PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.'.$this->tenantId),
            new Channel('landlord.diagnostics'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'tenant.isolation.checked';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        Log::info('Broadcasting TenantIsolationCheckEvent', [
            'tenant_id' => $this->tenantId,
            'current_tenant_id' => $this->currentTenantId,
            'tenant_slug' => $this->tenantSlug,
            'resource' => $this->resource,
            'tested_at' => $this->testedAt,
            'status' => $this->status,
        ]);

        return [
            'tenant_id' => $this->tenantId,
            'current_tenant_id' => $this->currentTenantId,
            'tenant_slug' => $this->tenantSlug,
            'resource' => $this->resource,
            'tested_at' => $this->testedAt,
            'status' => $this->status,
        ];
    }
}

<?php

namespace App\Events\Tenant;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IntegrationProcessStarted implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $integrationId,
        public string $resource,
        public string $referenceDate,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.'.$this->tenantId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'integration.process.started';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        Log::info('Broadcasting IntegrationProcessStarted event', [
            'tenant_id' => $this->tenantId,
            'integration_id' => $this->integrationId,
            'resource' => $this->resource,
            'reference_date' => $this->referenceDate,
        ]);

        return [
            'tenant_id' => $this->tenantId,
            'integration_id' => $this->integrationId,
            'resource' => $this->resource,
            'reference_date' => $this->referenceDate,
        ];
    }
}

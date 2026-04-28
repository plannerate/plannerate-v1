<?php

use App\Events\Tenant\IntegrationProcessFinished;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

test('integration process finished event uses tenant private channel', function () {
    $event = new IntegrationProcessFinished(
        tenantId: 'tenant-123',
        integrationId: 'integration-123',
        resource: 'sales',
        referenceDate: '2026-02-10',
        status: 'success',
    );

    expect($event)
        ->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event->broadcastAs())->toBe('integration.process.finished');

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-tenant.tenant-123');
});

test('integration process finished event broadcasts snake case payload', function () {
    $event = new IntegrationProcessFinished(
        tenantId: 'tenant-123',
        integrationId: 'integration-123',
        resource: 'sales',
        referenceDate: '2026-02-10',
        status: 'failed',
        errorMessage: 'Falha de teste',
    );

    expect($event->broadcastWith())->toBe([
        'tenant_id' => 'tenant-123',
        'integration_id' => 'integration-123',
        'resource' => 'sales',
        'reference_date' => '2026-02-10',
        'status' => 'failed',
        'error_message' => 'Falha de teste',
    ]);
});

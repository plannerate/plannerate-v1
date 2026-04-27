<?php

use Callcocam\LaravelRaptorPlannerate\Events\GondolaProductImagesUpdated;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Tests\TestCase;

uses(TestCase::class);

test('gondola product images updated broadcasts to the requesting user channel', function (): void {
    $event = new GondolaProductImagesUpdated(
        userId: 'user-01',
        gondolaId: 'gondola-01',
        processedCount: 3,
    );

    expect($event)
        ->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event->broadcastAs())->toBe('plannerate.gondola.product-images.updated')
        ->and($event->broadcastWith())->toBe([
            'gondola_id' => 'gondola-01',
            'processed_count' => 3,
        ]);

    $channels = $event->broadcastOn();

    expect($channels)
        ->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-App.Models.User.user-01');
});

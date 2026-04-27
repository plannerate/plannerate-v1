<?php

namespace Callcocam\LaravelRaptorPlannerate\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GondolaProductImagesUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $userId,
        public string $gondolaId,
        public int $processedCount,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("App.Models.User.{$this->userId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'plannerate.gondola.product-images.updated';
    }

    /**
     * @return array{gondola_id: string, processed_count: int}
     */
    public function broadcastWith(): array
    {
        return [
            'gondola_id' => $this->gondolaId,
            'processed_count' => $this->processedCount,
        ];
    }
}

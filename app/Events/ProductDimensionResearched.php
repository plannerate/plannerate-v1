<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductDimensionResearched implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Product $product,
        public readonly string $tenantId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("tenant.{$this->tenantId}.dimensions");
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'dimension_status' => $this->product->dimension_status?->value,
            'dimension_source' => $this->product->dimension_source,
            'dimension_confidence' => $this->product->dimension_confidence,
        ];
    }

    public function broadcastAs(): string
    {
        return 'ProductDimensionResearched';
    }
}

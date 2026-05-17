<?php

namespace App\Events\Tenant;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProductImageProcessed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $productId,
        public readonly string $ean,
        public readonly ?string $imagePath,
        public readonly ?string $database = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        if ($this->tenantId === '') {
            return [];
        }

        return [
            new PrivateChannel('tenant.'.$this->tenantId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'product.image.processed';
    }

    /**
     * @return array<string, string|null>
     */
    public function broadcastWith(): array
    {
        return [
            'product_id' => $this->productId,
            'ean' => $this->ean,
            'image_path' => $this->imagePath,
            'image_url' => $this->imagePath !== null
                ? Storage::disk(config('filesystems.default'))->url($this->imagePath)
                : null,
        ];
    }
}

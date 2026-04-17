<?php

namespace App\Events\Sync;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SyncProgressEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public ?string $userId,
        public string $clientId,
        public string $clientName,
        public string $storeName,
        public string $type, // 'started', 'progress', 'completed', 'failed'
        public string $context, // 'sales' ou 'products'
        public ?string $date = null,
        public ?int $totalItems = null,
        public ?int $processedItems = null,
        public ?string $message = null,
    ) {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        // Se tem userId, envia para canal do usuário
        // Senão, envia para canal global do cliente (todos os usuários veem)
        $channel = $this->userId 
            ? "sync.user.{$this->userId}"
            : "sync.client.{$this->clientId}";
            
        return new Channel($channel);
    }

    /**
     * Nome do evento no frontend
     */
    public function broadcastAs(): string
    {
        return 'sync.progress';
    }

    /**
     * Dados transmitidos
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'client_id' => $this->clientId,
            'client_name' => $this->clientName,
            'store_name' => $this->storeName,
            'type' => $this->type,
            'context' => $this->context,
            'date' => $this->date,
            'total_items' => $this->totalItems,
            'processed_items' => $this->processedItems,
            'message' => $this->message,
            'timestamp' => now()->toISOString(),
        ];
    }
}

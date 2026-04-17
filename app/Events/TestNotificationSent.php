<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TestNotificationSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $message,
        public string $userId,
        public array $metadata = []
    ) {
        Log::info('[TestNotificationSent] Evento criado', [
            'user_id' => $this->userId,
            'message' => $this->message,
            'channel' => "user.{$this->userId}",
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channel = 'user.' . $this->userId;
        
        Log::info('[TestNotificationSent] Broadcast no canal', [
            'channel' => $channel,
            'user_id' => $this->userId,
            'event_name' => $this->broadcastAs(),
        ]);
        
        return [
            new PrivateChannel($channel),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'test.notification';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $data = [
            'message' => $this->message,
            'timestamp' => now()->toIso8601String(),
            'metadata' => $this->metadata,
        ];
        
        Log::info('[TestNotificationSent] Dados do broadcast', [
            'channel' => "user.{$this->userId}",
            'event' => $this->broadcastAs(),
            'data' => $data,
        ]);
        
        return $data;
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class TestGlobalNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $title,
        public string $message,
        public string $type = 'info'
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     * O ID da notificação será atribuído automaticamente pelo Laravel
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        // O Laravel automaticamente adiciona o 'id' da notificação ao BroadcastMessage
        return new BroadcastMessage([
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'data' => $this->toArray($notifiable),
            'created_at' => now()->toIso8601String(),
            'notification_type' => static::class,
        ]);
    }

    /**
     * Get the type of the notification being broadcast.
     * O Laravel adiciona um ponto no início, então 'notification.created' vira '.notification.created'
     */
    public function broadcastType(): string
    {
        return 'notification.created';
    }
}

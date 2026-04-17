<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificação de início do sync:sales (database + broadcast).
 * Enviada ao iniciar o SalesCommand.
 */
class SalesSyncStartedNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    public function __construct(
        public int $clientCount,
        public array $clientNames
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'client_count' => $this->clientCount,
            'client_names' => $this->clientNames,
            'title' => 'Sync Vendas iniciado',
            'message' => $this->buildMessage(),
            'type' => 'info',
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title' => 'Sync Vendas iniciado',
            'message' => $this->buildMessage(),
            'type' => 'info',
            'data' => [
                'client_count' => $this->clientCount,
                'client_names' => $this->clientNames,
            ],
            'created_at' => now()->toIso8601String(),
            'notification_type' => static::class,
        ]);
    }

    public function broadcastType(): string
    {
        return 'notification.created';
    }

    private function buildMessage(): string
    {
        if ($this->clientCount === 0) {
            return 'Nenhum cliente para sincronizar.';
        }
        if ($this->clientCount === 1 && ! empty($this->clientNames)) {
            return 'Sincronizando vendas para 1 cliente: '.$this->clientNames[0];
        }

        $names = implode(', ', array_slice($this->clientNames, 0, 5));
        if (count($this->clientNames) > 5) {
            $names .= ' e mais '.(count($this->clientNames) - 5).' cliente(s)';
        }

        return "Sincronizando vendas para {$this->clientCount} cliente(s): {$names}";
    }
}

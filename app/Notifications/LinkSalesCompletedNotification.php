<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificação de conclusão do sync:link-sales (database + broadcast).
 * Enviada ao finalizar o LinkSalesProductsCommand.
 */
class LinkSalesCompletedNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    /**
     * @param  array<int, array{client_name: string, linked: int, remaining: int}>  $results
     */
    public function __construct(
        public bool $preview,
        public array $results,
        public int $totalClients
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
            'preview' => $this->preview,
            'results' => $this->results,
            'total_clients' => $this->totalClients,
            'title' => $this->preview
                ? 'Vinculação de vendas (preview) concluída'
                : 'Vinculação de vendas concluída',
            'message' => $this->buildMessage(),
            'type' => 'info',
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title' => $this->toArray($notifiable)['title'],
            'message' => $this->buildMessage(),
            'type' => 'info',
            'data' => [
                'preview' => $this->preview,
                'results' => $this->results,
                'total_clients' => $this->totalClients,
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
        if (empty($this->results)) {
            return 'Nenhum cliente processado.';
        }

        $parts = collect($this->results)->map(function (array $r) {
            $linked = $r['linked'] ?? 0;
            $remaining = $r['remaining'] ?? 0;
            $msg = "{$r['client_name']}: {$linked} vinculadas";
            if ($remaining > 0) {
                $msg .= ", {$remaining} sem produto";
            }

            return $msg;
        });

        return $parts->implode(' | ');
    }
}

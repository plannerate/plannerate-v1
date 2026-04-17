<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificação de início do processamento do sync:cleanup (database + broadcast).
 * Enviada no início da chain de jobs de cada cliente.
 */
class CleanupStartedNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

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
                ? 'Sync Cleanup (preview) iniciado'
                : 'Sync Cleanup iniciado',
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
        $clientsSummary = collect($this->results)->map(function (array $r) {
            $parts = [];
            if (($r['orphan_sales'] ?? 0) > 0) {
                $parts[] = "{$r['orphan_sales']} vendas órfãs";
            }
            if (($r['old_sales'] ?? 0) > 0) {
                $parts[] = "{$r['old_sales']} vendas antigas";
            }
            if (($r['inactive_products'] ?? 0) > 0) {
                $parts[] = "{$r['inactive_products']} produtos inativos";
            }
            if (($r['restore_sold'] ?? 0) > 0) {
                $parts[] = "{$r['restore_sold']} produtos a restaurar";
            }
            $summary = $parts ? implode(', ', $parts) : 'nenhuma ação';

            return "{$r['client_name']}: {$summary}";
        })->implode(' | ');

        return $clientsSummary ?: 'Nenhum item para processar.';
    }
}

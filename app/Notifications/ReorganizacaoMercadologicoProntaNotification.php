<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificação quando a sugestão de reorganização do mercadológico (IA) está pronta.
 * Database + broadcast para o usuário ver no dropdown e em tempo real.
 */
class ReorganizacaoMercadologicoProntaNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    /**
     * @param  string  $logId  ID do log de reorganização
     * @param  string|null  $mercadologicoIndexUrl  URL da página mercadológico (gerada no controller; no worker a rota pode não existir)
     */
    public function __construct(
        public string $logId,
        public ?string $mercadologicoIndexUrl = null
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
        $url = $this->mercadologicoIndexUrl !== null && $this->mercadologicoIndexUrl !== ''
            ? $this->mercadologicoIndexUrl.'?reorganize_log='.$this->logId
            : '';

        return [
            'title' => 'Sugestão de reorganização pronta',
            'message' => 'A análise das categorias com IA foi concluída. Abra o painel "Sugestões" no mercadológico para revisar e aplicar.',
            'type' => 'info',
            'log_id' => $this->logId,
            'url' => $url,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $data = $this->toArray($notifiable);

        return new BroadcastMessage([
            'title' => $data['title'],
            'message' => $data['message'],
            'type' => $data['type'],
            'data' => [
                'log_id' => $this->logId,
                'url' => $data['url'],
            ],
            'created_at' => now()->toIso8601String(),
            'notification_type' => static::class,
        ]);
    }

    public function broadcastType(): string
    {
        return 'notification.created';
    }
}

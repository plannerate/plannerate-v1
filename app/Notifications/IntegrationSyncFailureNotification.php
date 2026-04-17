<?php

namespace App\Notifications;

use App\Models\Client;
use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IntegrationSyncFailureNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Client $client,
        public Store $store,
        public string $syncType, // sales, products, purchases
        public string $severity, // 'warning', 'error', 'critical'
        public array $failedDays,
        public int $consecutiveFailures
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $syncTypeLabel = match ($this->syncType) {
            'sales' => 'Vendas',
            'products' => 'Produtos',
            'purchases' => 'Compras',
            default => ucfirst($this->syncType),
        };

        $subject = match ($this->severity) {
            'critical' => "🔴 CRÍTICO: Sincronização de {$syncTypeLabel} Falhou",
            'error' => "⚠️ ERRO: Problemas na Sincronização de {$syncTypeLabel}",
            'warning' => "⚡ AVISO: Dias com falhas na sincronização de {$syncTypeLabel}",
        };

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting("Olá, {$notifiable->name}!")
            ->line("**Cliente:** {$this->client->name}")
            ->line("**Loja:** {$this->store->name}")
            ->line("**Tipo de Sincronização:** {$syncTypeLabel}")
            ->line("**Falhas consecutivas:** {$this->consecutiveFailures}");

        if ($this->severity === 'critical') {
            $message->line('⚠️ A sincronização foi **INTERROMPIDA** devido a múltiplas falhas consecutivas.')
                ->line('**Ação requerida:** Verificar logs e configuração da integração.');
        }

        if (! empty($this->failedDays)) {
            $dates = implode(', ', array_column($this->failedDays, 'sync_date'));
            $message->line("**Dias com falha:** {$dates}");
        }

        return $message->action('Ver Detalhes', url('/settings/integrations'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'client_id' => $this->client->id,
            'client_name' => $this->client->name,
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'sync_type' => $this->syncType,
            'severity' => $this->severity,
            'failed_days' => $this->failedDays,
            'consecutive_failures' => $this->consecutiveFailures,
        ];
    }
}

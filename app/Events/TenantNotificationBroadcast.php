<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Broadcast SÍNCRONO de uma notificação para o canal do usuário, carregando
 * apenas dados primitivos (sem model Eloquent).
 *
 * Motivo: o broadcast padrão de notificações (BroadcastNotificationCreated) é
 * enfileirado e serializa o notifiable User. Quando o worker roda sem tenant
 * corrente — o Spatie remove o tenant de jobs não-TenantAware, como o de
 * broadcast herdando a AppNotification (NotTenantAware) — a restauração do User
 * (que usa a conexão tenant, com database nulo fora de contexto) falha com
 * ModelNotFoundException, e o real-time nunca chega ao sino.
 *
 * Como ShouldBroadcastNow, este evento roda EM PROCESSO (sem fila e sem restaurar
 * models), então funciona a partir de um job TenantAware. Usa o MESMO nome de
 * evento do broadcast de notificações do Laravel para ser capturado pelo listener
 * já existente no frontend (useEchoNotification), sem qualquer mudança no cliente.
 */
class TenantNotificationBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  string  $userId  ULID do usuário destino (compõe o canal privado)
     * @param  array<string, mixed>  $payload  dados da notificação (inclui id/type)
     */
    public function __construct(
        public string $userId,
        public array $payload,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("App.Models.User.{$this->userId}");
    }

    /**
     * Mesmo nome do evento de notificação do Laravel, para o Echo capturá-lo via
     * canal.notification() / useEchoNotification.
     */
    public function broadcastAs(): string
    {
        return 'Illuminate\\Notifications\\Events\\BroadcastNotificationCreated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}

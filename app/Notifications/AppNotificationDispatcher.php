<?php

namespace App\Notifications;

use App\Events\TenantNotificationBroadcast;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Envia uma AppNotification a partir de um job TenantAware: persiste no banco do
 * tenant e transmite em tempo real, com o MESMO id nos dois lados.
 *
 * Fonte única dos jobs que notificam o usuário ao terminar (geração de gôndola,
 * relatórios). A lógica era duplicada neles — e o mesmo bug do id nulo no broadcast
 * também.
 */
final class AppNotificationDispatcher
{
    /**
     * @param  string  $context  identifica a origem no log em caso de falha de broadcast
     */
    public function send(User $user, AppNotification $notification, string $context): void
    {
        // O id precisa ser definido AQUI, antes do envio: notifyNow() entrega um CLONE
        // da notificação ao canal e é nesse clone que o Laravel gera o id — o objeto
        // deste escopo ficaria com id null e o broadcast sairia sem id, quebrando o
        // dropdown no cliente (que usa o id para baixar/marcar lida/excluir). O canal
        // database só gera um id quando ele está vazio, então banco e broadcast ficam
        // com o mesmo valor.
        $notification->id = (string) Str::uuid();

        // Persiste APENAS pelo canal database. O canal broadcast padrão serializa o
        // notifiable User (conexão tenant) e, re-enfileirado num worker sem tenant
        // restaurado, falharia com ModelNotFoundException. O notifyNow síncrono grava
        // no banco do tenant corrente; o tempo real vai pelo evento abaixo.
        $user->notifyNow($notification, ['database']);

        try {
            // Evento ShouldBroadcastNow com dados primitivos (sem model Eloquent),
            // capturado pelo listener do front (useEchoNotification).
            TenantNotificationBroadcast::dispatch((string) $user->getKey(), array_merge(
                $notification->toArray($user),
                [
                    'id' => $notification->id,
                    'type' => AppNotification::class,
                    'read_at' => null,
                ],
            ));
        } catch (\Throwable $e) {
            // A notificação já foi persistida (aparece ao recarregar a página); uma
            // falha de broadcast (ex.: Reverb fora do ar) não deve derrubar o job.
            Log::warning("{$context}: falha no broadcast (notificação já persistida)", [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}

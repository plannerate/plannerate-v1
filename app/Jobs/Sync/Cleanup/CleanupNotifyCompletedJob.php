<?php

namespace App\Jobs\Sync\Cleanup;

use App\Models\Client;
use App\Models\User;
use App\Notifications\CleanupCompletedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job que envia notificação (database + broadcast) no fim da chain de cleanup do cliente.
 */
class CleanupNotifyCompletedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 30;

    public function __construct(
        protected Client $client,
        protected array $summary,
        protected bool $preview
    ) {}

    public function handle(): void
    {
        try {
            $users = User::all();
            if ($users->isEmpty()) {
                return;
            }
            $notification = new CleanupCompletedNotification(
                $this->preview,
                [$this->summary],
                1
            );
            foreach ($users as $user) {
                $user->notify($notification);
            }
            Log::info('Notificação de conclusão do cleanup (job) enviada', [
                'client_id' => $this->client->id,
                'client_name' => $this->client->name,
                'users_count' => $users->count(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar notificação de conclusão do cleanup (job)', [
                'client_id' => $this->client->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function tags(): array
    {
        return [
            'cleanup',
            'notify-completed',
            "client:{$this->client->id}",
        ];
    }
}

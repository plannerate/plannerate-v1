<?php

namespace App\Jobs\Cleanup;

use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\NotTenantAware;

/**
 * Último elo da corrente de cleanup: só roda depois que os jobs de limpeza do
 * tenant realmente executaram. Antes, a notificação "Limpeza concluída" era
 * enviada pelo comando no momento do DESPACHO — antes de qualquer job rodar.
 */
class NotifyCleanupCompletedJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public string $tenantId,
        public string $tenantName,
        public int $jobsDispatched,
    ) {
        $this->onQueue('maintenance');
    }

    public function handle(): void
    {
        try {
            $users = User::all();

            if ($users->isEmpty()) {
                return;
            }

            $notification = new AppNotification(
                title: 'Limpeza concluída',
                message: sprintf(
                    'Tenant %s: %d job(s) de limpeza executado(s).',
                    $this->tenantName,
                    $this->jobsDispatched,
                ),
                type: 'success',
            );

            foreach ($users as $user) {
                $user->notify($notification);
            }
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar notificação de conclusão do cleanup', [
                'tenant_id' => $this->tenantId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'cleanup',
            'notify',
            "tenant:{$this->tenantId}",
        ];
    }
}

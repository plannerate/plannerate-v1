<?php

namespace App\Jobs\Integrations;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Spatie\Multitenancy\Jobs\NotTenantAware;

/**
 * Dispara, sob demanda, a importação ou o pipeline pós-importação de um tenant —
 * é o que os botões da tela `/tenants/{tenant}/integration` enfileiram.
 *
 * Existe em vez de `Artisan::queue()` porque `queues_are_tenant_aware_by_default`
 * é `true`: o `QueuedCommand` do framework não implementa `NotTenantAware`, e o
 * Spatie tentava resolver um tenant que não existe no contexto do painel
 * landlord, estourando
 * `CurrentTenantCouldNotBeDeterminedInTenantAwareJob` na hora de processar.
 *
 * O `step` é fechado de propósito: um job genérico de "rode este comando" com o
 * nome vindo do payload seria execução arbitrária a partir da fila.
 */
class RunIntegrationPipelineJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const STEP_IMPORT = 'import';

    public const STEP_POST_IMPORT = 'post-import';

    public int $tries = 1;

    /**
     * Abaixo do timeout do supervisor `maintenance` (config/horizon.php: 1860s):
     * o pós-importação espera as filas de importação esvaziarem, e o worker
     * mataria o job no meio dessa espera.
     */
    public int $timeout = 1800;

    public function __construct(
        public readonly string $step,
        public readonly string $tenantId,
        /** Espera máxima (minutos) do pós-importação pelas filas de importação. */
        public readonly int $waitMinutes = 20,
    ) {
        if (! in_array($step, [self::STEP_IMPORT, self::STEP_POST_IMPORT], true)) {
            throw new InvalidArgumentException("Etapa de pipeline desconhecida: {$step}");
        }

        $this->onQueue('maintenance');
    }

    public function handle(): void
    {
        [$command, $parameters] = $this->step === self::STEP_IMPORT
            ? ['integration:run', ['--tenant' => $this->tenantId]]
            : ['sync:post-import', ['--tenant' => $this->tenantId, '--wait-minutes' => $this->waitMinutes]];

        Log::info('RunIntegrationPipelineJob: executando etapa sob demanda', [
            'step' => $this->step,
            'tenant_id' => $this->tenantId,
            'command' => $command,
        ]);

        $exitCode = Artisan::call($command, $parameters);

        if ($exitCode !== 0) {
            Log::warning('RunIntegrationPipelineJob: comando terminou com erro', [
                'step' => $this->step,
                'tenant_id' => $this->tenantId,
                'command' => $command,
                'exit_code' => $exitCode,
                'output' => Artisan::output(),
            ]);
        }
    }

    /** @return array<int, string> */
    public function tags(): array
    {
        return ['integration', 'pipeline', "step:{$this->step}", "tenant:{$this->tenantId}"];
    }
}

<?php

namespace App\Console\Commands\Sync;

use App\Models\Client;
use App\Models\Store;
use App\Services\Sync\IntegrationCircuitBreaker;
use Illuminate\Console\Command;

class ResetCircuitBreakerCommand extends Command
{
    use \App\Concerns\BelongsToConnection;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:reset-circuit 
                            {--client= : ID do cliente específico}
                            {--store= : ID da loja específica}
                            {--integration= : Tipo de integração (sysmo, visao)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reseta o Circuit Breaker de integrações bloqueadas';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $clientId = $this->option('client');
        $storeId = $this->option('store');
        $integrationType = $this->option('integration');

        // Se não passou parâmetros, pede confirmação
        if (! $clientId || ! $storeId || ! $integrationType) {
            $this->error('❌ Você deve fornecer --client, --store e --integration');
            $this->info('');
            $this->info('Exemplo: php artisan sync:reset-circuit --client=XXX --store=YYY --integration=visao');

            return self::FAILURE;
        }

        // Valida se cliente existe
        $client = Client::find($clientId);
        if (! $client) {
            $this->error("❌ Cliente {$clientId} não encontrado");

            return self::FAILURE;
        }

        // Configura o contexto do tenant/client antes de processar
        config([
            'app.current_tenant_id' => $client->tenant_id,
            'app.current_client_id' => $client->id,
        ]);
        $this->setupClientConnection($client);

        // Valida se loja existe
        $store = Store::find($storeId);
        if (! $store) {
            $this->error("❌ Loja {$storeId} não encontrada");

            return self::FAILURE;
        }

        // Verifica estado atual
        $state = IntegrationCircuitBreaker::getState($clientId, $storeId, $integrationType);

        if (! $state) {
            $this->info('ℹ️  Circuit Breaker já está FECHADO para esta integração');

            return self::SUCCESS;
        }

        $this->warn('🔴 Estado atual do Circuit Breaker:');
        $this->table(
            ['Campo', 'Valor'],
            [
                ['Status', $state['status']],
                ['Falhas', $state['failures']],
                ['Último erro', $state['last_error'] ?? 'N/A'],
                ['Primeira falha em', $state['first_failure_at'] ?? 'N/A'],
                ['Bloqueado até', $state['open_until'] ?? 'N/A'],
            ]
        );

        // Pede confirmação
        if (! $this->confirm('Deseja resetar o Circuit Breaker desta integração?', true)) {
            $this->info('Operação cancelada');

            return self::SUCCESS;
        }

        // Reseta
        IntegrationCircuitBreaker::reset($clientId, $storeId, $integrationType);

        $this->info('✅ Circuit Breaker resetado com sucesso!');
        $this->info("   Cliente: {$client->name}");
        $this->info("   Loja: {$store->name}");
        $this->info("   Integração: {$integrationType}");
        $this->newLine();
        $this->info('💡 Agora você pode rodar novamente: php artisan sync:sales');

        return self::SUCCESS;
    }
}

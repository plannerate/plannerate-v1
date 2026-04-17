<?php

/**
 * Trait para configuração de integração compartilhada entre comandos de sincronização.
 *
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Console\Commands\Sync\Concerns;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\ClientIntegration;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

trait IntegrationConfigTrait
{
    /**
     * Configura o contexto do tenant/client antes de processar
     */
    protected function configureTenantContext(Client $client): void
    {
        config([
            'app.current_tenant_id' => $client->tenant_id,
            'app.current_client_id' => $client->id,
        ]);

        try {
            $this->setupClientConnection($client);
        } catch (\Exception $e) {
            Log::error('Erro ao configurar conexão do cliente', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtém clientes para processar
     */
    protected function getClients(): Collection
    {
        $query = Client::query()
            ->where('status', ClientStatus::Published->value);

        if ($clientId = $this->option('client')) {
            $query->where('id', $clientId);
        }

        return $query->get();
    }

    /**
     * Prepara a configuração base da integração
     */
    protected function prepareBaseIntegrationConfig(
        Client $client,
        Store $store,
        ClientIntegration $integration,
        array $extraBody = []
    ): array {
        $data = $integration->toArray();

        // Headers de autenticação
        $headers = $this->normalizeArray($data['authentication_headers'] ?? []);

        // Body de autenticação
        $body = $this->normalizeArray($data['authentication_body'] ?? []);

        // Configurações gerais
        $config = $this->normalizeArray($data['config'] ?? []);

        // Adiciona documento da loja
        $documentName = data_get($config, 'document_name', 'document');
        if ($documentName) {
            $body[$documentName] = preg_replace('/\D/', '', $store->document);
        }

        // Merge com dados extras (datas, etc.)
        $body = array_merge($body, $extraBody);
        

        return array_merge($data, [
            'base_url' => data_get($data, 'api_url', ''),
            'headers' => [
                'authorization' => [
                    'username' => $headers['auth_username'] ?? null,
                    'password' => $headers['auth_password'] ?? null,
                ],
            ],
            'body' => $body,
            'max_pages' => data_get($config, 'max_pages'),
            'client_name' => $client->name,
            'store_name' => $store->name,
        ]);
    }

    /**
     * Normaliza array para formato associativo.
     * Suporta dois formatos:
     * - Formato antigo: [{key: 'x', value: 'y'}, ...]
     * - Formato novo (associativo): {x: 'y', ...}
     */
    protected function normalizeArray(array $data): array
    {
        if (empty($data)) {
            return [];
        }

        // Verifica se é formato antigo (array de objetos com key/value)
        if (isset($data[0]['key'])) {
            $transformed = [];
            foreach ($data as $item) {
                if (isset($item['key'], $item['value'])) {
                    $transformed[$item['key']] = $item['value'];
                }
            }

            return $transformed;
        }

        // Já está no formato associativo
        return $data;
    }

    /**
     * Exibe configuração de integração para debug
     */
    protected function debugIntegrationConfig(Client $client): void
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════');
        $this->info("🏢 CLIENTE: {$client->name}");
        $this->info("   ID: {$client->id} | Database: {$client->database}");
        $this->info('═══════════════════════════════════════════════════════');

        $integration = $client->client_integration;

        if (! $integration) {
            $this->warn('   ⚠️  SEM INTEGRAÇÃO CONFIGURADA');

            return;
        }

        $this->info("   📌 Tipo: {$integration->integration_type}");
        $this->info("   📌 URL: {$integration->api_url}");

        // Headers
        $headers = $this->normalizeArray($integration->authentication_headers ?? []);
        $this->info('   🔑 Auth: '.($headers['auth_username'] ?? '<não definido>'));

        // Body
        $body = $this->normalizeArray($integration->authentication_body ?? []);
        if (! empty($body)) {
            $this->info('   📝 Body: '.json_encode(array_keys($body)));
        }

        // Lojas
        $stores = $client->storesDocument;
        if ($stores && $stores->isNotEmpty()) {
            $this->info("   🏪 Lojas: {$stores->count()}");
            foreach ($stores as $store) {
                $doc = preg_replace('/\D/', '', $store->document);
                $this->line("      - {$store->name} ({$doc})");
            }
        }
    }
}

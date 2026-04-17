<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Services;

use Callcocam\LaravelRaptor\Enums\TenantStatus;
use Callcocam\LaravelRaptor\Services\TenantResolver;
use Callcocam\LaravelRaptor\Support\ResolvedTenantConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * TenantResolver avançado com suporte a:
 * - Múltiplos domínios por tenant (tenant_domains)
 * - Domainable polimórfico (Client, Store, etc)
 * - Banco de dados separado por tenant
 *
 * Segue as orientações de: docs/custom-tenant-resolver.md
 *
 * Para usar, configure em config/raptor.php:
 * ```php
 * 'services' => [
 *     'tenant_resolver' => \App\Services\AdvancedTenantResolver::class,
 * ]
 * ```
 */
class AdvancedTenantResolver extends TenantResolver
{
    /**
     * Override: detecta tenant com suporte a tenant_domains e domainable
     *
     * Retorna array com ['tenant' => Model, 'domainData' => object|null]
     * para que resolve() possa chamar storeTenantContext corretamente
     */
    protected function detectTenant(Request $request): mixed
    {
        $host = $request->getHost();
        $domain = str($host)->replace('www.', '')->toString();
        // 1. Verifica se é contexto landlord (SEMPRE PRIMEIRO)
        $landlordSubdomain = config('raptor.landlord.subdomain', 'landlord');
        if (str_contains($host, "{$landlordSubdomain}.")) {
            config(['app.context' => 'landlord']);

            return null;
        }

        $tenantModel = config('raptor.models.tenant', \Callcocam\LaravelRaptor\Models\Tenant::class);

        // 2. Primeiro tenta buscar na tabela tenant_domains (suporte a múltiplos domínios)
        $domainData = $this->findByTenantDomains($domain);

        if ($domainData && $domainData->tenant_id) {
            $tenant = $tenantModel::find($domainData->tenant_id);
            if ($tenant && $tenant->status->value === TenantStatus::Published->value) {
                // Se domainData tem domainable, tenta injetar o banco do domainable no tenant
                $this->injectDomainableDatabase($tenant, $domainData);

                // Retorna array com tenant e domainData para processar no resolve()
                return [
                    'tenant' => $tenant,
                    'domainData' => $this->prepareDomainData($domainData),
                ];
            }
        }

        // 3. Fallback: busca direto na tabela tenants (compatibilidade)
        $domainColumn = config('raptor.tenant.subdomain_column', 'domain');
        $tenant = $tenantModel::where($domainColumn, $domain)
            ->where('status', TenantStatus::Published->value)
            ->first();

        if ($tenant) {
            return [
                'tenant' => $tenant,
                'domainData' => null,
            ];
        }

        return null;
    }

    /**
     * Override resolve para processar resultado de detectTenant com domainData
     */
    public function resolve(Request $request): mixed
    {
        // Cache por requisição (não resolver duas vezes)
        if ($this->isResolved()) {
            return $this->getTenant();
        }

        $result = $this->detectTenant($request);

        // Marca como resolvido
        $this->resolved = true;

        // Se detectTenant retornou null (landlord ou não encontrado)
        if ($result === null) {
            $this->tenant = null;

            return null;
        }

        // Se detectTenant retornou array com tenant e domainData
        if (is_array($result) && isset($result['tenant'])) {
            $this->tenant = $result['tenant'];
            $domainData = $result['domainData'] ?? null;

            // DEBUG: Log para verificar dados do tenant
            Log::info('[AdvancedTenantResolver] Tenant resolvido', [
                'tenant_id' => $this->tenant->getKey(),
                'tenant_database' => $this->tenant->getAttribute('database'),
                'domainable_type' => $domainData?->domainable_type,
                'domainable_id' => $domainData?->domainable_id,
                'host' => $request->getHost(),
            ]);

            // Chama storeTenantContext com tenant e domainData
            $this->storeTenantContext($this->tenant, $domainData);

            return $this->tenant;
        }

        // Fallback: se detectTenant retornou apenas o tenant (compatibilidade)
        $this->tenant = $result;

        if ($this->tenant) {
            $this->storeTenantContext($this->tenant, null);
        }

        return $this->tenant;
    }

    /**
     * Busca domínio na tabela tenant_domains com join no tenant
     */
    protected function findByTenantDomains(string $domain): ?object
    {
        return DB::connection(config('raptor.database.landlord_connection_name', 'landlord'))->table('tenant_domains')
            ->join('tenants', 'tenants.id', '=', 'tenant_domains.tenant_id')
            ->where('tenant_domains.domain', $domain)
            ->where('tenants.status', TenantStatus::Published->value)
            ->whereNull('tenants.deleted_at')
            ->select(
                'tenants.id as tenant_id',
                'tenant_domains.domainable_type',
                'tenant_domains.domainable_id',
                'tenant_domains.is_primary'
            )
            ->first();
    }

    /**
     * Prepara objeto domainData no formato esperado por ResolvedTenantConfig
     */
    protected function prepareDomainData(?object $domainData): ?object
    {
        if (! $domainData || empty($domainData->domainable_type) || empty($domainData->domainable_id)) {
            return null;
        }

        return (object) [
            'domainable_type' => $domainData->domainable_type,
            'domainable_id' => (string) $domainData->domainable_id,
        ];
    }

    /**
     * Injeta o banco do domainable (Client/Store) no tenant
     *
     * Usa TenantDatabaseResolver para buscar banco seguindo hierarquia:
     * Store > Client > Tenant
     */
    protected function injectDomainableDatabase($tenant, ?object $domainData): void
    {
        if (! $domainData || empty($domainData->domainable_type) || empty($domainData->domainable_id)) {
            return;
        }

        try {
            // Usa serviço centralizado para buscar domainable com banco
            $resolver = app(TenantDatabaseResolver::class);
            $domainableInfo = $resolver->getDomainableWithDatabase(
                $domainData->domainable_type,
                $domainData->domainable_id
            );

            if ($domainableInfo && $domainableInfo->database) {
                // Injeta o banco do domainable no tenant
                // Isso faz com que ResolvedTenantConfig::from() use o banco correto
                $tenant->setAttribute('database', $domainableInfo->database);

                Log::info('[AdvancedTenantResolver] Banco do domainable injetado no tenant', [
                    'domainable_type' => $domainableInfo->type,
                    'domainable_id' => $domainableInfo->id,
                    'database' => $domainableInfo->database,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('[AdvancedTenantResolver] Erro ao injetar banco do domainable', [
                'domainable_type' => $domainData->domainable_type,
                'domainable_id' => $domainData->domainable_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * {@inheritdoc}
     *
     * O parent storeTenantContext já faz tudo através de ResolvedTenantConfig:
     * - Registra tenant context
     * - Configura app.current_client_id / app.current_store_id
     * - Aplica TenantDatabaseManager::applyConfig
     * - Adiciona tenant ao Landlord
     *
     * Não precisa override, mas mantido aqui para documentação
     */
    // public function storeTenantContext(mixed $tenant, ?object $domainData = null): void
    // {
    //     parent::storeTenantContext($tenant, $domainData);
    // }

    /**
     * {@inheritdoc}
     *
     * Usado quando o contexto de tenant já existe (ex.: job, command)
     * e você só precisa reaplicar a configuração de banco.
     *
     * Conforme orientação: deve chamar ResolvedTenantConfig::from() e
     * TenantDatabaseManager::applyConfig()
     */
    public function configureTenantDatabase(mixed $tenant, ?object $domainData = null): void
    {
        if (! $tenant) {
            return;
        }

        $configClass = config('raptor.config.resolved_tenant_config', ResolvedTenantConfig::class);
        $managerClass = config('raptor.services.tenant_database_manager', \Callcocam\LaravelRaptor\Services\TenantDatabaseManager::class);

        // Cria configuração a partir do tenant
        $resolvedConfig = $configClass::from($tenant, $domainData);

        // Aplica configuração de banco
        if (class_exists($managerClass)) {
            app($managerClass)->applyConfig($resolvedConfig);
        }
    }
}

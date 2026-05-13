<?php

namespace App\Console\Commands\Integrations;

use App\Events\Tenant\TenantIsolationCheckEvent;
use App\Models\Tenant;
use Illuminate\Console\Command;

class RunTenantEventCheckCommand extends Command
{
    protected $signature = 'tenant:test-event
        {tenant? : ID do tenant alvo}
        {--resource=isolation_test : Identificador do teste no payload do evento}';

    protected $description = 'Dispara evento de diagnóstico para validar isolamento por tenant';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->resolveTenantId();

        if ($tenantId === null) {
            return self::FAILURE;
        }

        $resource = trim((string) $this->option('resource'));

        if ($resource === '') {
            $resource = 'isolation_test';
        }

        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant instanceof Tenant) {
            $this->error(sprintf('Tenant nao encontrado: %s', $tenantId));

            return self::FAILURE;
        }

        $testedAt = now()->toIso8601String();

        $isolationOk = (bool) $tenant->execute(function () use ($tenantId, $resource, $testedAt): bool {
            $currentTenant = Tenant::current();
            $currentTenantId = (string) ($currentTenant?->getKey() ?? '');
            $currentTenantSlug = (string) ($currentTenant?->getAttribute('slug') ?? '');
            $status = $currentTenantId === $tenantId ? 'ok' : 'mismatch';

            event(new TenantIsolationCheckEvent(
                tenantId: $tenantId,
                currentTenantId: $currentTenantId,
                tenantSlug: $currentTenantSlug,
                resource: $resource,
                testedAt: $testedAt,
                status: $status,
            ));

            return $status === 'ok';
        });

        if (! $isolationOk) {
            $this->error(sprintf('Falha no isolamento de tenant para %s', $tenantId));

            return self::FAILURE;
        }

        $this->info(sprintf('Evento de teste disparado com sucesso para tenant %s', $tenantId));

        return self::SUCCESS;
    }

    private function resolveTenantId(): ?string
    {
        $tenantId = trim((string) $this->argument('tenant'));

        if ($tenantId !== '') {
            return $tenantId;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Informe o ID do tenant quando o comando estiver em modo nao interativo.');

            return null;
        }

        $tenants = Tenant::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        if ($tenants->isEmpty()) {
            $this->error('Nenhum tenant encontrado para selecao.');

            return null;
        }

        $choices = $tenants
            ->map(fn (Tenant $tenant): string => sprintf(
                '%s [%s] (%s)',
                (string) $tenant->name,
                (string) $tenant->slug,
                (string) $tenant->id,
            ))
            ->values()
            ->all();

        $selected = (string) $this->choice('Selecione o tenant alvo', $choices);

        if (! preg_match('/\(([^)]+)\)$/', $selected, $matches)) {
            $this->error('Nao foi possivel extrair o tenant selecionado.');

            return null;
        }

        return (string) $matches[1];
    }
}

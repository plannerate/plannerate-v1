<?php

namespace App\Console\Commands\Integrations;

use App\Models\IntegrationApi;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\IntegrationModels;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\Multitenancy\Models\Tenant;
use Throwable;

/**
 * Restaura `integration_apis` e `tenant_integrations` a partir dos snapshots que
 * a aplicação grava em `storage/app/private/last_payload/{tenant_id}.json` a cada
 * vez que a integração de um tenant é salva.
 *
 * Existe porque esses snapshots são a única cópia do blueprint + credenciais fora
 * do banco landlord. Sem eles, perder o landlord significa reconfigurar cada
 * integração na mão — inclusive redigitar senhas de API.
 *
 * Preserva os ids originais: o `tenant_integrations.integration_type` aponta para
 * o id do blueprint, então recriar com id novo quebraria o vínculo.
 *
 * Idempotente: `updateOrCreate` por id. Rode com `--dry-run` antes.
 *
 * O snapshot grava, junto dos dados, a chave `model` com o FQCN do model que os
 * produziu. Ela é metadado descritivo e **não** é usada para resolver classe alguma:
 * a restauração sempre passa pelos models desta versão do código. É isso que faz um
 * snapshot antigo continuar restaurável depois de o motor mudar de namespace.
 */
class RestoreIntegrationSnapshotCommand extends Command
{
    protected $signature = 'integration:restore-snapshot
        {--tenant= : Restaura só o snapshot deste tenant}
        {--dry-run : Mostra o que faria, sem gravar}';

    protected $description = 'Restaura blueprints e integrações de tenant a partir dos snapshots em last_payload';

    private const SNAPSHOT_DIR = 'last_payload';

    public function handle(): int
    {
        $files = $this->snapshotFiles();

        if ($files === []) {
            $this->warn('Nenhum snapshot encontrado em storage/app/private/'.self::SNAPSHOT_DIR);

            return self::SUCCESS;
        }

        $this->info(sprintf('%d snapshot(s) encontrado(s).%s', count($files), $this->option('dry-run') ? ' [dry-run]' : ''));
        $this->newLine();

        $restored = 0;

        foreach ($files as $file) {
            $restored += $this->restoreFile($file) ? 1 : 0;
        }

        $this->newLine();
        $this->info(sprintf('%d snapshot(s) restaurado(s).', $restored));

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function snapshotFiles(): array
    {
        $tenantId = (string) $this->option('tenant');

        $files = collect(Storage::disk('local')->files(self::SNAPSHOT_DIR))
            ->filter(fn (string $path): bool => str_ends_with($path, '.json'));

        if ($tenantId !== '') {
            $files = $files->filter(fn (string $path): bool => basename($path, '.json') === $tenantId);
        }

        return $files->values()->all();
    }

    private function restoreFile(string $path): bool
    {
        $tenantId = basename($path, '.json');
        $snapshot = json_decode((string) Storage::disk('local')->get($path), true);

        if (! is_array($snapshot)) {
            $this->warn("  {$tenantId}: JSON inválido, ignorado.");

            return false;
        }

        $apiData = (array) data_get($snapshot, 'integration_api.data', []);
        $integrationData = (array) data_get($snapshot, 'tenant_integration.data', []);

        if ($apiData === [] || $integrationData === []) {
            $this->warn("  {$tenantId}: snapshot sem integration_api ou tenant_integration, ignorado.");

            return false;
        }

        if (IntegrationModels::tenant()::query()->whereKey($tenantId)->doesntExist()) {
            $this->warn("  {$tenantId}: tenant não existe no landlord — crie-o antes de restaurar a integração.");

            return false;
        }

        $this->line(sprintf(
            '  <fg=cyan>%s</> → blueprint <fg=blue>%s</> (%s)',
            $tenantId,
            (string) ($apiData['name'] ?? '?'),
            (string) ($apiData['id'] ?? '?'),
        ));

        if ($this->option('dry-run')) {
            $this->line(sprintf(
                '     [dry-run] base_url=%s | auth=%s | paths=%s',
                (string) data_get($integrationData, 'config.connection.base_url', '-'),
                (string) data_get($integrationData, 'config.auth.type', '-'),
                implode(', ', array_keys((array) data_get($apiData, 'requests.paths', []))),
            ));

            return true;
        }

        try {
            $this->restoreApi($apiData);
            $this->restoreTenantIntegration($tenantId, $integrationData);
        } catch (Throwable $e) {
            $this->error("     falhou: {$e->getMessage()}");

            return false;
        }

        $this->line('     <fg=green>restaurado</>');

        return true;
    }

    /** @param array<string, mixed> $apiData */
    private function restoreApi(array $apiData): void
    {
        $api = IntegrationApi::withTrashed()->firstOrNew(['id' => (string) $apiData['id']]);

        $api->forceFill([
            'id' => (string) $apiData['id'],
            'name' => (string) ($apiData['name'] ?? ''),
            'description' => $apiData['description'] ?? null,
            'requests' => (array) ($apiData['requests'] ?? []),
            'response' => (array) ($apiData['response'] ?? []),
            'is_active' => (bool) ($apiData['is_active'] ?? true),
            'deleted_at' => null,
        ])->save();

        // O HasSlug regenera o slug a partir do `name` em todo save (inclusive
        // update). Reescrevemos direto na tabela para preservar o slug original
        // do snapshot — é por ele que a documentação e as migrations guardadas
        // procuram o blueprint.
        IntegrationApi::withTrashed()
            ->whereKey($api->id)
            ->toBase()
            ->update(['slug' => (string) ($apiData['slug'] ?? $api->slug)]);
    }

    /** @param array<string, mixed> $integrationData */
    private function restoreTenantIntegration(string $tenantId, array $integrationData): void
    {
        $integration = TenantIntegration::withTrashed()
            ->firstOrNew(['tenant_id' => $tenantId]);

        $integration->forceFill([
            'tenant_id' => $tenantId,
            'integration_type' => (string) ($integrationData['integration_type'] ?? ''),
            'is_active' => (bool) ($integrationData['is_active'] ?? true),
            'config' => (array) ($integrationData['config'] ?? []),
            'deleted_at' => null,
        ])->save();
    }
}

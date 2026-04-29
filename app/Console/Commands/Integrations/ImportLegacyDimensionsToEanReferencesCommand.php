<?php

namespace App\Console\Commands\Integrations;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ImportLegacyDimensionsToEanReferencesCommand extends Command
{
    protected $signature = 'sync:import-legacy-dimensions-to-ean-references
        {--tenant-id= : Tenant ULID específico}
        {--chunk=1000 : Tamanho do lote}
        {--dry-run : Só mostra quantos registros seriam processados}';

    protected $description = 'Importa dimensions da base legada para ean_references (tenant específico, tenant atual ou todos ativos)';

    public function handle(): int
    {
        $tenantId = $this->resolveTenantId();

        try {
            DB::connection('mysql_legacy')->getPdo();
        } catch (\Throwable $exception) {
            $this->error('❌ Falha ao conectar em mysql_legacy: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! Schema::connection('mysql_legacy')->hasTable('dimensions')) {
            $this->error("❌ Tabela 'dimensions' não encontrada na conexão mysql_legacy.");

            return self::FAILURE;
        }

        $chunkSize = max(100, (int) $this->option('chunk'));
        $baseQuery = DB::connection('mysql_legacy')
            ->table('dimensions')
            ->select([
                'ean',
                'width',
                'height',
                'depth',
                'weight',
                'unit',
                'status',
            ])
            ->whereNotNull('ean');

        $total = (clone $baseQuery)->count();
        if ($total === 0) {
            $this->warn('⚠️ Nenhum registro encontrado em dimensions para importar.');

            return self::SUCCESS;
        }

        if (is_string($tenantId) && $tenantId !== '') {
            if ($this->option('dry-run')) {
                $this->info("👁️ Dry-run: {$total} registros seriam processados para o tenant {$tenantId}.");

                return self::SUCCESS;
            }

            $stats = $this->processSingleTenant($baseQuery, $chunkSize, $tenantId);
            $this->info("✅ Importação concluída. Lidos: {$stats['processed']} | Upsert: {$stats['upserted']}");

            return self::SUCCESS;
        }

        $targetTenants = $this->resolveTargetTenants($tenantId);
        if ($targetTenants->isEmpty()) {
            $this->warn('⚠️ Nenhum tenant ativo encontrado para processar.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("👁️ Dry-run: {$total} registros seriam processados para {$targetTenants->count()} tenant(s).");

            return self::SUCCESS;
        }

        foreach ($targetTenants as $tenant) {
            $this->line(sprintf('➡️ Processando tenant: %s (%s)', $tenant->name, $tenant->id));

            $stats = $tenant->execute(function () use ($baseQuery, $chunkSize, $tenant): array {
                if (! Schema::connection('tenant')->hasTable('ean_references')) {
                    return ['processed' => 0, 'upserted' => 0, 'skipped' => true];
                }

                $processed = 0;
                $upserted = 0;

                (clone $baseQuery)->orderBy('ean')->chunk($chunkSize, function ($rows) use ($tenant, &$processed, &$upserted): void {
                    $now = now();
                    $payload = [];

                    foreach ($rows as $row) {
                        $ean = $this->normalizeEan((string) $row->ean);
                        $processed++;

                        if ($ean === '') {
                            continue;
                        }

                        $width = $this->toDecimal($row->width);
                        $height = $this->toDecimal($row->height);
                        $depth = $this->toDecimal($row->depth);
                        $weight = $this->toDecimal($row->weight);
                        $unit = $this->normalizeUnit($row->unit);
                        $hasDimensions = $width > 0 && $height > 0 && $depth > 0;
                        $dimensionStatus = $this->normalizeDimensionStatus($row->status, $hasDimensions);

                        $payload[] = [
                            'id' => (string) Str::ulid(),
                            'tenant_id' => (string) $tenant->id,
                            'ean' => $ean,
                            'width' => $width,
                            'height' => $height,
                            'depth' => $depth,
                            'weight' => $weight,
                            'unit' => $unit,
                            'has_dimensions' => $hasDimensions,
                            'dimension_status' => $dimensionStatus,
                            'created_at' => $now,
                            'updated_at' => $now,
                            'deleted_at' => null,
                        ];
                    }

                    if ($payload !== []) {
                        DB::connection('tenant')
                            ->table('ean_references')
                            ->upsert(
                                $payload,
                                ['tenant_id', 'ean'],
                                ['width', 'height', 'depth', 'weight', 'unit', 'has_dimensions', 'dimension_status', 'updated_at', 'deleted_at']
                            );

                        $upserted += count($payload);
                    }
                });

                return ['processed' => $processed, 'upserted' => $upserted, 'skipped' => false];
            });

            if (($stats['skipped'] ?? false) === true) {
                $this->warn("   ⚠️ Tabela 'ean_references' não encontrada no tenant; pulando.");

                continue;
            }

            $this->line("   ✅ Lidos: {$stats['processed']} | Upsert: {$stats['upserted']}");
        }

        $this->info('✅ Importação concluída para os tenants selecionados.');

        return self::SUCCESS;
    }

    /**
     * @param  Builder  $baseQuery
     * @return array{processed:int,upserted:int}
     */
    private function processSingleTenant($baseQuery, int $chunkSize, string $tenantId): array
    {
        if (! Schema::connection('tenant')->hasTable('ean_references')) {
            $this->error("❌ Tabela 'ean_references' não encontrada na conexão tenant.");

            return ['processed' => 0, 'upserted' => 0];
        }

        $processed = 0;
        $upserted = 0;

        (clone $baseQuery)->orderBy('ean')->chunk($chunkSize, function ($rows) use ($tenantId, &$processed, &$upserted): void {
            $now = now();
            $payload = [];

            foreach ($rows as $row) {
                $ean = $this->normalizeEan((string) $row->ean);
                $processed++;

                if ($ean === '') {
                    continue;
                }

                $width = $this->toDecimal($row->width);
                $height = $this->toDecimal($row->height);
                $depth = $this->toDecimal($row->depth);
                $weight = $this->toDecimal($row->weight);
                $unit = $this->normalizeUnit($row->unit);
                $hasDimensions = $width > 0 && $height > 0 && $depth > 0;
                $dimensionStatus = $this->normalizeDimensionStatus($row->status, $hasDimensions);

                $payload[] = [
                    'id' => (string) Str::ulid(),
                    'tenant_id' => $tenantId,
                    'ean' => $ean,
                    'width' => $width,
                    'height' => $height,
                    'depth' => $depth,
                    'weight' => $weight,
                    'unit' => $unit,
                    'has_dimensions' => $hasDimensions,
                    'dimension_status' => $dimensionStatus,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ];
            }

            if ($payload !== []) {
                DB::connection('tenant')
                    ->table('ean_references')
                    ->upsert(
                        $payload,
                        ['tenant_id', 'ean'],
                        ['width', 'height', 'depth', 'weight', 'unit', 'has_dimensions', 'dimension_status', 'updated_at', 'deleted_at']
                    );

                $upserted += count($payload);
            }
        });

        return ['processed' => $processed, 'upserted' => $upserted];
    }

    private function resolveTenantId(): ?string
    {
        $tenantIdOption = $this->option('tenant-id');
        if (is_string($tenantIdOption) && $tenantIdOption !== '') {
            return $tenantIdOption;
        }

        $currentTenantKey = (string) config('multitenancy.current_tenant_container_key', 'currentTenant');
        $currentTenant = app()->bound($currentTenantKey) ? app($currentTenantKey) : null;

        if (is_object($currentTenant) && isset($currentTenant->id)) {
            return (string) $currentTenant->id;
        }

        return null;
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function resolveTargetTenants(?string $tenantId): Collection
    {
        if (is_string($tenantId) && $tenantId !== '') {
            $tenant = Tenant::query()->whereKey($tenantId)->first();

            return $tenant ? new Collection([$tenant]) : new Collection;
        }

        $currentTenantKey = (string) config('multitenancy.current_tenant_container_key', 'currentTenant');
        $currentTenant = app()->bound($currentTenantKey) ? app($currentTenantKey) : null;

        if ($currentTenant instanceof Tenant) {
            return new Collection([$currentTenant]);
        }

        return Tenant::query()
            ->where('status', 'active')
            ->get(['id', 'name', 'database']);
    }

    private function normalizeEan(string $ean): string
    {
        return preg_replace('/\D+/', '', $ean) ?? '';
    }

    private function normalizeUnit(mixed $unit): string
    {
        if (! is_string($unit)) {
            return 'cm';
        }

        $normalized = trim($unit);

        return $normalized !== '' ? mb_strtolower($normalized) : 'cm';
    }

    private function normalizeDimensionStatus(mixed $status, bool $hasDimensions): string
    {
        if (is_string($status)) {
            $normalized = mb_strtolower(trim($status));
            if (in_array($normalized, ['draft', 'published'], true)) {
                return $normalized;
            }
        }

        return $hasDimensions ? 'published' : 'draft';
    }

    private function toDecimal(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalized = str_replace(',', '.', trim($value));
            if (is_numeric($normalized)) {
                return (float) $normalized;
            }
        }

        return 0.0;
    }
}

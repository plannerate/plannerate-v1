<?php

namespace App\Console\Commands\Integrations;

use App\Models\Tenant;
use App\Services\Integrations\Support\LayerOrphanProductsReportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('report:layers-orphan-products {--tenant= : ID do tenant específico} {--limit=50 : Quantidade de linhas por tenant} {--with-legacy : Enriquecer com ean da base legada e produto local por ean} {--export= : Exporta relatório (csv)}')]
#[Description('Relatório de layers com product_id inexistente em products')]
class ReportLayerOrphanProductsCommand extends Command
{
    public function handle(LayerOrphanProductsReportService $service): int
    {
        $withLegacy = (bool) $this->option('with-legacy');
        $legacyAvailable = $this->legacyConnectionIsAvailable();
        $export = is_string($this->option('export')) ? strtolower((string) $this->option('export')) : null;

        if ($withLegacy && ! $legacyAvailable) {
            $this->error('Conexão [mysql_legacy] indisponível.');

            return self::FAILURE;
        }
        if ($export !== null && $export !== '' && $export !== 'csv') {
            $this->error('Formato de exportação inválido. Use: --export=csv');

            return self::FAILURE;
        }

        $tenants = $this->getTenants();
        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant ativo encontrado.');

            return self::SUCCESS;
        }

        $limit = max((int) $this->option('limit'), 1);
        $grandTotal = 0;
        $exportRows = [];

        foreach ($tenants as $tenant) {
            $this->newLine();
            $this->info(sprintf('🏢 %s (%s)', $tenant->name, $tenant->id));

            $configuredTenantConnection = config('multitenancy.tenant_database_connection_name');
            $tenantConnection = (string) ($configuredTenantConnection ?: config('database.default'));
            $shouldSwitchTenantContext = is_string($configuredTenantConnection) && $configuredTenantConnection !== '';

            $tenantDatabase = is_string($tenant->getAttribute('database'))
                ? trim((string) $tenant->getAttribute('database'))
                : '';

            if ($shouldSwitchTenantContext && $tenantDatabase === '') {
                $this->warn(sprintf('Tenant %s sem database configurado; relatório ignorado.', $tenant->id));

                continue;
            }

            $run = function () use ($service, $tenantConnection, $tenant, $limit, $withLegacy, $legacyAvailable, &$grandTotal, &$exportRows): void {
                $tenantId = (string) $tenant->id;
                $total = $service->countOrphans($tenantConnection, $tenantId);
                $grandTotal += $total;

                $this->line(sprintf('Total órfãos: %d', $total));

                if ($total === 0) {
                    return;
                }

                $rows = $service->listOrphans($tenantConnection, $tenantId, $limit);
                if ($legacyAvailable) {
                    $rows = $service->enrichWithLegacy($tenantConnection, 'mysql_legacy', $tenantId, $rows);
                }

                if ($withLegacy) {
                    foreach ($rows as $row) {
                        $effectiveEan = $row->ean ?? $row->legacy_ean ?? '';
                        $exportRows[] = [
                            'tenant_id' => $tenantId,
                            'tenant_name' => $tenant->name,
                            'layer_id' => $row->layer_id,
                            'segment_id' => $row->segment_id,
                            'product_id_atual' => $row->product_id,
                            'ean' => $effectiveEan,
                            'legacy_ean' => $row->legacy_ean ?? '',
                            'tenant_product_id_by_ean' => $row->tenant_product_id_by_ean ?? '',
                            'updated_at' => (string) ($row->updated_at ?? ''),
                        ];
                    }
                    $this->table(
                        ['layer_id', 'segment_id', 'product_id_atual', 'ean', 'legacy_ean', 'tenant_product_id_by_ean', 'updated_at'],
                        $rows->map(static fn ($row) => [
                            $row->layer_id,
                            $row->segment_id,
                            $row->product_id,
                            $row->ean ?? $row->legacy_ean ?? '-',
                            $row->legacy_ean ?? '-',
                            $row->tenant_product_id_by_ean ?? '-',
                            (string) ($row->updated_at ?? '-'),
                        ])->all()
                    );

                    return;
                }
                foreach ($rows as $row) {
                    $effectiveEan = $row->ean ?? $row->legacy_ean ?? '';
                    $exportRows[] = [
                        'tenant_id' => $tenantId,
                        'tenant_name' => $tenant->name,
                        'layer_id' => $row->layer_id,
                        'segment_id' => $row->segment_id,
                        'product_id_atual' => $row->product_id,
                        'ean' => $effectiveEan,
                        'legacy_ean' => '',
                        'tenant_product_id_by_ean' => '',
                        'updated_at' => (string) ($row->updated_at ?? ''),
                    ];
                }

                $this->table(
                    ['layer_id', 'segment_id', 'product_id_atual', 'ean', 'updated_at'],
                    $rows->map(static fn ($row) => [
                        $row->layer_id,
                        $row->segment_id,
                        $row->product_id,
                        $row->ean ?? $row->legacy_ean ?? '-',
                        (string) ($row->updated_at ?? '-'),
                    ])->all()
                );
            };

            if ($shouldSwitchTenantContext) {
                $tenant->execute($run);
            } else {
                $run();
            }
        }

        $this->newLine();
        $this->info(sprintf('✅ Total geral de layers órfãos: %d', $grandTotal));

        if ($export === 'csv') {
            $exportInfo = $this->exportCsv($exportRows);
            $this->newLine();
            $this->info(sprintf('📄 CSV salvo em: %s', $exportInfo['path']));
            $this->info(sprintf('🔗 Link para download: %s', $exportInfo['url']));
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array{path: string, url: string}
     */
    private function exportCsv(array $rows): array
    {
        $filename = sprintf(
            'reports/layers-orphan-products-%s-%s.csv',
            now()->format('Ymd-His'),
            Str::lower(Str::random(6))
        );

        $headers = [
            'tenant_id',
            'tenant_name',
            'layer_id',
            'segment_id',
            'product_id_atual',
            'ean',
            'legacy_ean',
            'tenant_product_id_by_ean',
            'updated_at',
        ];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, array_map(static fn ($header) => $row[$header] ?? '', $headers));
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        Storage::disk('public')->put($filename, $csv);

        return [
            'path' => Storage::disk('public')->path($filename),
            'url' => url(Storage::disk('public')->url($filename)),
        ];
    }

    private function legacyConnectionIsAvailable(): bool
    {
        try {
            DB::connection('mysql_legacy')->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function getTenants(): Collection
    {
        $query = Tenant::query()->where('status', 'active');
        $tenantId = $this->option('tenant');
        if (is_string($tenantId) && $tenantId !== '') {
            $query->whereKey($tenantId);
        }

        return $query->get(['id', 'name', 'database']);
    }
}

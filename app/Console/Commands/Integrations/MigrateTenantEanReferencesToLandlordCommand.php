<?php

namespace App\Console\Commands\Integrations;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MigrateTenantEanReferencesToLandlordCommand extends Command
{
    protected $signature = 'sync:migrate-tenant-ean-references-to-landlord
        {--tenant= : Tenant ULID específico}
        {--chunk=1000 : Tamanho do lote por tenant}
        {--dry-run : Apenas exibe o resumo sem gravar}';

    protected $description = 'Consolida ean_references dos tenants no landlord usando regra de completude';

    /**
     * @var list<string>
     */
    private array $referenceColumns = [
        'ean',
        'category_id',
        'category_name',
        'category_slug',
        'reference_description',
        'brand',
        'subbrand',
        'packaging_type',
        'packaging_size',
        'measurement_unit',
        'width',
        'height',
        'depth',
        'weight',
        'unit',
        'has_dimensions',
        'dimension_status',
        'image_front_url',
        'image_side_url',
        'image_top_url',
        'metadata',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function handle(): int
    {
        if (! Schema::connection('landlord')->hasTable('ean_references')) {
            $this->error("❌ Tabela 'ean_references' não encontrada no landlord.");

            return self::FAILURE;
        }

        $tenants = $this->resolveTenants();
        if ($tenants->isEmpty()) {
            $this->warn('⚠️ Nenhum tenant ativo encontrado para consolidação.');

            return self::SUCCESS;
        }

        $chunk = max(100, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $collected = 0;
        $byEan = [];

        foreach ($tenants as $tenant) {
            $tenant->execute(function () use (&$byEan, &$collected, $chunk): void {
                if (! Schema::connection('tenant')->hasTable('ean_references')) {
                    return;
                }

                DB::connection('tenant')
                    ->table('ean_references')
                    ->orderBy('ean')
                    ->chunk($chunk, function ($rows) use (&$byEan, &$collected): void {
                        foreach ($rows as $row) {
                            $ean = preg_replace('/\D+/', '', (string) ($row->ean ?? '')) ?? '';
                            if ($ean === '') {
                                continue;
                            }

                            $collected++;

                            $candidate = [
                                'ean' => $ean,
                                'category_id' => is_string($row->category_id ?? null) ? trim((string) $row->category_id) : null,
                                'category_name' => is_string($row->category_name ?? null) ? trim((string) $row->category_name) : null,
                                'category_slug' => is_string($row->category_slug ?? null) ? trim((string) $row->category_slug) : null,
                                'reference_description' => $row->reference_description,
                                'brand' => $row->brand,
                                'subbrand' => $row->subbrand,
                                'packaging_type' => $row->packaging_type,
                                'packaging_size' => $row->packaging_size,
                                'measurement_unit' => $row->measurement_unit,
                                'width' => $row->width,
                                'height' => $row->height,
                                'depth' => $row->depth,
                                'weight' => $row->weight,
                                'unit' => $row->unit ?: 'cm',
                                'has_dimensions' => (bool) ($row->has_dimensions ?? false),
                                'dimension_status' => $row->dimension_status ?: 'draft',
                                'image_front_url' => $row->image_front_url ?? null,
                                'image_side_url' => $row->image_side_url ?? null,
                                'image_top_url' => $row->image_top_url ?? null,
                                'metadata' => $row->metadata ?? null,
                                'created_at' => $row->created_at,
                                'updated_at' => $row->updated_at,
                                'deleted_at' => null,
                            ];

                            if (! isset($byEan[$ean])) {
                                $byEan[$ean] = $candidate;

                                continue;
                            }

                            if ($this->completenessScore($candidate) > $this->completenessScore($byEan[$ean])) {
                                $byEan[$ean] = $candidate;
                            }
                        }
                    });
            });
        }

        $unique = count($byEan);
        $conflicts = max(0, $collected - $unique);

        if ($dryRun) {
            $this->info("👁️ Dry-run concluído: coletados {$collected}, únicos {$unique}, conflitos resolvíveis {$conflicts}.");

            return self::SUCCESS;
        }

        if ($unique > 0) {
            $now = now();
            $payload = [];

            foreach ($byEan as $row) {
                $row['id'] = (string) Str::ulid();
                $row['created_at'] = $row['created_at'] ?? $now;
                $row['updated_at'] = $now;

                $payload[] = Arr::only($row, array_merge(['id'], $this->referenceColumns));
            }

            DB::connection('landlord')
                ->table('ean_references')
                ->upsert(
                    $payload,
                    ['ean'],
                    array_diff($this->referenceColumns, ['ean', 'created_at'])
                );
        }

        $this->info("✅ Consolidação concluída: coletados {$collected}, únicos {$unique}, conflitos resolvidos {$conflicts}.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function resolveTenants(): Collection
    {
        $query = Tenant::query()->where('status', 'active');

        $tenantId = $this->option('tenant');
        if (is_string($tenantId) && $tenantId !== '') {
            $query->whereKey($tenantId);
        }

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function completenessScore(array $row): int
    {
        $score = 0;

        foreach ([
            'reference_description',
            'brand',
            'subbrand',
            'packaging_type',
            'packaging_size',
            'measurement_unit',
            'category_name',
            'category_slug',
            'image_front_url',
            'image_side_url',
            'image_top_url',
        ] as $column) {
            $value = $row[$column] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $score++;
            }
        }

        foreach (['width', 'height', 'depth', 'weight'] as $column) {
            $value = $row[$column] ?? null;
            if (is_numeric($value) && (float) $value > 0) {
                $score++;
            }
        }

        if (($row['has_dimensions'] ?? false) === true) {
            $score++;
        }

        if (is_string($row['category_id'] ?? null) && trim((string) $row['category_id']) !== '') {
            $score++;
        }

        if (is_array($row['metadata'] ?? null) && $row['metadata'] !== []) {
            $score++;
        }

        return $score;
    }
}

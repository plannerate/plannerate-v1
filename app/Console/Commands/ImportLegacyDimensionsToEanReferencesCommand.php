<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ImportLegacyDimensionsToEanReferencesCommand extends Command
{
    protected $signature = 'sync:import-legacy-dimensions-to-ean-references
        {--chunk=1000 : Tamanho do lote}
        {--dry-run : Só mostra quantos registros seriam processados}';

    protected $description = 'Importa dimensions da base legada para o catálogo global ean_references (landlord)';

    public function handle(): int
    {
        try {
            DB::connection('mysql_legacy')->getPdo();
            DB::connection('landlord')->getPdo();
        } catch (\Throwable $exception) {
            $this->error('❌ Falha ao conectar: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! Schema::connection('mysql_legacy')->hasTable('dimensions')) {
            $this->error("❌ Tabela 'dimensions' não encontrada na conexão mysql_legacy.");

            return self::FAILURE;
        }

        if (! Schema::connection('landlord')->hasTable('ean_references')) {
            $this->error("❌ Tabela 'ean_references' não encontrada na conexão landlord.");

            return self::FAILURE;
        }

        $referenceColumns = Schema::connection('landlord')->getColumnListing('ean_references');
        $dimensionStatusColumn = in_array('dimension_publish_status', $referenceColumns, true)
            ? 'dimension_publish_status'
            : (in_array('dimension_status', $referenceColumns, true) ? 'dimension_status' : null);

        if ($dimensionStatusColumn === null) {
            $this->error("❌ Nenhuma coluna de status de dimensão encontrada em 'ean_references' (esperado: dimension_publish_status ou dimension_status).");

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

        if ($this->option('dry-run')) {
            $this->info("👁️ Dry-run: {$total} registros seriam processados para o catálogo global.");

            return self::SUCCESS;
        }

        $processed = 0;
        $upserted = 0;

        (clone $baseQuery)->orderBy('ean')->chunk($chunkSize, function ($rows) use (&$processed, &$upserted, $dimensionStatusColumn): void {
            $now = now();
            $payload = [];
            $upsertColumns = ['width', 'height', 'depth', 'weight', 'unit', 'has_dimensions', $dimensionStatusColumn, 'updated_at', 'deleted_at'];

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

                $payload[$ean] = [
                    'id' => (string) Str::ulid(),
                    'ean' => $ean,
                    'width' => $width,
                    'height' => $height,
                    'depth' => $depth,
                    'weight' => $weight,
                    'unit' => $unit,
                    'has_dimensions' => $hasDimensions,
                    'updated_at' => $now,
                    'created_at' => $now,
                    'deleted_at' => null,
                ];

                $payload[$ean][$dimensionStatusColumn] = $dimensionStatus;
            }

            if ($payload !== []) {
                DB::connection('landlord')
                    ->table('ean_references')
                    ->upsert(
                        array_values($payload),
                        ['ean'],
                        $upsertColumns
                    );

                $upserted += count($payload);
            }
        });

        $this->info("✅ Importação concluída. Lidos: {$processed} | Upsert: {$upserted}");

        return self::SUCCESS;
    }

    /**
     * @return string Apenas os dígitos do EAN, sem espaços ou caracteres especiais
     */
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

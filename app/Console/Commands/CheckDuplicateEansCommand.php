<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckDuplicateEansCommand extends Command
{
    protected $signature = 'products:check-duplicate-eans 
                            {--limit=20 : Limite de resultados a exibir}
                            {--show-details : Mostrar detalhes dos produtos duplicados}';

    protected $description = 'Verifica se há EANs duplicados na tabela de produtos';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $showDetails = $this->option('show-details');

        $this->info('Verificando EANs duplicados...');
        $this->newLine();

        // Verifica duplicados considerando apenas EAN
        $duplicatesByEan = DB::table('products')
            ->select('ean', DB::raw('COUNT(*) as count'), DB::raw('COUNT(DISTINCT tenant_id) as tenants'))
            ->whereNotNull('ean')
            ->where('ean', '!=', '')
            ->groupBy('ean')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();

        if ($duplicatesByEan->isEmpty()) {
            $this->info('✅ Nenhum EAN duplicado encontrado!');
            return self::SUCCESS;
        }

        $this->warn("⚠️  Encontrados {$duplicatesByEan->count()} EANs duplicados:");
        $this->newLine();

        $headers = ['EAN', 'Quantidade', 'Tenants Diferentes'];
        $rows = $duplicatesByEan->map(fn ($dup) => [
            $dup->ean,
            $dup->count,
            $dup->tenants,
        ])->toArray();

        $this->table($headers, $rows);

        // Verifica violações da constraint única (tenant_id + ean)
        $this->newLine();
        $this->info('Verificando violações da constraint única (tenant_id + ean)...');

        $violations = DB::select("
            SELECT tenant_id, ean, COUNT(*) as count
            FROM products
            WHERE ean IS NOT NULL AND ean != ''
            GROUP BY tenant_id, ean
            HAVING COUNT(*) > 1
            LIMIT ?
        ", [$limit]);

        if (count($violations) > 0) {
            $this->error("❌ Violações da constraint única encontradas: " . count($violations));
            $this->newLine();

            $violationHeaders = ['Tenant ID', 'EAN', 'Quantidade'];
            $violationRows = array_map(fn ($v) => [
                $v->tenant_id ?? 'NULL',
                $v->ean,
                $v->count,
            ], $violations);

            $this->table($violationHeaders, $violationRows);
        } else {
            $this->info('✅ Nenhuma violação da constraint única!');
        }

        // Mostra detalhes se solicitado
        if ($showDetails && $duplicatesByEan->isNotEmpty()) {
            $this->newLine();
            $this->info('Detalhes dos produtos duplicados:');
            $this->newLine();

            foreach ($duplicatesByEan->take(5) as $dup) {
                $this->line("EAN: {$dup->ean} (aparece {$dup->count} vezes)");
                
                $products = DB::table('products')
                    ->where('ean', $dup->ean)
                    ->select('id', 'name', 'codigo_erp', 'tenant_id', 'created_at')
                    ->get();

                $details = [];
                foreach ($products as $p) {
                    $details[] = [
                        'ID' => $p->id,
                        'Nome' => substr($p->name ?? 'N/A', 0, 40),
                        'Código ERP' => $p->codigo_erp ?? 'NULL',
                        'Tenant' => $p->tenant_id ?? 'NULL',
                        'Criado em' => $p->created_at ?? 'N/A',
                    ];
                }

                $this->table(
                    ['ID', 'Nome', 'Código ERP', 'Tenant', 'Criado em'],
                    $details
                );
                $this->newLine();
            }
        }

        return self::SUCCESS;
    }
}


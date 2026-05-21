<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoPlanogramDimensionsReportCommand extends Command
{
    protected $signature = 'auto-planogram:dimensions-report
        {--tenant= : Slug ou ID do tenant}
        {--category= : ID da categoria raiz (inclui descendentes)}
        {--missing : Lista EANs e nomes dos produtos sem dimensão}
        {--limit=50 : Limite de produtos listados com --missing}';

    protected $description = 'Relatório de cobertura de dimensões (width/height) por categoria para o auto-planograma';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        $tenants = $tenantId !== null
            ? Tenant::on('landlord')
                ->where(fn ($q) => $q->where('id', $tenantId)->orWhere('slug', $tenantId))
                ->get()
            : Tenant::on('landlord')->orderBy('name')->get();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $this->newLine();
            $this->info("🏢 {$tenant->name} ({$tenant->id})");

            $tenant->execute(fn () => $this->reportForTenant());
        }

        return self::SUCCESS;
    }

    private function reportForTenant(): void
    {
        $categoryId = $this->option('category');
        $showMissing = (bool) $this->option('missing');
        $limit = max(1, (int) $this->option('limit'));

        $categoryIds = $categoryId !== null
            ? Category::getDescendantIds($categoryId)
            : null;

        $rows = Product::query()
            ->select([
                'category_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN width > 0 AND height > 0 THEN 1 ELSE 0 END) as com_dimensao'),
                DB::raw('SUM(CASE WHEN width IS NULL OR width <= 0 OR height IS NULL OR height <= 0 THEN 1 ELSE 0 END) as sem_dimensao'),
            ])
            ->where('status', '!=', 'draft')
            ->when($categoryIds !== null, fn ($q) => $q->whereIn('category_id', $categoryIds))
            ->groupBy('category_id')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('  Nenhum produto encontrado para os critérios fornecidos.');

            return;
        }

        $categoryNames = Category::query()
            ->whereIn('id', $rows->pluck('category_id')->filter()->all())
            ->pluck('name', 'id');

        $tableRows = $rows->map(fn ($row) => [
            $categoryNames[$row->category_id] ?? ($row->category_id ?? '(sem categoria)'),
            (int) $row->total,
            (int) $row->com_dimensao,
            (int) $row->sem_dimensao,
            $row->total > 0
                ? number_format((int) $row->com_dimensao / (int) $row->total * 100, 1).'%'
                : '—',
        ])->sortByDesc(fn ($r) => $r[3])->values()->toArray();

        $this->table(
            ['Categoria', 'Total', 'Com dimensão', 'Sem dimensão', '% Cobertura'],
            $tableRows,
        );

        $totalAll = $rows->sum('total');
        $totalCom = $rows->sum('com_dimensao');
        $totalSem = $rows->sum('sem_dimensao');
        $pct = $totalAll > 0 ? number_format($totalCom / $totalAll * 100, 1).'%' : '—';

        $this->line("  <fg=cyan>Total: {$totalAll} | Com dimensão: {$totalCom} | Sem dimensão: {$totalSem} | Cobertura: {$pct}</>");

        if ($showMissing && $totalSem > 0) {
            $this->newLine();
            $this->line("  <fg=yellow>Produtos sem dimensão (limit {$limit}):</>");

            Product::query()
                ->select(['id', 'name', 'ean'])
                ->where('status', '!=', 'draft')
                ->where(fn ($q) => $q->whereNull('width')->orWhere('width', '<=', 0)
                    ->orWhereNull('height')->orWhere('height', '<=', 0))
                ->when($categoryIds !== null, fn ($q) => $q->whereIn('category_id', $categoryIds))
                ->limit($limit)
                ->get()
                ->each(fn ($p) => $this->line("    [{$p->ean}] {$p->name}"));
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Gondola;
use App\Models\Tenant;
use App\Models\WorkflowGondolaExecution;
use App\Models\WorkflowHistory;
use Callcocam\LaravelRaptorPlannerate\Models\GondolaAnalysis;
use Callcocam\LaravelRaptorPlannerate\Models\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramRejectedProduct;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Segment;
use Callcocam\LaravelRaptorPlannerate\Models\Shelf;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;

class CleanGondolaRelationshipsCommand extends Command
{
    protected $signature = 'tenant:clean-gondolas
        {--tenant=* : ID ou slug dos tenants (pode repetir ou separar por vírgula)}
        {--dry-run  : Mostra o que seria deletado sem deletar}
        {--force    : Não pede confirmação}';

    protected $description = 'Limpa registros órfãos relacionados a gôndolas deletadas (sections, shelves, segments, layers, executions, etc.)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('⚠️  Modo DRY-RUN — nenhum registro será deletado.');
            $this->newLine();
        }

        $tenants = $this->resolveTenants();

        if (empty($tenants)) {
            $this->warn('Nenhum tenant selecionado.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info(sprintf('🏢 %d tenant(s) selecionado(s).', count($tenants)));

        foreach ($tenants as $tenant) {
            $this->newLine();
            $this->line('═══════════════════════════════════════════════════════');
            $this->info("🔄 {$tenant->name}");

            $tenant->makeCurrent();

            $this->cleanTenant($dryRun);

            Tenant::forgetCurrent();
        }

        $this->newLine();
        $this->info($dryRun ? '✅ Análise concluída (nada foi deletado).' : '✅ Limpeza concluída!');

        return self::SUCCESS;
    }

    private function cleanTenant(bool $dryRun): void
    {
        $deletedGondolaIds = Gondola::onlyTrashed()->pluck('id');

        if ($deletedGondolaIds->isEmpty()) {
            $this->line('  <fg=gray>–  Nenhuma gôndola deletada encontrada.</>');
        } else {
            $this->cleanDeletedGondolas($deletedGondolaIds, $dryRun);
        }

        $this->cleanOrphanedRecords($dryRun);
    }

    private function cleanDeletedGondolas(Collection $gondolaIds, bool $dryRun): void
    {
        $this->line("  📦 {$gondolaIds->count()} gôndola(s) deletada(s) encontrada(s).");

        $sectionIds = Section::withTrashed()->whereIn('gondola_id', $gondolaIds)->pluck('id');
        $shelfIds = Shelf::withTrashed()->whereIn('section_id', $sectionIds)->pluck('id');
        $segmentIds = Segment::withTrashed()->whereIn('shelf_id', $shelfIds)->pluck('id');
        $executionIds = WorkflowGondolaExecution::withTrashed()->whereIn('gondola_id', $gondolaIds)->pluck('id');

        $counts = [
            'layers' => Layer::withTrashed()->whereIn('segment_id', $segmentIds)->whereNull('deleted_at')->count(),
            'segments' => Segment::withTrashed()->whereIn('shelf_id', $shelfIds)->whereNull('deleted_at')->count(),
            'shelves' => Shelf::withTrashed()->whereIn('section_id', $sectionIds)->whereNull('deleted_at')->count(),
            'sections' => Section::withTrashed()->whereIn('gondola_id', $gondolaIds)->whereNull('deleted_at')->count(),
            'gondola_analyses' => GondolaAnalysis::withTrashed()->whereIn('gondola_id', $gondolaIds)->whereNull('deleted_at')->count(),
            'workflow_histories' => WorkflowHistory::withTrashed()->whereIn('workflow_gondola_execution_id', $executionIds)->whereNull('deleted_at')->count(),
            'workflow_executions' => WorkflowGondolaExecution::withTrashed()->whereIn('gondola_id', $gondolaIds)->whereNull('deleted_at')->count(),
            'rejected_products' => PlanogramRejectedProduct::whereIn('gondola_id', $gondolaIds)->count(),
        ];

        $totalOrphans = array_sum($counts);

        if ($totalOrphans === 0) {
            $this->line('  <fg=gray>–  Nenhum registro órfão encontrado.</>');

            return;
        }

        $this->table(
            ['Tabela', 'Registros órfãos'],
            collect($counts)->filter()->map(fn ($c, $k) => [$k, $c])->values()->toArray()
        );

        if ($dryRun) {
            return;
        }

        if (! $this->option('force')) {
            $confirmed = confirm(
                label: "  Confirma a limpeza de {$totalOrphans} registro(s) órfão(s)?",
                default: false,
            );

            if (! $confirmed) {
                $this->line('  <fg=yellow>⏭  Pulado.</>');

                return;
            }
        }

        Layer::whereIn('segment_id', $segmentIds)->whereNull('deleted_at')->delete();
        Segment::whereIn('shelf_id', $shelfIds)->whereNull('deleted_at')->delete();
        Shelf::whereIn('section_id', $sectionIds)->whereNull('deleted_at')->delete();
        Section::whereIn('gondola_id', $gondolaIds)->whereNull('deleted_at')->delete();
        GondolaAnalysis::whereIn('gondola_id', $gondolaIds)->whereNull('deleted_at')->delete();
        WorkflowHistory::whereIn('workflow_gondola_execution_id', $executionIds)->whereNull('deleted_at')->delete();
        WorkflowGondolaExecution::whereIn('gondola_id', $gondolaIds)->whereNull('deleted_at')->delete();
        PlanogramRejectedProduct::whereIn('gondola_id', $gondolaIds)->delete();

        $this->info("  ✅ {$totalOrphans} registro(s) removido(s).");
    }

    /**
     * Limpa registros cujo gondola_id não existe nem mesmo como soft-deleted.
     * Situação rara, mas possível se gondolas foram force-deleted diretamente no banco.
     */
    private function cleanOrphanedRecords(bool $dryRun): void
    {
        $allGondolaIds = Gondola::withTrashed()->pluck('id');

        $orphanedExecCount = WorkflowGondolaExecution::withTrashed()->whereNotIn('gondola_id', $allGondolaIds)->count();
        $orphanedAnalysisCount = GondolaAnalysis::withTrashed()->whereNotIn('gondola_id', $allGondolaIds)->count();
        $orphanedRejectedCount = PlanogramRejectedProduct::whereNotIn('gondola_id', $allGondolaIds)->count();

        $total = $orphanedExecCount + $orphanedAnalysisCount + $orphanedRejectedCount;

        if ($total === 0) {
            $this->line('  <fg=gray>–  Nenhum registro totalmente órfão encontrado.</>');

            return;
        }

        $this->warn("  ⚠️  {$total} registro(s) totalmente órfão(s) (gondola_id inexistente):");
        $this->line("    executions: {$orphanedExecCount} | analyses: {$orphanedAnalysisCount} | rejected_products: {$orphanedRejectedCount}");

        if ($dryRun) {
            return;
        }

        if (! $this->option('force')) {
            $confirmed = confirm(label: "  Deletar esses {$total} registro(s) órfão(s)?", default: false);

            if (! $confirmed) {
                $this->line('  <fg=yellow>⏭  Pulado.</>');

                return;
            }
        }

        $orphanedExecIds = WorkflowGondolaExecution::withTrashed()
            ->whereNotIn('gondola_id', $allGondolaIds)
            ->pluck('id');

        WorkflowHistory::whereIn('workflow_gondola_execution_id', $orphanedExecIds)->forceDelete();
        WorkflowGondolaExecution::withTrashed()->whereNotIn('gondola_id', $allGondolaIds)->forceDelete();
        GondolaAnalysis::withTrashed()->whereNotIn('gondola_id', $allGondolaIds)->forceDelete();
        PlanogramRejectedProduct::whereNotIn('gondola_id', $allGondolaIds)->delete();

        $this->info("  ✅ {$total} registro(s) totalmente órfão(s) removido(s).");
    }

    /** @return list<Tenant> */
    private function resolveTenants(): array
    {
        $tenantOptions = $this->option('tenant');

        if (! empty($tenantOptions)) {
            $ids = collect($tenantOptions)
                ->flatMap(fn (string $v) => explode(',', $v))
                ->map(fn (string $v) => trim($v))
                ->filter()
                ->values()
                ->toArray();

            return Tenant::on('landlord')
                ->where(fn ($q) => $q->whereIn('id', $ids)->orWhereIn('slug', $ids))
                ->get()
                ->all();
        }

        $all = Tenant::on('landlord')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'database']);

        if ($all->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return [];
        }

        $selected = multiselect(
            label: 'Selecione os tenants para limpar',
            options: $all->pluck('name', 'id')->toArray(),
            hint: 'Use espaço para selecionar, enter para confirmar',
        );

        return $all->whereIn('id', $selected)->values()->all();
    }
}

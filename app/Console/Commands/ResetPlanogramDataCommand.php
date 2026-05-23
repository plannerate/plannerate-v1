<?php

namespace App\Console\Commands;

use App\Models\Gondola;
use App\Models\GondolaSlotOverride;
use App\Models\Planogram;
use App\Models\PlanogramRejectedProduct;
use App\Models\PlanogramSubtemplate;
use App\Models\PlanogramTemplate;
use App\Models\PlanogramTemplateSlot;
use App\Models\Tenant;
use App\Models\WorkflowGondolaExecution;
use App\Models\WorkflowHistory;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\GondolaAnalysis;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Segment;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\SegmentNote;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Shelf;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;

class ResetPlanogramDataCommand extends Command
{
    protected $signature = 'plannerate:reset-data
        {--tenant=* : ID ou slug dos tenants (pode repetir ou separar por vírgula)}
        {--force    : Pular confirmação}
        {--dry-run  : Mostra o que seria deletado sem deletar}';

    protected $description = 'Apaga todos os templates e gôndolas, preservando apenas os planogramas';

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
            $this->info("🔄 Tenant: {$tenant->name}");

            $tenant->makeCurrent();

            try {
                $this->resetTenant($dryRun);
            } finally {
                Tenant::forgetCurrent();
            }
        }

        $this->newLine();
        $this->info($dryRun ? '✅ Análise concluída (nada foi deletado).' : '✅ Reset concluído!');

        return self::SUCCESS;
    }

    /**
     * Coleta contagens e executa o reset completo para o tenant atual.
     */
    private function resetTenant(bool $dryRun): void
    {
        // Coleta todos os IDs antes de qualquer deleção para evitar inconsistências
        $gondolaIds = Gondola::withTrashed()->pluck('id');
        $sectionIds = Section::withTrashed()->whereIn('gondola_id', $gondolaIds)->pluck('id');
        $shelfIds = Shelf::withTrashed()->whereIn('section_id', $sectionIds)->pluck('id');
        $segmentIds = Segment::withTrashed()->whereIn('shelf_id', $shelfIds)->pluck('id');
        $executionIds = WorkflowGondolaExecution::withTrashed()->whereIn('gondola_id', $gondolaIds)->pluck('id');
        $templateIds = PlanogramTemplate::withTrashed()->pluck('id');
        $subtemplateIds = PlanogramSubtemplate::withTrashed()->whereIn('template_id', $templateIds)->pluck('id');

        $counts = [
            'workflow_histories' => WorkflowHistory::withTrashed()->whereIn('workflow_gondola_execution_id', $executionIds)->count(),
            'workflow_executions' => WorkflowGondolaExecution::withTrashed()->whereIn('gondola_id', $gondolaIds)->count(),
            'rejected_products' => PlanogramRejectedProduct::whereIn('gondola_id', $gondolaIds)->count(),
            'slot_overrides' => GondolaSlotOverride::whereIn('gondola_id', $gondolaIds)->count(),
            'gondola_analyses' => GondolaAnalysis::withTrashed()->whereIn('gondola_id', $gondolaIds)->count(),
            'segment_notes' => SegmentNote::withTrashed()->whereIn('segment_id', $segmentIds)->count(),
            'layers' => Layer::withTrashed()->whereIn('segment_id', $segmentIds)->count(),
            'segments' => Segment::withTrashed()->whereIn('shelf_id', $shelfIds)->count(),
            'shelves' => Shelf::withTrashed()->whereIn('section_id', $sectionIds)->count(),
            'sections' => Section::withTrashed()->whereIn('gondola_id', $gondolaIds)->count(),
            'gondolas' => $gondolaIds->count(),
            'template_slots' => PlanogramTemplateSlot::withTrashed()->whereIn('subtemplate_id', $subtemplateIds)->count(),
            'subtemplates' => $subtemplateIds->count(),
            'templates' => $templateIds->count(),
        ];

        $total = array_sum($counts);

        if ($total === 0) {
            $this->line('  <fg=gray>–  Nenhum dado para apagar.</>');

            return;
        }

        $this->table(
            ['Tabela', 'Registros'],
            collect($counts)->filter()->map(fn ($c, $k) => [$k, $c])->values()->toArray(),
        );

        if ($dryRun) {
            return;
        }

        if (! $this->option('force')) {
            $confirmed = confirm(
                label: "  Confirma o reset de {$total} registro(s)? Esta ação é IRREVERSÍVEL.",
                default: false,
            );

            if (! $confirmed) {
                $this->line('  <fg=yellow>⏭  Pulado.</>');

                return;
            }
        }

        $this->deleteGondolaData($gondolaIds, $sectionIds, $shelfIds, $segmentIds, $executionIds);
        $this->deleteTemplateData($templateIds, $subtemplateIds);
        $this->nullifyPlanogramTemplateRefs();

        $this->info("  ✅ {$total} registro(s) removido(s).");
    }

    /**
     * Remove todas as gôndolas e seus relacionamentos (força hard-delete para ignorar soft-deletes existentes).
     *
     * @param  Collection<int, string>  $gondolaIds
     * @param  Collection<int, string>  $sectionIds
     * @param  Collection<int, string>  $shelfIds
     * @param  Collection<int, string>  $segmentIds
     * @param  Collection<int, string>  $executionIds
     */
    private function deleteGondolaData(
        Collection $gondolaIds,
        Collection $sectionIds,
        Collection $shelfIds,
        Collection $segmentIds,
        Collection $executionIds,
    ): void {
        if ($gondolaIds->isEmpty()) {
            return;
        }

        // Folhas primeiro, raiz por último
        WorkflowHistory::withTrashed()->whereIn('workflow_gondola_execution_id', $executionIds)->forceDelete();
        WorkflowGondolaExecution::withTrashed()->whereIn('gondola_id', $gondolaIds)->forceDelete();
        PlanogramRejectedProduct::whereIn('gondola_id', $gondolaIds)->delete();
        GondolaSlotOverride::whereIn('gondola_id', $gondolaIds)->delete();
        GondolaAnalysis::withTrashed()->whereIn('gondola_id', $gondolaIds)->forceDelete();
        SegmentNote::withTrashed()->whereIn('segment_id', $segmentIds)->forceDelete();
        Layer::withTrashed()->whereIn('segment_id', $segmentIds)->forceDelete();
        Segment::withTrashed()->whereIn('shelf_id', $shelfIds)->forceDelete();
        Shelf::withTrashed()->whereIn('section_id', $sectionIds)->forceDelete();
        Section::withTrashed()->whereIn('gondola_id', $gondolaIds)->forceDelete();
        Gondola::withTrashed()->whereIn('id', $gondolaIds)->forceDelete();

        $this->line('  <fg=green>✓  Gôndolas e relacionamentos removidos.</>');
    }

    /**
     * Remove todos os templates e seus slots/subtemplates (cascata das folhas para a raiz).
     *
     * @param  Collection<int, string>  $templateIds
     * @param  Collection<int, string>  $subtemplateIds
     */
    private function deleteTemplateData(Collection $templateIds, Collection $subtemplateIds): void
    {
        if ($templateIds->isEmpty()) {
            return;
        }

        PlanogramTemplateSlot::withTrashed()->whereIn('subtemplate_id', $subtemplateIds)->forceDelete();
        PlanogramSubtemplate::withTrashed()->whereIn('template_id', $templateIds)->forceDelete();
        PlanogramTemplate::withTrashed()->whereIn('id', $templateIds)->forceDelete();

        $this->line('  <fg=green>✓  Templates e slots removidos.</>');
    }

    /**
     * Remove a referência de template dos planogramas remanescentes para evitar registros órfãos.
     */
    private function nullifyPlanogramTemplateRefs(): void
    {
        Planogram::withTrashed()->whereNotNull('template_id')->update(['template_id' => null]);
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
            label: 'Selecione os tenants para o reset',
            options: $all->pluck('name', 'id')->toArray(),
            hint: 'Use espaço para selecionar, enter para confirmar',
        );

        return $all->whereIn('id', $selected)->values()->all();
    }
}

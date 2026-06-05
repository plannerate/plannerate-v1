<template>
    <!-- Dropdown Performance -->
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm" class="gap-1.5 rounded-lg">
                <Gauge class="size-4 text-amber-500" />
                {{ t('plannerate.dropdown.performance.title') }}
                <ChevronDown class="size-3 text-muted-foreground" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-64" style="z-index: 9999;">
            <DropdownMenuItem @click="showPerformanceModal = true">
                <Gauge class="mr-2 size-4" />
                {{ t('plannerate.dropdown.performance.open_analyses') }}
            </DropdownMenuItem>
            <DropdownMenuSeparator />

            <!-- Controles Individuais -->
            <DropdownMenuItem @click="performance.abc.toggleVisibility()" :disabled="!performance.abc.hasData.value">
                <Eye v-if="!performance.abc.isVisible.value" class="mr-2 size-4 text-green-600" />
                <EyeOff v-else class="mr-2 size-4 text-green-600" />
                {{ performance.abc.isVisible.value ? t('plannerate.dropdown.performance.hide') : t('plannerate.dropdown.performance.show') }}
                {{ t('plannerate.dropdown.performance.assortment_analysis') }}
                <span class="ml-auto text-xs text-muted-foreground">
                    ({{ performance.abc.stats.value.total }})
                </span>
            </DropdownMenuItem>

            <DropdownMenuItem @click="performance.targetStock.toggleVisibility()" :disabled="!performance.targetStock.hasData.value">
                <Eye v-if="!performance.targetStock.isVisible.value" class="mr-2 size-4 text-blue-600" />
                <EyeOff v-else class="mr-2 size-4 text-blue-600" />
                {{ performance.targetStock.isVisible.value ? t('plannerate.dropdown.performance.hide') : t('plannerate.dropdown.performance.show') }}
                {{ t('plannerate.dropdown.performance.target_stock') }}
                <span class="ml-auto text-xs text-muted-foreground">
                    ({{ performance.targetStock.stats.value.total }})
                </span>
            </DropdownMenuItem>

            <DropdownMenuItem @click="performance.paper.toggleVisibility()" :disabled="!performance.paper.hasData.value">
                <Eye v-if="!performance.paper.isVisible.value" class="mr-2 size-4 text-amber-500" />
                <EyeOff v-else class="mr-2 size-4 text-amber-500" />
                {{ performance.paper.isVisible.value ? t('plannerate.dropdown.performance.hide') : t('plannerate.dropdown.performance.show') }}
                {{ t('plannerate.dropdown.performance.paper_analysis') }}
                <span class="ml-auto text-xs text-muted-foreground">
                    ({{ performance.paper.stats.value.total }})
                </span>
            </DropdownMenuItem>

            <DropdownMenuSeparator />

            <!-- Controle Geral -->
            <DropdownMenuItem @click="performance.toggleAllIndicators()" :disabled="!performance.hasAnyData.value">
                <Eye v-if="!performance.anyVisible.value" class="mr-2 size-4" />
                <EyeOff v-else class="mr-2 size-4" />
                {{ performance.anyVisible.value ? t('plannerate.dropdown.performance.hide') : t('plannerate.dropdown.performance.show') }}
                {{ t('plannerate.dropdown.performance.all') }}
            </DropdownMenuItem>

            <DropdownMenuSeparator />

            <DropdownMenuItem @click="performance.clearAllAnalysis(gondola.id)" :disabled="!performance.hasAnyData.value" class="text-destructive">
                <Trash2 class="mr-2 size-4" />
                {{ t('plannerate.dropdown.performance.clear_all') }}
            </DropdownMenuItem>

            <DropdownMenuSeparator />

            <!-- Exportar Relatórios CSV -->
            <DropdownMenuItem @click="handleExportAbc" :disabled="!performance.abc.hasData.value">
                <Download class="mr-2 size-4 text-green-600" />
                {{ t('plannerate.dropdown.performance.export_abc') }}
            </DropdownMenuItem>

            <DropdownMenuItem @click="handleExportStock" :disabled="!performance.targetStock.hasData.value">
                <Download class="mr-2 size-4 text-blue-600" />
                {{ t('plannerate.dropdown.performance.export_stock') }}
            </DropdownMenuItem>

            <DropdownMenuItem @click="handleExportPaper" :disabled="!performance.paper.hasData.value">
                <Download class="mr-2 size-4 text-amber-500" />
                {{ t('plannerate.dropdown.performance.export_paper') }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>

    <!-- Performance Modal -->
    <Performance v-model:open="showPerformanceModal" :gondola-id="gondola.id" :planogram="planogram" :analysis="props.analysis" />
</template>

<script setup lang="ts">
import { ChevronDown, Download, Eye, EyeOff, Gauge, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import Performance from '@/components/plannerate/header/Performance.vue';
import type { AbcResult } from '@/components/plannerate/analysis/abc/types';
import type { PaperResult } from '@/components/plannerate/analysis/paper/types';
import type { TargetStockResult } from '@/components/plannerate/analysis/target-stock/types';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAnalysisExport } from '@/composables/plannerate/analysis/useAnalysisExport';
import { usePerformanceIndicators } from '@/composables/plannerate/analysis/usePerformanceIndicators';
import { useT } from '@/composables/useT';
import type { AbcAnalysis, Gondola, StockAnalysis } from '@/types/planogram';

const props = defineProps<{
    gondola: Pick<Gondola, 'id' | 'planogram'>;
    analysis?: {
        abc?: AbcAnalysis;
        stock?: StockAnalysis;
        paper?: { results?: PaperResult[] };
        [key: string]: any;
    };
}>();

const showPerformanceModal = ref(false);
const { t } = useT();
const { exportAbcToCsv, exportStockToCsv, exportPaperToCsv } = useAnalysisExport();

const performance = usePerformanceIndicators();

const getStorageKey = (gondolaId: string) => `plannerate:performance:visibility:${gondolaId}`;

interface PerformanceVisibilityPreferences {
    abcVisible: boolean;
    targetStockVisible: boolean;
    paperVisible: boolean;
}

const saveVisibilityPreferences = (): void => {
    if (typeof window === 'undefined') return;

    const payload: PerformanceVisibilityPreferences = {
        abcVisible: performance.abc.isVisible.value,
        targetStockVisible: performance.targetStock.isVisible.value,
        paperVisible: performance.paper.isVisible.value,
    };

    window.localStorage.setItem(getStorageKey(props.gondola.id), JSON.stringify(payload));
};

const loadVisibilityPreferences = (): void => {
    if (typeof window === 'undefined') return;

    const raw = window.localStorage.getItem(getStorageKey(props.gondola.id));
    if (!raw) return;

    try {
        const parsed = JSON.parse(raw) as Partial<PerformanceVisibilityPreferences>;

        if (typeof parsed.abcVisible === 'boolean') performance.abc.setVisibility(parsed.abcVisible);
        if (typeof parsed.targetStockVisible === 'boolean') performance.targetStock.setVisibility(parsed.targetStockVisible);
        if (typeof parsed.paperVisible === 'boolean') performance.paper.setVisibility(parsed.paperVisible);
    } catch {
        window.localStorage.removeItem(getStorageKey(props.gondola.id));
    }
};

// Mudança de gôndola — limpar análises antigas
watch(
    () => props.gondola.id,
    (newId, oldId) => {
        if (newId !== oldId && oldId !== undefined) {
            performance.abc.clearClassifications();
            performance.targetStock.clearTargetStockData();
            performance.paper.clearPaperRoles();
            loadVisibilityPreferences();
        }
    },
);

// Carregar classificações ABC
watch(
    () => props.analysis?.abc,
    (analysis) => {
        performance.abc.clearClassifications();
        if (analysis?.results?.length) {
            performance.abc.setClassifications(analysis.results);
        }
    },
    { immediate: true },
);

// Carregar dados de Target Stock
watch(
    () => props.analysis?.stock,
    (analysis) => {
        performance.targetStock.clearTargetStockData();
        if (analysis?.results?.length) {
            performance.targetStock.setTargetStockDataBatch(analysis.results as any[]);
        }
    },
    { immediate: true },
);

// Carregar papéis estratégicos (Análise de Papel)
watch(
    () => props.analysis?.paper,
    (analysis) => {
        performance.paper.clearPaperRoles();
        if (analysis?.results?.length) {
            performance.paper.setPaperRoles(
                (analysis.results as PaperResult[])
                    .filter((r) => r.ean && r.role)
                    .map((r) => ({ ean: r.ean, role: r.role })),
            );
        }
    },
    { immediate: true },
);

// Persistir preferências de visibilidade
watch(
    [performance.abc.isVisible, performance.targetStock.isVisible, performance.paper.isVisible],
    () => saveVisibilityPreferences(),
);

onMounted(() => loadVisibilityPreferences());

const planogram = computed(() => {
    const pg = props.gondola.planogram;
    if (pg && 'id' in pg && 'name' in pg) return pg as any;
    return null;
});

function handleExportAbc(): void {
    const results = props.analysis?.abc?.results;
    if (!results?.length) return;
    exportAbcToCsv(results as AbcResult[]);
}

function handleExportStock(): void {
    const results = props.analysis?.stock?.results;
    if (!results?.length) return;
    exportStockToCsv(results as TargetStockResult[]);
}

function handleExportPaper(): void {
    const results = props.analysis?.paper?.results;
    if (!results?.length) return;
    exportPaperToCsv(results as PaperResult[]);
}
</script>

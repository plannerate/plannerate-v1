<template>
    <Dialog :open="isOpen" @update:open="handleOpenChange">
        <DialogContent
            class="max-w-[95vw] xl:max-w-[80vw] max-h-[95vh] w-full h-full overflow-hidden flex flex-col p-0 z-[500]">
            <DialogHeader class="px-4 pt-4 pb-2">
                <DialogTitle class="flex items-center gap-2 text-lg">
                    <Gauge class="size-4" />
                    {{ t('plannerate.performance.title') }}
                </DialogTitle>
                <DialogDescription class="text-xs">
                    {{ t('plannerate.performance.description') }}
                </DialogDescription>
            </DialogHeader>

            <Tabs v-model="activeTab" class="flex-1 flex flex-col overflow-hidden px-4 pb-4">
                <TabsList :class="['grid w-full h-9', gridColsClass]">
                    <TabsTrigger v-for="tab in visibleTabs" :key="tab.value" :value="tab.value">
                        <div class="flex items-center gap-1.5">
                            <component :is="tab.icon" class="size-3.5 shrink-0" />
                            <span class="leading-tight">{{ t(tab.labelKey) }}</span>
                        </div>
                    </TabsTrigger>
                </TabsList>

                <TabsContent v-if="isTabVisible('abc')" value="abc" class="flex-1 overflow-auto mt-2">
                    <PerformanceAbcTab :gondola-id="gondolaId" :planogram="planogram" :results="analysis?.abc?.results" />
                </TabsContent>

                <TabsContent v-if="isTabVisible('target-stock')" value="target-stock" class="flex-1 overflow-auto mt-2">
                    <PerformanceTargetStockTab :gondola-id="gondolaId" :planogram="planogram" :results="analysis?.stock?.results" />
                </TabsContent>

                <TabsContent v-if="isTabVisible('paper')" value="paper" class="flex-1 overflow-auto mt-2">
                    <PerformancePaperTab :gondola-id="gondolaId" :planogram="planogram" :results="analysis?.paper?.results" />
                </TabsContent>

                <TabsContent v-if="isTabVisible('bcg')" value="bcg" class="flex-1 overflow-auto mt-2">
                    <PerformanceBcgTab :gondola-id="gondolaId" :planogram="planogram" :results="analysis?.bcg?.results" />
                </TabsContent>
            </Tabs>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { BarChart3, Gauge, Grid2x2, Package, TrendingUp } from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed, ref, watch } from 'vue';
import { isAnalysisVisible  } from '@/components/plannerate/analysis/visibility';
import type {AnalysisKey} from '@/components/plannerate/analysis/visibility';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useT } from '@/composables/useT';
import type { AbcAnalysis, StockAnalysis } from '@/types/planogram';
import PerformanceAbcTab from './PerformanceAbcTab.vue';
import PerformanceBcgTab from './PerformanceBcgTab.vue';
import PerformancePaperTab from './PerformancePaperTab.vue';
import PerformanceTargetStockTab from './PerformanceTargetStockTab.vue';

interface Planogram {
    id: string;
    name: string;
    tenant_id?: string;
    start_date?: string;
    end_date?: string;
}

interface Props {
    open: boolean;
    gondolaId?: string | null;
    planogram?: Planogram | null;
    analysis?: {
        abc?: AbcAnalysis;
        stock?: StockAnalysis;
        [key: string]: any; // Permite outras análises futuras sem quebrar o componente
     };
}

interface Emits {
    (e: 'update:open', value: boolean): void;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
    gondolaId: null,
    planogram: null,
});

const emit = defineEmits<Emits>();
const { t } = useT();

/**
 * Configuração de visibilidade das abas de análise.
 *
 * Ponto único de liga/desliga: mudar `visible` mostra/esconde a aba e ajusta a grade
 * automaticamente. Hoje é local; no futuro dá para alimentar cada `visible` a partir de
 * uma prop vinda do backend sem tocar no template.
 */
interface PerformanceTab {
    value: AnalysisKey;
    labelKey: string;
    icon: Component;
}

const TABS: PerformanceTab[] = [
    { value: 'abc', labelKey: 'plannerate.performance.abc_tab', icon: BarChart3 },
    { value: 'target-stock', labelKey: 'plannerate.performance.target_stock', icon: Package },
    { value: 'paper', labelKey: 'plannerate.performance.paper_tab', icon: TrendingUp },
    { value: 'bcg', labelKey: 'plannerate.performance.bcg_tab', icon: Grid2x2 },
];

const visibleTabs = computed(() => TABS.filter((tab) => isAnalysisVisible(tab.value)));

const isTabVisible = (value: string): boolean => visibleTabs.value.some((tab) => tab.value === value);

/** Tailwind não aceita classe dinâmica: mapeia a contagem para uma classe estática. */
const GRID_COLS: Record<number, string> = {
    1: 'grid-cols-1',
    2: 'grid-cols-2',
    3: 'grid-cols-3',
    4: 'grid-cols-4',
};

const gridColsClass = computed(() => GRID_COLS[visibleTabs.value.length] ?? 'grid-cols-4');

const isOpen = ref(props.open);
// Primeira aba visível como padrão, para nunca abrir numa aba escondida
const activeTab = ref(visibleTabs.value[0]?.value ?? 'abc');
// Sincroniza isOpen com o prop open quando ele mudar
watch(() => props.open, (newValue) => {
    isOpen.value = newValue;
}, { immediate: true });

const handleOpenChange = (value: boolean) => {
    isOpen.value = value;
    emit('update:open', value);
};
</script>

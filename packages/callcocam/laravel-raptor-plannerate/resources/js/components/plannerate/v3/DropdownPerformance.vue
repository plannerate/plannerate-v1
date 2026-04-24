<template>
    <!-- Dropdown Performance -->
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="destructive" size="sm" class="gap-2 rounded-lg"> 
                    <Gauge class="size-4 text-destructive-foreground" /> 
                Performance
                <ChevronDown class="size-3" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="z-[9999] w-64">
            <DropdownMenuItem @click="showPerformanceModal = true">
                <Gauge class="mr-2 size-4" />
                Abrir Análises
            </DropdownMenuItem>
            <DropdownMenuSeparator />

            <!-- Controles Individuais -->
            <DropdownMenuItem
                @click="performance.abc.toggleVisibility()"
                :disabled="!performance.abc.hasData.value"
            >
                <Eye
                    v-if="!performance.abc.isVisible.value"
                    class="mr-2 size-4 text-green-600"
                />
                <EyeOff v-else class="mr-2 size-4 text-green-600" />
                {{
                    performance.abc.isVisible.value ? 'Esconder' : 'Mostrar'
                }}
                Análise de Assortimento
                <span class="ml-auto text-xs text-muted-foreground">
                    ({{ performance.abc.stats.value.total }})
                </span>
            </DropdownMenuItem>

            <DropdownMenuItem
                @click="performance.targetStock.toggleVisibility()"
                :disabled="!performance.targetStock.hasData.value"
            >
                <Eye
                    v-if="!performance.targetStock.isVisible.value"
                    class="mr-2 size-4 text-blue-600"
                />
                <EyeOff v-else class="mr-2 size-4 text-blue-600" />
                {{
                    performance.targetStock.isVisible.value
                        ? 'Esconder'
                        : 'Mostrar'
                }}
                Estoque Alvo
                <span class="ml-auto text-xs text-muted-foreground">
                    ({{ performance.targetStock.stats.value.total }})
                </span>
            </DropdownMenuItem>

            <DropdownMenuSeparator />

            <!-- Controle Geral -->
            <DropdownMenuItem
                @click="performance.toggleAllIndicators()"
                :disabled="!performance.hasAnyData.value"
            >
                <Eye v-if="!performance.anyVisible.value" class="mr-2 size-4" />
                <EyeOff v-else class="mr-2 size-4" />
                {{
                    performance.anyVisible.value ? 'Esconder' : 'Mostrar'
                }}
                Todos
            </DropdownMenuItem>

            <DropdownMenuSeparator />

            <DropdownMenuItem
                @click="performance.clearAllAnalysis(gondola.id)"
                :disabled="!performance.hasAnyData.value"
                class="text-destructive"
            >
                <Trash2 class="mr-2 size-4" />
                Limpar Todas as Análises
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
    <!-- Performance Modal -->
    <Performance
        v-model:open="showPerformanceModal"
        :gondola-id="gondola.id"
        :planogram="planogram"
        :analysis="props.analysis"
    />
</template>
<script setup lang="ts">
import Performance from '@/components/plannerate/v3/header/Performance.vue';
import { Button } from '@/components/ui/button'; 
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

import { usePerformanceIndicators } from '@/composables/plannerate/v3/usePerformanceIndicators';
import type { AbcAnalysis, Gondola, StockAnalysis } from '@/types/planogram';
import { ChevronDown, Eye, EyeOff, Gauge, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';


const props = defineProps<{
    gondola: Pick<Gondola, 'id' | 'planogram'>;
    analysis?: {
        abc?: AbcAnalysis;
        stock?: StockAnalysis;
        [key: string]: any; // Permite outras análises futuras sem quebrar o componente
    };
}>();

const showPerformanceModal = ref(false);
// Performance indicators
const performance = usePerformanceIndicators();

// Chave do localStorage inclui gondola_id para preferências específicas por gôndola
const getStorageKey = (gondolaId: string) => `plannerate:performance:visibility:${gondolaId}`;

interface PerformanceVisibilityPreferences {
    abcVisible: boolean;
    targetStockVisible: boolean;
}

const saveVisibilityPreferences = (): void => {
    if (typeof window === 'undefined') {
        return;
    }

    const payload: PerformanceVisibilityPreferences = {
        abcVisible: performance.abc.isVisible.value,
        targetStockVisible: performance.targetStock.isVisible.value,
    };

    const storageKey = getStorageKey(props.gondola.id);
    window.localStorage.setItem(storageKey, JSON.stringify(payload));
};

const loadVisibilityPreferences = (): void => {
    if (typeof window === 'undefined') {
        return;
    }

    const storageKey = getStorageKey(props.gondola.id);
    const raw = window.localStorage.getItem(storageKey);

    if (!raw) {
        return;
    }

    try {
        const parsed = JSON.parse(raw) as Partial<PerformanceVisibilityPreferences>;

        if (typeof parsed.abcVisible === 'boolean') {
            performance.abc.setVisibility(parsed.abcVisible);
        }

        if (typeof parsed.targetStockVisible === 'boolean') {
            performance.targetStock.setVisibility(parsed.targetStockVisible);
        }
    } catch {
        window.localStorage.removeItem(storageKey);
    }
};

// Detectar mudança de gôndola e limpar análises antigas
watch(
    () => props.gondola.id,
    (newId, oldId) => {
        if (newId !== oldId && oldId !== undefined) {
            // Mudou de gôndola - limpar análises antigas COMPLETAMENTE
            performance.abc.clearClassifications();
            performance.targetStock.clearTargetStockData();
            
            // Recarregar preferências de visibilidade da nova gôndola
            loadVisibilityPreferences();
        }
    },
);

// Carregar análises ABC quando disponíveis
watch(
    () => props.analysis?.abc,
    (analysis) => {
        // Sempre limpa antes de carregar para evitar dados residuais
        performance.abc.clearClassifications();
        
        // Só carrega se há dados válidos
        if (analysis?.results && Array.isArray(analysis.results) && analysis.results.length > 0) {
            performance.abc.setClassifications(analysis.results);
        }
    },
    { immediate: true },
);

// Carregar análises de Stock quando disponíveis
watch(
    () => props.analysis?.stock,
    (analysis) => {
        // Sempre limpa antes de carregar para evitar dados residuais
        performance.targetStock.clearTargetStockData();
        
        // Só carrega se há dados válidos
        if (analysis?.results && Array.isArray(analysis.results) && analysis.results.length > 0) {
            performance.targetStock.setTargetStockDataBatch(
                analysis.results as any[],
            );
        }
    },
    { immediate: true },
);

watch(
    [performance.abc.isVisible, performance.targetStock.isVisible],
    () => {
        saveVisibilityPreferences();
    },
);

onMounted(() => {
    loadVisibilityPreferences();
});

const planogram = computed(() => {
    const pg = props.gondola.planogram;
    // Return the full planogram if it has required id and name properties
    if (pg && 'id' in pg && 'name' in pg) {
        return pg as any; // Cast to Planogram type
    }
    return null;
});
</script>

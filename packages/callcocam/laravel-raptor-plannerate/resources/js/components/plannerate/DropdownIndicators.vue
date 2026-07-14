<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm" :class="activeConfig ? 'border-primary text-primary' : ''">
                <component :is="activeConfig?.icon ?? Eye" class="mr-2 size-4" />
                {{ t('plannerate.dropdown.indicators.title') }}
                <ChevronDown class="ml-1 size-3" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="z-9999 w-64 p-2">
            <!-- Título do painel -->
            <p class="px-1 pb-1.5 text-sm font-semibold text-foreground">
                {{ t('plannerate.dropdown.indicators.title') }}
            </p>

            <!-- Card com a lista de indicadores (montados a partir do registro) -->
            <div class="overflow-hidden rounded-lg border bg-card">
                <DropdownMenuItem
                    v-for="(indicator, index) in indicators"
                    :key="indicator.key"
                    class="gap-2.5 rounded-none px-2 py-1.5"
                    :class="index < indicators.length - 1 ? 'border-b' : ''"
                    @click="select(indicator.key)"
                >
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-lg"
                        :class="indicator.badgeClass"
                    >
                        <component :is="indicator.icon" class="size-4" :class="indicator.iconClass" />
                    </span>
                    <span class="flex-1 text-sm">{{ t(indicator.labelKey) }}</span>
                    <CheckCircle2
                        v-if="selectedIndicator === indicator.key"
                        class="size-5 shrink-0 text-green-600"
                    />
                </DropdownMenuItem>
            </div>

            <!-- Card "Nenhum" (limpar seleção) -->
            <DropdownMenuItem
                class="mt-1.5 gap-2.5 rounded-lg border bg-card px-2 py-1.5"
                @click="select(INDICATOR_NONE)"
            >
                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                    <Ban class="size-4" />
                </span>
                <span class="flex-1 text-sm">{{ t('plannerate.dropdown.indicators.none') }}</span>
                <CheckCircle2
                    v-if="selectedIndicator === INDICATOR_NONE"
                    class="size-5 shrink-0 text-green-600"
                />
            </DropdownMenuItem>

            <!-- Orientação do selo: dois botões lado a lado (vertical | horizontal) -->
            <p class="px-1 pb-1.5 pt-2 text-xs font-medium text-muted-foreground">
                {{ t('plannerate.dropdown.indicators.orientation') }}
            </p>

            <div class="grid grid-cols-2 gap-1.5">
                <button
                    type="button"
                    class="flex items-center justify-center gap-1.5 rounded-md border px-2 py-1.5 text-xs font-medium transition-colors"
                    :class="
                        indicatorOrientation === 'vertical'
                            ? 'border-green-600 bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-400'
                            : 'text-foreground hover:bg-accent'
                    "
                    @click="setOrientation('vertical')"
                >
                    <GalleryVertical class="size-3.5" />
                    {{ t('plannerate.dropdown.indicators.orientation_vertical') }}
                </button>

                <button
                    type="button"
                    class="flex items-center justify-center gap-1.5 rounded-md border px-2 py-1.5 text-xs font-medium transition-colors"
                    :class="
                        indicatorOrientation === 'horizontal'
                            ? 'border-green-600 bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-400'
                            : 'text-foreground hover:bg-accent'
                    "
                    @click="setOrientation('horizontal')"
                >
                    <GalleryHorizontal class="size-3.5" />
                    {{ t('plannerate.dropdown.indicators.orientation_horizontal') }}
                </button>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

<script setup lang="ts">
import { Ban, CheckCircle2, ChevronDown, Eye, GalleryHorizontal, GalleryVertical } from 'lucide-vue-next';
import { computed, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useSalesIndicators } from '@/composables/plannerate/analysis/useSalesIndicators';
import {
    currentGondola,
    indicatorOrientation,
    selectedIndicator
    
} from '@/composables/plannerate/core/useGondolaState';
import type {IndicatorOrientation} from '@/composables/plannerate/core/useGondolaState';
import {
    getIndicatorConfig,
    INDICATOR_NONE,
    indicatorNeedsSales,
    PRODUCT_INDICATORS,
} from '@/composables/plannerate/editor/indicators';
import { useT } from '@/composables/useT';

/**
 * Gôndola de contexto opcional. No editor, o componente lê a gôndola atual do
 * estado global (`currentGondola`); na área de print esse estado pode não estar
 * populado, então a tela passa a gôndola explicitamente via prop.
 */
interface Props {
    gondola?: {
        id: string;
        planogram?: { start_date?: string | null; end_date?: string | null } | null;
    } | null;
}

const props = withDefaults(defineProps<Props>(), {
    gondola: null,
});

const { t } = useT();
const { loadForGondola, hasData } = useSalesIndicators();

/** Lista completa de indicadores disponíveis (define a ordem do menu). */
const indicators = PRODUCT_INDICATORS;

/** Gôndola efetiva: a prop tem prioridade; senão, cai no estado global. */
const effectiveGondola = computed(() => props.gondola ?? currentGondola.value);

/** Configuração do indicador ativo — usada para destacar o botão da toolbar. */
const activeConfig = computed(() => getIndicatorConfig(selectedIndicator.value));

/**
 * Carrega em lote os indicadores de vendas da gôndola atual, filtrando pelo
 * período do planograma quando disponível. Só busca uma vez (a menos que
 * `force`), pois a store é compartilhada entre todos os selos.
 */
function ensureSalesData(force = false) {
    if (!force && hasData.value) {
        return;
    }

    const gondola = effectiveGondola.value;

    if (!gondola?.id) {
        return;
    }

    loadForGondola(
        gondola.id,
        gondola.planogram?.start_date ?? null,
        gondola.planogram?.end_date ?? null,
    );
}

/** Aplica a seleção de indicador (persistida via estado global). */
function select(key: string) {
    selectedIndicator.value = key;

    // Indicadores baseados em vendas precisam do lote carregado para exibir valores.
    if (indicatorNeedsSales(key)) {
        ensureSalesData();
    }
}

/** Define a orientação do selo de indicador (persistida via estado global). */
function setOrientation(orientation: IndicatorOrientation) {
    indicatorOrientation.value = orientation;
}

// Se o indicador persistido (localStorage) depende de vendas, carrega ao montar.
onMounted(() => {
    if (indicatorNeedsSales(selectedIndicator.value)) {
        ensureSalesData();
    }
});
</script>

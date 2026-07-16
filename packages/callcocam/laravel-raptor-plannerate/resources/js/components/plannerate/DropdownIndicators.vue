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

            <!-- Estilo da tábua da prateleira -->
            <p class="px-1 pb-1.5 pt-3 text-xs font-medium text-muted-foreground">
                {{ t('plannerate.dropdown.indicators.shelf_style') }}
            </p>

            <div class="grid grid-cols-3 gap-1.5">
                <button
                    v-for="style in shelfStyles"
                    :key="style.key"
                    type="button"
                    class="flex flex-col items-center gap-1.5 rounded-md border px-1.5 py-2 text-[11px] font-medium transition-colors"
                    :class="
                        shelfBoardStyle === style.key
                            ? 'border-green-600 bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-400'
                            : 'text-foreground hover:bg-accent'
                    "
                    @click="setShelfStyle(style.key)"
                >
                    <span
                        class="h-4 w-full rounded-sm ring-1 ring-inset ring-black/10"
                        :style="{ background: style.swatch }"
                    />
                    {{ t(style.labelKey) }}
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
    selectedIndicator,
    shelfBoardStyle,
} from '@/composables/plannerate/core/useGondolaState';
import type {IndicatorOrientation, ShelfBoardStyle} from '@/composables/plannerate/core/useGondolaState';
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

/**
 * Estilos da tábua da prateleira exibidos no seletor. `swatch` é um gradiente
 * CSS que reproduz em miniatura a face da tábua (ver `ShelfBoard.vue`).
 */
const shelfStyles: { key: ShelfBoardStyle; labelKey: string; swatch: string }[] = [
    { key: 'slate', labelKey: 'plannerate.dropdown.indicators.shelf_slate', swatch: 'linear-gradient(#64707f, #3f4855 42%, #1f242c)' },
    { key: 'wood', labelKey: 'plannerate.dropdown.indicators.shelf_wood', swatch: 'linear-gradient(#c79a63, #a6743f 45%, #7c5027)' },
    { key: 'white', labelKey: 'plannerate.dropdown.indicators.shelf_white', swatch: 'linear-gradient(#ffffff, #eef1f5 40%, #c4cbd4)' },
    { key: 'chrome', labelKey: 'plannerate.dropdown.indicators.shelf_chrome', swatch: 'linear-gradient(#eef3f8, #7d8896 50%, #e6ecf2)' },
    { key: 'persp', labelKey: 'plannerate.dropdown.indicators.shelf_persp', swatch: 'linear-gradient(#566270, #8e9cae 20%, #2b323b 74%, #12161b)' },
    { key: 'deck', labelKey: 'plannerate.dropdown.indicators.shelf_deck', swatch: 'linear-gradient(#8b98a8 0 45%, #2b323b 45%)' },
    { key: 'glass', labelKey: 'plannerate.dropdown.indicators.shelf_glass', swatch: 'linear-gradient(rgba(214,230,244,.85), rgba(96,132,170,.8))' },
];

/** Define o estilo da tábua da prateleira (persistido via estado global). */
function setShelfStyle(style: ShelfBoardStyle) {
    shelfBoardStyle.value = style;
}

// Se o indicador persistido (localStorage) depende de vendas, carrega ao montar.
onMounted(() => {
    if (indicatorNeedsSales(selectedIndicator.value)) {
        ensureSalesData();
    }
});
</script>

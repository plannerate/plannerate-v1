<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm" :class="activeConfig ? 'border-primary text-primary' : ''">
                <component :is="activeConfig?.icon ?? Eye" class="mr-2 size-4" />
                {{ t('plannerate.dropdown.indicators.title') }}
                <ChevronDown class="ml-1 size-3" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="z-9999 w-56">
            <DropdownMenuLabel class="text-xs text-muted-foreground">
                {{ t('plannerate.dropdown.indicators.title') }}
            </DropdownMenuLabel>
            <DropdownMenuSeparator />

            <!-- Itens montados dinamicamente a partir do registro de indicadores -->
            <DropdownMenuItem
                v-for="indicator in indicators"
                :key="indicator.key"
                class="gap-2"
                @click="select(indicator.key)"
            >
                <span
                    class="flex size-5 shrink-0 items-center justify-center rounded"
                    :class="indicator.badgeClass"
                >
                    <component :is="indicator.icon" class="size-3" :class="indicator.iconClass" />
                </span>
                <span class="flex-1">{{ t(indicator.labelKey) }}</span>
                <Check v-if="selectedIndicator === indicator.key" class="size-4 text-primary" />
            </DropdownMenuItem>

            <DropdownMenuSeparator />

            <!-- Opção "Nenhum" -->
            <DropdownMenuItem class="gap-2" @click="select(INDICATOR_NONE)">
                <span class="flex size-5 shrink-0 items-center justify-center rounded text-muted-foreground">
                    <Ban class="size-3" />
                </span>
                <span class="flex-1">{{ t('plannerate.dropdown.indicators.none') }}</span>
                <Check v-if="selectedIndicator === INDICATOR_NONE" class="size-4 text-primary" />
            </DropdownMenuItem>

            <DropdownMenuSeparator />

            <!-- Orientação do selo: vertical (rotacionado) ou horizontal -->
            <DropdownMenuLabel class="text-xs text-muted-foreground">
                {{ t('plannerate.dropdown.indicators.orientation') }}
            </DropdownMenuLabel>

            <DropdownMenuItem class="gap-2" @click="setOrientation('vertical')">
                <span class="flex size-5 shrink-0 items-center justify-center rounded text-muted-foreground">
                    <GalleryVertical class="size-3" />
                </span>
                <span class="flex-1">{{ t('plannerate.dropdown.indicators.orientation_vertical') }}</span>
                <Check v-if="indicatorOrientation === 'vertical'" class="size-4 text-primary" />
            </DropdownMenuItem>

            <DropdownMenuItem class="gap-2" @click="setOrientation('horizontal')">
                <span class="flex size-5 shrink-0 items-center justify-center rounded text-muted-foreground">
                    <GalleryHorizontal class="size-3" />
                </span>
                <span class="flex-1">{{ t('plannerate.dropdown.indicators.orientation_horizontal') }}</span>
                <Check v-if="indicatorOrientation === 'horizontal'" class="size-4 text-primary" />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

<script setup lang="ts">
import { Ban, Check, ChevronDown, Eye, GalleryHorizontal, GalleryVertical } from 'lucide-vue-next';
import { computed, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useSalesIndicators } from '@/composables/plannerate/analysis/useSalesIndicators';
import {
    currentGondola,
    indicatorOrientation,
    selectedIndicator,
    type IndicatorOrientation,
} from '@/composables/plannerate/core/useGondolaState';
import {
    getIndicatorConfig,
    INDICATOR_NONE,
    indicatorNeedsSales,
    PRODUCT_INDICATORS,
} from '@/composables/plannerate/editor/indicators';
import { useT } from '@/composables/useT';

const { t } = useT();
const { loadForGondola, hasData } = useSalesIndicators();

/** Lista completa de indicadores disponíveis (define a ordem do menu). */
const indicators = PRODUCT_INDICATORS;

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
    const gondola = currentGondola.value;
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

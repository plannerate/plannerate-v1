<script setup lang="ts">
import { computed } from 'vue';
import { useBcgLabels } from '@/components/plannerate/analysis/bcg/labels';
import type { BcgQuadrant } from '@/components/plannerate/analysis/bcg/types';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useBcgAnalysis  } from '@/composables/plannerate/analysis/useBcgAnalysis';
import type {BcgBadgeData} from '@/composables/plannerate/analysis/useBcgAnalysis';
import { useT } from '@/composables/useT';

/**
 * Película do quadrante BCG sobre o produto, na gôndola.
 *
 * Segue o padrão do StockIndicator: em vez de um rótulo solto na base, o produto
 * inteiro recebe uma película colorida pela sua classificação, com um marcador
 * central que abre o detalhe no hover. Ler a gôndola vira varredura de cor — dá
 * para ver de longe onde estão os "baixo valor" sem ler texto nenhum.
 *
 * Cores por quadrante, iguais às da tabela e do gráfico (a cor segue a entidade):
 *   alto_alto → verde | forte_x → azul | forte_y → amarelo | baixo_baixo → vermelho
 *
 * pointer-events-none no root: a película cobre o produto (inset-0) e sem isso
 * roubaria hover/click da imagem. Só o marcador central é interativo.
 * z-79 = Z.BCG_OVERLAY (constants/zIndex.ts).
 */
interface Props {
    data?: BcgBadgeData;
    /** Fator de escala do planograma (mesma base do StockIndicator). */
    scale?: number;
}

const props = withDefaults(defineProps<Props>(), {
    scale: 3,
});

const { t } = useT();
const { isVisible, isQuadrantActive } = useBcgAnalysis();
const {
    axisLabel,
    quadrantLabel,
    quadrantDescription,
    quadrantActions,
    actionsTitle,
    quadrantIcon,
    spaceActionLabel,
    spaceActionIcon,
} = useBcgLabels();

const visible = computed(
    () => Boolean(props.data) && isVisible.value && isQuadrantActive(props.data?.quadrant),
);

/** Película: borda sólida + fundo translúcido na cor do quadrante. */
const overlayClasses = computed(() => {
    const classes: Record<BcgQuadrant, string> = {
        alto_alto: 'border-2 border-green-500 bg-green-500/20 dark:bg-green-500/30',
        forte_x: 'border-2 border-blue-500 bg-blue-500/20 dark:bg-blue-500/30',
        forte_y: 'border-2 border-yellow-500 bg-yellow-500/20 dark:bg-yellow-500/30',
        baixo_baixo: 'border-2 border-red-500 bg-red-500/20 dark:bg-red-500/30',
    };

    return props.data ? classes[props.data.quadrant] : '';
});

/** Borda do marcador central, na cor do quadrante. */
const markerBorderClasses = computed(() => {
    const classes: Record<BcgQuadrant, string> = {
        alto_alto: 'border border-green-500/70',
        forte_x: 'border border-blue-500/70',
        forte_y: 'border border-yellow-500/70',
        baixo_baixo: 'border border-red-500/70',
    };

    return props.data ? classes[props.data.quadrant] : '';
});

/** Cor do símbolo do quadrante dentro do marcador. */
const markerTextClasses = computed(() => {
    const classes: Record<BcgQuadrant, string> = {
        alto_alto: 'text-green-600',
        forte_x: 'text-blue-600',
        forte_y: 'text-yellow-600',
        baixo_baixo: 'text-red-600',
    };

    return props.data ? classes[props.data.quadrant] : '';
});

/** Descrição do quadrante gerada pela análise (depende dos eixos — ver bcg/labels.ts). */
const label = computed(() =>
    props.data ? quadrantLabel(props.data.quadrant, props.data.x_axis, props.data.y_axis) : '',
);

/** A ação só aparece em destaque quando há algo a fazer: 'manter' não vira ruído. */
const showAction = computed(
    () => props.data?.acao_espaco === 'aumentar' || props.data?.acao_espaco === 'reduzir',
);

/** Ações recomendadas do quadrante (vazio fora do preset canônico — ver bcg/labels.ts). */
const actions = computed(() =>
    props.data ? quadrantActions(props.data.quadrant, props.data.x_axis, props.data.y_axis) : [],
);

// Dimensões proporcionais à escala da gôndola (mesma fórmula do StockIndicator)
const markerSize = computed(() => Math.max(6, Math.min(20, props.scale * 4)));
const markerPadding = computed(() => Math.max(2, Math.min(8, props.scale * 2)));
</script>

<template>
    <div
        v-if="visible && data"
        class="pointer-events-none absolute inset-0 z-[79] flex items-center justify-center rounded-sm"
        :class="overlayClasses"
    >
        <TooltipProvider :delay-duration="200">
            <Tooltip>
                <TooltipTrigger as-child>
                    <!-- Sem z próprio: o root (z-79 = Z.BCG_OVERLAY) cria o stacking context -->
                    <div
                        class="pointer-events-auto absolute top-1/2 left-1/2 flex -translate-x-1/2 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-white font-bold shadow-sm transition-transform hover:scale-105"
                        :class="[markerBorderClasses, markerTextClasses]"
                        :style="{
                            padding: `${markerPadding}px`,
                            width: `${markerSize + markerPadding * 2}px`,
                            height: `${markerSize + markerPadding * 2}px`,
                            fontSize: `${markerSize}px`,
                            lineHeight: '1',
                        }"
                    >
                        {{ quadrantIcon(data.quadrant) }}
                    </div>
                </TooltipTrigger>
                <TooltipContent
                    side="right"
                    align="start"
                    :side-offset="8"
                    :collision-padding="16"
                    :avoid-collisions="true"
                    class="z-[9999] max-h-[68vh] w-[min(18rem,calc(100vw-1rem))] overflow-hidden border border-border bg-background p-0 shadow-2xl"
                >
                    <div class="max-h-[68vh] space-y-2.5 overflow-y-auto p-3">
                        <!-- Quadrante -->
                        <div
                            class="rounded-lg border p-2 text-center"
                            :class="overlayClasses"
                        >
                            <p class="text-xs font-bold text-foreground">
                                <span aria-hidden="true">{{ quadrantIcon(data.quadrant) }}</span>
                                {{ label }}
                            </p>
                            <p class="mt-0.5 text-[10px] text-muted-foreground">
                                {{ quadrantDescription(data.quadrant) }}
                            </p>
                        </div>

                        <!-- Ações recomendadas do quadrante -->
                        <div v-if="actions.length" class="rounded-lg border border-border bg-accent/50 p-2">
                            <p class="mb-1 text-[11px] font-semibold text-foreground">
                                {{ actionsTitle() }}
                            </p>
                            <ul class="space-y-0.5">
                                <li
                                    v-for="action in actions"
                                    :key="action"
                                    class="flex gap-1.5 text-[11px] leading-snug text-muted-foreground"
                                >
                                    <span aria-hidden="true" class="text-foreground">•</span>
                                    <span>{{ action }}</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Eixos usados no cálculo do quadrante -->
                        <div class="rounded-lg border border-border bg-accent/50 p-2 text-[11px]">
                            <p class="mb-1 font-semibold text-muted-foreground">
                                {{ t('plannerate.analysis.bcg_params.axis_title') }}
                            </p>
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground">{{ t('plannerate.analysis.bcg_params.x_axis') }}</span>
                                <span class="font-semibold text-foreground">{{ axisLabel(data.x_axis) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground">{{ t('plannerate.analysis.bcg_params.y_axis') }}</span>
                                <span class="font-semibold text-foreground">{{ axisLabel(data.y_axis) }}</span>
                            </div>
                        </div>

                        <!-- Ação sugerida de espaço -->
                        <div
                            class="rounded-lg border p-2 text-center"
                            :class="showAction ? 'border-border bg-accent' : 'border-dashed border-border'"
                        >
                            <p class="text-[10px] text-muted-foreground">
                                {{ t('plannerate.analysis.bcg_selection.action') }}
                            </p>
                            <p
                                class="text-xs font-semibold"
                                :class="showAction ? 'text-foreground' : 'text-muted-foreground'"
                            >
                                <span aria-hidden="true">{{ spaceActionIcon(data.acao_espaco) }}</span>
                                {{ spaceActionLabel(data.acao_espaco) }}
                            </p>
                        </div>
                    </div>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    </div>
</template>

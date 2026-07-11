<script setup lang="ts">
import { computed } from 'vue';
import { useBcgLabels } from '@/components/plannerate/analysis/bcg/labels';
import type { BcgQuadrant } from '@/components/plannerate/analysis/bcg/types';
import { useBcgAnalysis, type BcgBadgeData } from '@/composables/plannerate/analysis/useBcgAnalysis';
import { useT } from '@/composables/useT';

/**
 * Selo do quadrante BCG na frente do produto, na gôndola.
 *
 * Mostra o quadrante E a seta da ação (↑ ganhar frente / ↓ ceder frente). Um selo que
 * só dissesse o quadrante seria decoração: quem está diante da gôndola precisa saber
 * o que fazer com aquele produto, não em que caixinha ele caiu.
 *
 * Respeita o filtro por quadrante do painel de análises — assim o usuário isola, por
 * exemplo, só os "baixo valor" e vê onde eles estão fisicamente na gôndola.
 */
interface Props {
    data?: BcgBadgeData;
}

const props = defineProps<Props>();

const { t } = useT();
const { isVisible, isQuadrantActive } = useBcgAnalysis();
const { quadrantLabel, quadrantIcon, spaceActionLabel, spaceActionIcon } = useBcgLabels();

const visible = computed(
    () => Boolean(props.data) && isVisible.value && isQuadrantActive(props.data?.quadrant),
);

/**
 * Cores dos quadrantes — mesmas famílias de matiz da tabela e do gráfico.
 * A cor segue a entidade: um produto verde na matriz é verde na gôndola.
 */
const badgeClasses = computed(() => {
    const classes: Record<BcgQuadrant, string> = {
        alto_alto: 'bg-green-600 text-white',
        forte_x: 'bg-blue-600 text-white',
        forte_y: 'bg-yellow-500 text-gray-900',
        baixo_baixo: 'bg-red-600 text-white',
    };

    return props.data ? classes[props.data.quadrant] : '';
});

/** Tooltip: quadrante + ação. É a leitura completa sem depender da cor. */
const title = computed(() => {
    if (!props.data) return '';

    const quadrant = quadrantLabel(props.data.quadrant, props.data.x_axis, props.data.y_axis);
    const action = spaceActionLabel(props.data.acao_espaco);

    return `${quadrant} — ${t('plannerate.analysis.bcg_selection.action')}: ${action}`;
});

/** A ação só ganha destaque quando há algo a fazer: 'manter' não vira ruído visual. */
const showAction = computed(
    () => props.data?.acao_espaco === 'aumentar' || props.data?.acao_espaco === 'reduzir',
);
</script>

<template>
    <div
        v-if="visible && data"
        class="flex items-center gap-0.5 rounded-lg px-1 py-0.5 text-[10px] font-bold shadow-md"
        :class="badgeClasses"
        :title="title"
    >
        <span aria-hidden="true">{{ quadrantIcon(data.quadrant) }}</span>
        <span v-if="showAction" aria-hidden="true">{{ spaceActionIcon(data.acao_espaco) }}</span>
    </div>
</template>

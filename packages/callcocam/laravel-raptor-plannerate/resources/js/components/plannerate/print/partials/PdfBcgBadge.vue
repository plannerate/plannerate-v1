<script setup lang="ts">
import { computed } from 'vue';
import { useBcgLabels } from '@/components/plannerate/analysis/bcg/labels';
import type { BcgQuadrant } from '@/components/plannerate/analysis/bcg/types';
import { useBcgAnalysis } from '@/composables/plannerate/analysis/useBcgAnalysis';
import type { BcgBadgeData } from '@/composables/plannerate/analysis/useBcgAnalysis';
import { indicatorOrientation } from '@/composables/plannerate/core/useGondolaState';
import { useT } from '@/composables/useT';

/**
 * Selo do quadrante BCG para o print/PDF. Mesmo padrão do BcgBadge do editor: pill
 * branco com o símbolo do quadrante e a DESCRIÇÃO que a análise gera, ancorado ao topo
 * do produto e com dimensões escalonadas pelo fator do PDF.
 */
interface Props {
    data?: BcgBadgeData;
    scale?: number;
}

const props = withDefaults(defineProps<Props>(), {
    scale: 1,
});

const { t } = useT();
const { isVisible, isQuadrantActive } = useBcgAnalysis();
const { quadrantLabel, quadrantIcon, spaceActionLabel, spaceActionIcon } = useBcgLabels();

const visible = computed(
    () => Boolean(props.data) && isVisible.value && isQuadrantActive(props.data?.quadrant),
);

const orientation = computed(() => indicatorOrientation.value);

const iconSize = computed(() => Math.max(6 * props.scale, 11));
const fontSize = computed(() => Math.max(3.5 * props.scale, 6));

const circleClasses = computed(() => {
    const classes: Record<BcgQuadrant, string> = {
        alto_alto: 'bg-green-600 text-white',
        forte_x: 'bg-blue-600 text-white',
        forte_y: 'bg-yellow-500 text-gray-900',
        baixo_baixo: 'bg-red-600 text-white',
    };

    return props.data ? classes[props.data.quadrant] : '';
});

const labelTextClass = computed(() => {
    const classes: Record<BcgQuadrant, string> = {
        alto_alto: 'text-green-700',
        forte_x: 'text-blue-700',
        forte_y: 'text-yellow-700',
        baixo_baixo: 'text-red-700',
    };

    return props.data ? classes[props.data.quadrant] : '';
});

/** Modo por categoria: o selo representa o grupo do produto (ver BcgBadge do editor). */
const isCategory = computed(() => Boolean(props.data?.display_by && props.data.display_by !== 'produto'));

const label = computed(() => {
    if (!props.data) {
        return '';
    }

    if (isCategory.value) {
        return props.data.group_label ?? '';
    }

    return quadrantLabel(props.data.quadrant, props.data.x_axis, props.data.y_axis);
});

const showAction = computed(
    () => props.data?.acao_espaco === 'aumentar' || props.data?.acao_espaco === 'reduzir',
);

const title = computed(() => {
    if (!props.data) {
        return '';
    }

    const quadrant = quadrantLabel(props.data.quadrant, props.data.x_axis, props.data.y_axis);
    const action = spaceActionLabel(props.data.acao_espaco);
    const base = `${quadrant} — ${t('plannerate.analysis.bcg_selection.action')}: ${action}`;

    if (isCategory.value) {
        const category = props.data.group_label
            ? `${t('plannerate.analysis.bcg_selection.category_badge')}: ${props.data.group_label} · `
            : `${t('plannerate.analysis.bcg_selection.category_hint')} · `;

        return `${category}${base}`;
    }

    return base;
});

/** Pill ancorado à base do produto (mesma posição do selo ABC), pivotando no círculo quando vertical. */
const pillStyle = computed(() => {
    const half = iconSize.value / 2;
    const base: Record<string, string> = {
        gap: `${Math.max(0.75 * props.scale, 1.5)}px`,
        padding: `${Math.max(0.5 * props.scale, 1)}px ${Math.max(1.5 * props.scale, 3)}px ${Math.max(0.5 * props.scale, 1)}px ${Math.max(0.5 * props.scale, 1)}px`,
        bottom: `${Math.max(1 * props.scale, 2)}px`,
    };

    if (orientation.value === 'vertical') {
        base.transformOrigin = `${half}px center`;
        base.transform = `translateX(-${half}px) rotate(-90deg)`;
        base.bottom = `${Math.max(2 * props.scale, 4)}px`;
    } else {
        base.transform = 'translateX(-50%)';
    }

    return base;
});

const circleStyle = computed(() => ({
    fontSize: `${fontSize.value}px`,
    width: `${iconSize.value}px`,
    height: `${iconSize.value}px`,
}));

const labelStyle = computed(() => ({
    fontSize: `${fontSize.value}px`,
}));
</script>

<template>
    <div
        v-if="visible && data"
        class="pointer-events-none absolute left-1/2 z-[500] flex items-center rounded-full bg-white/95 shadow-md"
        :style="pillStyle"
        :title="title"
    >
        <!-- Símbolo do quadrante -->
        <span
            class="flex items-center justify-center rounded-full font-bold leading-none"
            :class="circleClasses"
            :style="circleStyle"
        >
            {{ quadrantIcon(data.quadrant) }}
        </span>

        <!-- Rótulo + seta da ação. No modo por categoria, marcador (▦) antecede o grupo. -->
        <span class="font-bold whitespace-nowrap leading-none" :class="labelTextClass" :style="labelStyle">
            <span v-if="isCategory" aria-hidden="true" class="opacity-70">▦ </span>{{ label }}<span v-if="showAction" aria-hidden="true"> {{ spaceActionIcon(data.acao_espaco) }}</span>
        </span>
    </div>
</template>

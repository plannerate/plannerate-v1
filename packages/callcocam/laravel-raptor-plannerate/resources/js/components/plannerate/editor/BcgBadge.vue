<script setup lang="ts">
import { computed, ref } from 'vue';
import { useBcgLabels } from '@/components/plannerate/analysis/bcg/labels';
import type { BcgQuadrant } from '@/components/plannerate/analysis/bcg/types';
import { useBcgAnalysis  } from '@/composables/plannerate/analysis/useBcgAnalysis';
import type {BcgBadgeData} from '@/composables/plannerate/analysis/useBcgAnalysis';
import { indicatorOrientation } from '@/composables/plannerate/core/useGondolaState';
import { useT } from '@/composables/useT';

/**
 * Selo do quadrante BCG na frente do produto, na gôndola.
 *
 * Segue o padrão do selo ABC: um pill branco com um círculo colorido (o símbolo do
 * quadrante) e, ao lado, a DESCRIÇÃO que a análise gera (o rótulo do quadrante —
 * "Alto valor – manutenção", "Incentivo – volume", etc.). A seta da ação (↑ ganhar
 * frente / ↓ ceder frente) aparece só quando há algo a fazer, para não virar ruído.
 *
 * Respeita o filtro por quadrante do painel de análises — assim o usuário isola, por
 * exemplo, só os "baixo valor" e vê onde eles estão fisicamente na gôndola.
 */
interface Props {
    data?: BcgBadgeData;
    /** Fator de escala do planograma (mesma base do selo ABC). */
    scale?: number;
}

const props = withDefaults(defineProps<Props>(), {
    scale: 3,
});

const { t } = useT();
const { isVisible, isQuadrantActive } = useBcgAnalysis();
const { quadrantLabel, quadrantIcon, spaceActionLabel, spaceActionIcon } = useBcgLabels();

const visible = computed(
    () => Boolean(props.data) && isVisible.value && isQuadrantActive(props.data?.quadrant),
);

/** Orientação atual do selo (vertical = rotacionado 90°, horizontal = normal), igual ao ABC. */
const orientation = computed(() => indicatorOrientation.value);

/** Diâmetro do círculo do símbolo, escalonado como no selo ABC. */
const iconSize = computed(() => Math.max(6 * props.scale, 11));

/** Tamanho da fonte (símbolo e rótulo), escalonado como no selo ABC. */
const fontSize = computed(() => Math.max(3.5 * props.scale, 6));

/**
 * Cores dos quadrantes no círculo — mesmas famílias de matiz da tabela e do gráfico.
 * A cor segue a entidade: um produto verde na matriz é verde na gôndola.
 */
const circleClasses = computed(() => {
    const classes: Record<BcgQuadrant, string> = {
        alto_alto: 'bg-green-600 text-white',
        forte_x: 'bg-blue-600 text-white',
        forte_y: 'bg-yellow-500 text-gray-900',
        baixo_baixo: 'bg-red-600 text-white',
    };

    return props.data ? classes[props.data.quadrant] : '';
});

/** Cor do texto do rótulo, tingida com a cor do quadrante sobre o fundo claro do pill. */
const labelTextClass = computed(() => {
    const classes: Record<BcgQuadrant, string> = {
        alto_alto: 'text-green-700',
        forte_x: 'text-blue-700',
        forte_y: 'text-yellow-700',
        baixo_baixo: 'text-red-700',
    };

    return props.data ? classes[props.data.quadrant] : '';
});

/**
 * Modo "exibir por categoria": o selo representa o GRUPO ao qual o produto pertence
 * (mesmo selo em todos os produtos da categoria), não o produto em si.
 */
const isCategory = computed(() => Boolean(props.data?.display_by && props.data.display_by !== 'produto'));

/**
 * Rótulo do selo. No modo por categoria mostramos o NOME da categoria (marca o grupo);
 * no modo por produto, a descrição do quadrante gerada pela análise (ver bcg/labels.ts).
 */
const label = computed(() => {
    if (!props.data) {
        return '';
    }

    if (isCategory.value) {
        return props.data.group_label ?? '';
    }

    return quadrantLabel(props.data.quadrant, props.data.x_axis, props.data.y_axis);
});

/** Máximo de caracteres antes de truncar o rótulo (mantém o selo compacto na gôndola). */
const MAX_LABEL_CHARS = 12;

const hovered = ref(false);

/**
 * Rótulo exibido: truncado por padrão, completo ao passar o mouse. Assim o selo fica
 * compacto na gôndola e ainda revela a descrição inteira quando o usuário quer lê-la.
 */
const displayLabel = computed(() => {
    if (hovered.value || label.value.length <= MAX_LABEL_CHARS) {
        return label.value;
    }

    return `${label.value.slice(0, MAX_LABEL_CHARS - 1)}…`;
});

/** A ação só ganha destaque quando há algo a fazer: 'manter' não vira ruído visual. */
const showAction = computed(
    () => props.data?.acao_espaco === 'aumentar' || props.data?.acao_espaco === 'reduzir',
);

/** Tooltip: quadrante + ação. É a leitura completa sem depender da cor. */
const title = computed(() => {
    if (!props.data) {
        return '';
    }

    const quadrant = quadrantLabel(props.data.quadrant, props.data.x_axis, props.data.y_axis);
    const action = spaceActionLabel(props.data.acao_espaco);
    const base = `${quadrant} — ${t('plannerate.analysis.bcg_selection.action')}: ${action}`;

    // No modo por categoria, deixa explícito de qual grupo é o selo.
    if (isCategory.value) {
        const category = props.data.group_label
            ? `${t('plannerate.analysis.bcg_selection.category_badge')}: ${props.data.group_label} · `
            : `${t('plannerate.analysis.bcg_selection.category_hint')} · `;

        return `${category}${base}`;
    }

    return base;
});

/**
 * Padding/gap + transform, escalonados e pivotados como no selo ABC.
 * Vertical: rotaciona 90° pivotando no CENTRO DO CÍRCULO (não do pill), para o símbolo
 * ficar sempre na mesma base; o rótulo cresce para cima, independente do comprimento.
 * Horizontal: pill centralizado no produto.
 */
const pillStyle = computed(() => {
    const half = iconSize.value / 2;
    const base: Record<string, string> = {
        gap: `${Math.max(0.75 * props.scale, 1.5)}px`,
        padding: `${Math.max(0.5 * props.scale, 1)}px ${Math.max(1.5 * props.scale, 3)}px ${Math.max(0.5 * props.scale, 1)}px ${Math.max(0.5 * props.scale, 1)}px`,
    };

    if (orientation.value === 'vertical') {
        base.transformOrigin = `${half}px center`;
        base.transform = `translateX(-${half}px) rotate(-90deg)`;
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
    <!--
        data-bcg-badge: o wrapper no Segment.vue usa has-[[data-bcg-badge]:hover]
        para se elevar a Z.BADGE_HOVER quando este selo está em hover — o antigo
        z-[1000] aqui era neutralizado pelo stacking context do próprio wrapper.
    -->
    <div
        v-if="visible && data"
        data-bcg-badge
        class="pointer-events-auto relative flex items-center rounded-full bg-white/95 shadow-md"
        :style="pillStyle"
        :title="title"
        @mouseenter="hovered = true"
        @mouseleave="hovered = false"
    >
        <!-- Símbolo do quadrante -->
        <span
            class="flex items-center justify-center rounded-full font-bold leading-none"
            :class="circleClasses"
            :style="circleStyle"
        >
            {{ quadrantIcon(data.quadrant) }}
        </span>

        <!--
            Rótulo (truncado; completo no hover) + seta da ação.
            No modo por categoria, um marcador (▦) antecede o nome do grupo, deixando
            claro que o selo é da CATEGORIA e não do produto individual.
        -->
        <span class="font-bold whitespace-nowrap leading-none" :class="labelTextClass" :style="labelStyle">
            <span v-if="isCategory" aria-hidden="true" class="opacity-70">▦ </span>{{ displayLabel }}<span v-if="showAction" aria-hidden="true"> {{ spaceActionIcon(data.acao_espaco) }}</span>
        </span>
    </div>
</template>

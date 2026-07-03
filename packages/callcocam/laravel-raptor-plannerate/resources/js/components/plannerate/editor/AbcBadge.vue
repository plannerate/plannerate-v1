<template>
    <!--
        Badge de sortimento da curva ABC: mostra a classe (A/B/C) e, ao lado,
        a recomendação sempre visível (Proteger / Potencializar / Monitorar / Retirar),
        colorida conforme a classe:
        - A → Proteger | B → Potencializar | C (manter) → Monitorar | C (retirar) → Retirar
        As dimensões (círculo, fonte, padding) escalam com o `scale` do planograma.
    -->
    <div
        v-if="classification && isVisible && isRecommendationActive(effectiveRecommendation)"
        class="flex items-center rounded-full bg-white/95 shadow-md"
        :style="pillStyle"
    >
        <!-- Letra da classe -->
        <span
            class="flex items-center justify-center rounded-full font-bold leading-none"
            :class="letterClasses"
            :style="letterStyle"
        >
            {{ classification }}
        </span>

        <!-- Recomendação de sortimento -->
        <span
            v-if="recommendationLabel"
            class="font-bold whitespace-nowrap leading-none"
            :class="labelTextClass"
            :style="labelStyle"
        >
            {{ recommendationLabel }}
        </span>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useAbcClassification } from '@/composables/plannerate/analysis/useAbcClassification';
import type { AbcRecommendation } from '@/composables/plannerate/analysis/useAbcClassification';
import { indicatorOrientation } from '@/composables/plannerate/core/useGondolaState';
import { useT } from '@/composables/useT';

interface Props {
    classification?: 'A' | 'B' | 'C';
    /**
     * Recomendação de sortimento. Se omitida, é derivada da classificação
     * (assumindo "monitorar" para produtos C, ou seja, manter no mix).
     */
    recommendation?: AbcRecommendation;
    /** Fator de escala do planograma (mesma base das demais medidas). */
    scale?: number;
}

const props = withDefaults(defineProps<Props>(), {
    scale: 3,
});

const { isVisible, isRecommendationActive } = useAbcClassification();
const { t } = useT();

/** Orientação atual do selo (vertical = rotacionado 90°, horizontal = normal). */
const orientation = computed(() => indicatorOrientation.value);

/** Diâmetro do círculo da letra, escalonado pelo planograma (mesma fórmula do PDF). */
const letterSize = computed(() => Math.max(6 * props.scale, 11));

/** Tamanho da fonte (letra e label), escalonado como no PDF. */
const fontSize = computed(() => Math.max(3.5 * props.scale, 6));

/**
 * Recomendação efetiva: usa a prop quando fornecida; caso contrário, deriva
 * da classificação (C sem informação extra é tratado como "monitorar").
 */
const effectiveRecommendation = computed<AbcRecommendation | undefined>(() => {
    if (props.recommendation) {
        return props.recommendation;
    }

    switch (props.classification) {
        case 'A':
            return 'proteger';
        case 'B':
            return 'potencializar';
        case 'C':
            return 'monitorar';
        default:
            return undefined;
    }
});

/**
 * Estilo do pill: padding/gap escalonados e transform de posicionamento.
 * - Horizontal: centraliza o pill (translateX(-50%)).
 * - Vertical: pivota a rotação no CENTRO DA LETRA (translateX(-half) + origin
 *   na letra), alinhando todas as bolinhas A/B/C na mesma base; a label cresce
 *   para cima, independente do comprimento (Proteger, Potencializar, etc.).
 * O componente pai posiciona o selo com a borda esquerda em left-1/2.
 */
const pillStyle = computed(() => {
    const half = letterSize.value / 2;
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

/** Dimensões do círculo da letra (fonte escalonada como no PDF). */
const letterStyle = computed(() => ({
    fontSize: `${fontSize.value}px`,
    width: `${letterSize.value}px`,
    height: `${letterSize.value}px`,
}));

/** Tamanho da fonte da label de recomendação, alinhado à letra da classe. */
const labelStyle = computed(() => ({
    fontSize: `${fontSize.value}px`,
}));

/**
 * Cor de fundo da letra da classe, alinhada à família de cores do badge.
 * "Retirar" usa um vermelho mais intenso para reforçar a ação.
 */
const letterClasses = computed(() => {
    switch (props.classification) {
        case 'A':
            return 'bg-green-500 text-white';
        case 'B':
            return 'bg-yellow-500 text-gray-900';
        case 'C':
            return effectiveRecommendation.value === 'retirar'
                ? 'bg-red-700 text-white'
                : 'bg-red-500 text-white';
        default:
            return 'bg-red-500 text-white';
    }
});

/**
 * Cor do texto da recomendação, tingida com a cor da classe para manter a
 * identidade visual do badge sobre o fundo claro do pill.
 */
const labelTextClass = computed(() => {
    switch (effectiveRecommendation.value) {
        case 'proteger':
            return 'text-green-700';
        case 'potencializar':
            return 'text-yellow-700';
        case 'monitorar':
            return 'text-red-600';
        case 'retirar':
            return 'text-red-700';
        default:
            return 'text-red-600';
    }
});

/**
 * Texto traduzido da recomendação (ex.: "Proteger", "Retirar").
 */
const recommendationLabel = computed(() => {
    if (!effectiveRecommendation.value) {
        return '';
    }

    return t(`plannerate.editor.abc_badge.recommendation.${effectiveRecommendation.value}`);
});
</script>

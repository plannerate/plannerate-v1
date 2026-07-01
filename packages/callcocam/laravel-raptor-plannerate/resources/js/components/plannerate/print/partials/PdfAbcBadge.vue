<template>
    <!--
        Selo de sortimento da curva ABC para o print/PDF: mostra a classe (A/B/C)
        e, ao lado, a recomendação (Proteger / Potencializar / Monitorar / Retirar),
        colorida conforme a classe. Segue o mesmo padrão do AbcBadge do editor,
        com dimensões escalonadas pelo fator do PDF.
    -->
    <div
        v-if="classification && isVisible && isClassActive(classification)"
        class="pointer-events-none absolute left-1/2 z-50 flex items-center rounded-full bg-white/95 shadow-md"
        :style="pillStyle"
        :title="t('plannerate.editor.abc_badge.title', { classification })"
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

const { isVisible, isClassActive } = useAbcClassification();
const { t } = useT();

/** Orientação atual do selo (vertical = rotacionado 90°, horizontal = normal). */
const orientation = computed(() => indicatorOrientation.value);

interface Props {
    classification?: 'A' | 'B' | 'C';
    /**
     * Recomendação de sortimento. Se omitida, é derivada da classificação
     * (assumindo "monitorar" para produtos C, ou seja, manter no mix).
     */
    recommendation?: AbcRecommendation;
    scale?: number;
}

const props = withDefaults(defineProps<Props>(), {
    scale: 1,
});

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
 * Dimensões do pill (padding e gap) escalonadas pelo fator do PDF, com
 * pisos mínimos para permanecerem legíveis em escalas pequenas.
 */
/** Diâmetro do círculo da letra (escalonado pelo PDF, com piso mínimo). */
const letterSize = computed(() => Math.max(6 * props.scale, 11));

const pillStyle = computed(() => {
    const base: Record<string, string> = {
        gap: `${Math.max(0.75 * props.scale, 1.5)}px`,
        padding: `${Math.max(0.5 * props.scale, 1)}px ${Math.max(1.5 * props.scale, 3)}px ${Math.max(0.5 * props.scale, 1)}px ${Math.max(0.5 * props.scale, 1)}px`,
        bottom: `${Math.max(1 * props.scale, 2)}px`,
    };

    // Metade do círculo da letra: usada para centralizar/pivotar na bolinha.
    const half = letterSize.value / 2;

    if (orientation.value === 'vertical') {
        // Rotaciona 90° pivotando no CENTRO DA LETRA (não no centro do pill),
        // para que a bolinha A/B/C fique sempre na mesma base, independente do
        // comprimento da label. O translateX(-half) centraliza a letra no
        // produto; o transform-origin fixa o pivô na letra.
        base.transformOrigin = `${half}px center`;
        base.transform = `translateX(-${half}px) rotate(-90deg)`;
        base.bottom = `${Math.max(2 * props.scale, 4)}px`;
    } else {
        // Horizontal: pill centralizado no produto, rente à base.
        base.transform = 'translateX(-50%)';
    }

    return base;
});

/** Dimensões da letra da classe (círculo colorido) escalonadas pelo PDF. */
const letterStyle = computed(() => ({
    fontSize: `${Math.max(3.5 * props.scale, 6)}px`,
    width: `${letterSize.value}px`,
    height: `${letterSize.value}px`,
}));

/** Tamanho da fonte da label de recomendação escalonado pelo PDF. */
const labelStyle = computed(() => ({
    fontSize: `${Math.max(3.5 * props.scale, 6)}px`,
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

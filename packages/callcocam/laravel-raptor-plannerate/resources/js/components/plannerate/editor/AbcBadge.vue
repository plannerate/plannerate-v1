<template>
    <div
        v-if="classification && isVisible"
        class="group relative flex items-center justify-center rounded-lg px-1 py-0.5 text-[10px] font-bold shadow-md"
        :class="badgeClasses"
    >
        {{ classification }}

        <!--
            Tooltip customizado com a recomendação de sortimento da curva ABC.
            Aparece no hover, posicionado acima do badge e colorido conforme a
            classe (mesma família de cores do badge):
            - A → Proteger | B → Potencializar | C (manter) → Monitorar | C (retirar) → Retirar
        -->
        <div
            v-if="recommendationLabel"
            role="tooltip"
            class="pointer-events-none absolute bottom-full left-1/2 z-200 mb-1.5 -translate-x-1/2 whitespace-nowrap rounded-md px-2 py-1 text-[10px] font-semibold opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100"
            :class="[tooltipBgClass, tooltipTextClass]"
        >
            {{ recommendationLabel }}
            <!-- Seta apontando para o badge -->
            <span
                class="absolute left-1/2 top-full size-2 -translate-x-1/2 -translate-y-1/2 rotate-45 rounded-[1px]"
                :class="tooltipBgClass"
            ></span>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useAbcClassification } from '@/composables/plannerate/analysis/useAbcClassification';
import type { AbcRecommendation } from '@/composables/plannerate/analysis/useAbcClassification';
import { useT } from '@/composables/useT';

interface Props {
    classification?: 'A' | 'B' | 'C';
    /**
     * Recomendação de sortimento. Se omitida, é derivada da classificação
     * (assumindo "monitorar" para produtos C, ou seja, manter no mix).
     */
    recommendation?: AbcRecommendation;
}

const props = defineProps<Props>();

const { isVisible } = useAbcClassification();
const { t } = useT();

/**
 * Classes CSS do badge baseadas na classificação ABC e na recomendação.
 * - A (Verde): Alta performance - produtos premium
 * - B (Amarelo): Média performance - produtos intermediários
 * - C Monitorar (Vermelho): baixa performance, manter e monitorar
 * - C Retirar (Vermelho intenso): baixa performance, retirar do mix
 */
const badgeClasses = computed(() => {
    switch (props.classification) {
        case 'A':
            return 'bg-green-500 text-white';
        case 'B':
            return 'bg-yellow-500 text-gray-900';
        case 'C':
            // Diferencia levemente "retirar" (vermelho mais intenso) de "monitorar"
            return effectiveRecommendation.value === 'retirar'
                ? 'bg-red-700 text-white'
                : 'bg-red-500 text-white';
        default:
            return 'bg-red-500 text-white';
    }
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
 * Texto traduzido exibido no tooltip (ex.: "Proteger", "Retirar").
 */
const recommendationLabel = computed(() => {
    if (!effectiveRecommendation.value) {
        return '';
    }

    return t(`plannerate.editor.abc_badge.recommendation.${effectiveRecommendation.value}`);
});

/**
 * Cor de fundo do tooltip, alinhada à família de cores do badge.
 * "Retirar" usa um vermelho mais intenso para reforçar a ação.
 */
const tooltipBgClass = computed(() => {
    switch (effectiveRecommendation.value) {
        case 'proteger':
            return 'bg-green-600';
        case 'potencializar':
            return 'bg-yellow-500';
        case 'monitorar':
            return 'bg-red-500';
        case 'retirar':
            return 'bg-red-700';
        default:
            return 'bg-red-500';
    }
});

/**
 * Cor do texto do tooltip, garantindo contraste com o fundo.
 */
const tooltipTextClass = computed(() =>
    effectiveRecommendation.value === 'potencializar' ? 'text-gray-900' : 'text-white',
);
</script>

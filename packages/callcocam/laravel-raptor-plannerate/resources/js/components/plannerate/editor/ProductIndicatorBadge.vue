<template>
    <!--
        Selo dinâmico de indicador exibido na frente do produto.
        Monta-se 100% a partir da configuração ativa em `editor/indicators.ts`:
        ícone, cores, valor e formatação vêm todos do registro — este componente
        não conhece nenhum indicador específico.
    -->
    <!--
        Posicionado DENTRO da base do produto (não pendurado abaixo), pois cada
        prateleira tem seu container de segmentos com z-index próprio — um selo
        que invadisse a faixa da prateleira de baixo seria coberto por ela,
        independentemente do z. z alto (acima do StockIndicator) garante que
        fique sobre o produto e segmentos vizinhos da mesma prateleira.
    -->
    <div
        v-if="config && isVisible"
        class="pointer-events-none absolute left-1/2 z-[90] flex items-center whitespace-nowrap rounded-md font-bold shadow-md"
        :class="config.badgeClass"
        :style="pillStyle"
        :title="displayValue"
    >
        <!-- <component :is="config.icon" class="size-3 shrink-0" :class="config.iconClass" /> -->
        <span :style="labelStyle">{{ displayValue }}</span>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useSalesIndicators } from '../../../composables/plannerate/analysis/useSalesIndicators';
import { indicatorOrientation, selectedIndicator } from '../../../composables/plannerate/core/useGondolaState';
import { getIndicatorConfig, type IndicatorContext } from '../../../composables/plannerate/editor/indicators';
import type { Product } from '../../../types/planogram';

interface Props {
    product?: Product;
    /** Fator de escala do planograma (mesma base de AbcBadge/PdfAbcBadge). */
    scale?: number;
}

const props = withDefaults(defineProps<Props>(), {
    scale: 3,
});

const { getIndicators } = useSalesIndicators();

/** Configuração do indicador atualmente selecionado (ou undefined se 'none'). */
const config = computed(() => getIndicatorConfig(selectedIndicator.value));

/** Orientação atual do selo (vertical = rotacionado 90°, horizontal = normal). */
const orientation = computed(() => indicatorOrientation.value);

/**
 * Estilo do pill escalonado pelo planograma, no MESMO padrão compacto de
 * AbcBadge/PdfAbcBadge (fonte, gap e padding com pisos mínimos). O selo fica
 * acima da base do produto (bottom escalonado) para não colidir com o selo ABC,
 * centralizado horizontalmente e rotacionado 90° na orientação vertical.
 */
const pillStyle = computed(() => {
    const padY = Math.max(0.5 * props.scale, 1);
    const padX = Math.max(1.5 * props.scale, 3);
    // Na orientação horizontal o selo sobe um pouco mais (afasta da base do
    // produto e do selo ABC); na vertical mantém a distância original.
    const bottom =
        orientation.value === 'vertical'
            ? Math.max(6 * props.scale, 12)
            : Math.max(11 * props.scale, 22);
    const base: Record<string, string> = {
        gap: `${Math.max(0.75 * props.scale, 1.5)}px`,
        padding: `${padY}px ${padX}px`,
        bottom: `${bottom}px`,
        transform:
            orientation.value === 'vertical'
                ? 'translateX(-50%) rotate(-90deg)'
                : 'translateX(-50%)',
    };

    return base;
});

/** Tamanho da fonte da label, alinhado ao padrão dos selos ABC. */
const labelStyle = computed(() => ({
    fontSize: `${Math.max(3.5 * props.scale, 6)}px`,
}));

/**
 * Contexto entregue aos callbacks do indicador. Para indicadores de vendas,
 * anexa o resumo de vendas do produto buscado por EAN na store.
 */
const context = computed<IndicatorContext | null>(() => {
    if (!props.product) {
        return null;
    }
    return {
        product: props.product,
        sales: config.value?.source === 'sales' ? getIndicators(props.product.ean) : undefined,
    };
});

/**
 * Valor bruto do indicador para este produto.
 * Usa `accessor` quando definido (campos calculados / vendas), senão lê o `field` direto.
 */
const rawValue = computed(() => {
    if (!config.value || !context.value) {
        return undefined;
    }
    if (config.value.accessor) {
        return config.value.accessor(context.value);
    }
    return config.value.field ? context.value.product[config.value.field] : undefined;
});

/** Valor já formatado para exibição (moeda, percentual, unidades, etc.). */
const displayValue = computed(() => {
    if (!config.value || !context.value) {
        return '';
    }
    return config.value.format
        ? config.value.format(rawValue.value, context.value)
        : String(rawValue.value ?? '');
});

/**
 * Define se o selo deve renderizar. Cada indicador pode ter sua própria regra
 * (`shouldShow`); na ausência dela, exibe sempre que houver valor não-vazio.
 */
const isVisible = computed(() => {
    if (!config.value || !context.value) {
        return false;
    }
    if (config.value.shouldShow) {
        return config.value.shouldShow(rawValue.value, context.value);
    }
    return rawValue.value !== null && rawValue.value !== undefined && rawValue.value !== '';
});
</script>

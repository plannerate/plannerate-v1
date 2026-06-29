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
        class="pointer-events-none absolute bottom-1 left-1/2 z-[90] flex -translate-x-1/2 items-center gap-1 whitespace-nowrap rounded-md px-1.5 py-0.5 text-[10px] font-bold shadow-md ring-1 ring-black/10"
        :class="config.badgeClass"
        :title="displayValue"
    >
        <component :is="config.icon" class="size-3 shrink-0" :class="config.iconClass" />
        <span>{{ displayValue }}</span>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useSalesIndicators } from '../../../composables/plannerate/analysis/useSalesIndicators';
import { selectedIndicator } from '../../../composables/plannerate/core/useGondolaState';
import { getIndicatorConfig, type IndicatorContext } from '../../../composables/plannerate/editor/indicators';
import type { Product } from '../../../types/planogram';

interface Props {
    product?: Product;
}

const props = defineProps<Props>();

const { getIndicators } = useSalesIndicators();

/** Configuração do indicador atualmente selecionado (ou undefined se 'none'). */
const config = computed(() => getIndicatorConfig(selectedIndicator.value));

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

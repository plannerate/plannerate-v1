<template>
    <div class="flex items-center" :style="containerStyle">
        <div v-for="(_, index) in getQuantity" :key="index" class="z-20 cursor-pointer transition-all">
            <img v-if="hasImage" :src="displayImageUrl!" :alt="product?.name" :style="style"
                class="z-20 object-cover" />
            <ProductPlaceholder v-else class="z-20" :width="placeholderWidth" :height="placeholderHeight"
                :name="product?.name" :ean="product?.ean" />
        </div>
    </div>
</template>
<script setup lang="ts">

import { computed } from 'vue';
import type { Layer, Product, Segment } from '@/types/planogram';
import ProductPlaceholder from '../../editor/ProductPlaceholder.vue';

interface Props {
    segment: Segment;
    layer: Layer | undefined;
    scale: number;
    isSelected?: boolean;
    /**
     * Gap uniforme (px) aplicado como column-gap entre as frentes do produto no
     * modo justificar — alinhado ao espaçamento usado entre os segmentos.
     */
    facingGap?: number;
}

const props = defineProps<Props>();

const product = computed<Product | undefined>(() => props.layer?.product);

const containerStyle = computed(() => {
    if (props.facingGap == null) {
        return undefined;
    }

    return {
        columnGap: `${props.facingGap}px`,
    };
});


/**
 * Imagem exibida no PDF: usa EXCLUSIVAMENTE a versão embutida/base64
 * (`image_url_encoded`). URLs externas (`image_url`) não são embarcadas na
 * captura para PDF — cairiam em branco —, por isso, na ausência do base64,
 * renderizamos o placeholder SVG em vez de tentar a URL remota.
 */
const displayImageUrl = computed<string | null>(
    () => product.value?.image_url_encoded ?? null,
);

/**
 * O backend embute uma arte de fallback (img/fallback/*) para produto sem
 * imagem; esticada na caixa do produto ela distorce, então tratamos como "sem
 * imagem" e desenhamos o placeholder SVG — vetorial, adapta-se a qualquer
 * proporção e não depende de carregamento externo na captura do PDF.
 */
const hasImage = computed<boolean>(() => {
    const url = displayImageUrl.value;

    return Boolean(url) && !url!.includes('fallback');
});

/**
 * Quantidade de frentes (facings) deste layer, normalizada igual ao editor:
 * inteiro, no mínimo 1 e no máximo 500.
 */
const getQuantity = computed<number>(() => {
    const quantity = Number(props.layer?.quantity ?? 1);

    if (!Number.isFinite(quantity)) {
        return 1;
    }

    return Math.max(1, Math.min(500, Math.trunc(quantity)));
});

const scale = computed(() => props.scale || 3);

const productWidth = computed(
    () => (product.value?.width || 0) * scale.value,
);
const productHeight = computed(
    () => (product.value?.height || 0) * scale.value,
);

const placeholderWidth = computed(() => productWidth.value || 20);
const placeholderHeight = computed(() => productHeight.value || 20);

const style = computed(() => ({
    width: `${productWidth.value || 10}px`,
    height: `${productHeight.value || 10}px`,
}));

</script>

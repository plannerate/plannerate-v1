<template>
    <div class="flex items-center" :style="containerStyle">
        <div v-for="(_, index) in getQuantity" :key="index" class="z-20 cursor-pointer transition-all">
            <img v-if="displayImageUrl" :src="displayImageUrl" :alt="product?.name" :style="style"
                class="z-20 object-cover" />
            <div v-else :style="style" class="flex items-center justify-center border border-dashed bg-muted">
                <span class="text-xs text-muted-foreground">{{
                    product?.name || t('plannerate.sidebar.product_image_card.no_image')
                    }}</span>
            </div>
        </div>
    </div>
</template>
<script setup lang="ts">

import { computed } from 'vue';
import { useT } from '@/composables/useT';
import type { Layer, Product, Segment } from '@/types/planogram';

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
const { t } = useT();

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
 * renderizamos o placeholder de fallback em vez de tentar a URL remota.
 */
const displayImageUrl = computed<string | null>(
    () => product.value?.image_url_encoded ?? null,
);

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

const style = computed(() => {
    const imageUrl = displayImageUrl.value;

    //  Verifica se a imagem do produto está disponível.
    // Se não houver imagem ou o caminho contiver 'fallback', renderiza o
    // placeholder com altura e largura padrão iguais às do editor.
    if (!imageUrl || imageUrl.includes('fallback')) {
        // Placeholder (caixa tracejada via classe bg-muted). NÃO usa imagem de
        // fundo externa para não depender de carregamento na captura do PDF;
        // mantém apenas as dimensões iguais às do editor.
        return {
            width: `${productWidth.value || 20}px`,
            height: `${productHeight.value || 20}px`,
        };
    }

    return {
        width: `${productWidth.value || 10}px`,
        height: `${productHeight.value || 10}px`,
    };
});

</script>

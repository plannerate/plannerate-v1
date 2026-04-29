<template>
    <!-- Produtos um do lado do outro (horizontalmente) -->
    <div class="flex items-center" :class="internalAlignmentClass" :style="distributionContainerStyle">
        <div
            v-for="(_, index) in getQuantity"
            :key="index"
            class="z-20 cursor-pointer transition-all"
        >
            <img
                v-if="product?.image_url"
                :src="product.image_url"
                :alt="product.name"
                :style="style"
                class="z-20 object-cover"
                v-on:error="getDefaultImage"
            />
            <div
                v-else
                :style="style"
                class="flex items-center justify-center border border-dashed bg-muted"
            >
                <span class="text-xs text-muted-foreground">{{
                    product?.name || 'Sem imagem'
                }}</span>
            </div>
        </div>
    </div>
</template>
<script setup lang="ts">
import { computed } from 'vue';
import type { Layer, Product, Segment } from '../../../types/planogram';

interface Props {
    segment: Segment;
    layer: Layer | undefined;
    scale: number;
    isSelected?: boolean;
    distributionWidth?: number;
    internalAlignment?: 'left' | 'right' | 'center' | 'justify';
}

const props = defineProps<Props>();

const product = computed<Product | undefined>(() => props.layer?.product);

const getQuantity = computed<number>(() => {
    const quantity = Number(props.layer?.quantity ?? 1);

    if (!Number.isFinite(quantity)) {
        return 1;
    }

    return Math.max(0, Math.min(500, Math.trunc(quantity)));
});

const internalAlignmentClass = computed(() => {
    const align = props.internalAlignment;

    if (!align) {
        return '';
    }

    if (align === 'justify') {
        return getQuantity.value <= 1 ? 'justify-center' : 'justify-between';
    }

    const map: Record<string, string> = {
        left: 'justify-start',
        right: 'justify-end',
        center: 'justify-center',
    };

    return map[align] || '';
});

const distributionContainerStyle = computed(() => {
    if (!props.distributionWidth) {
        return undefined;
    }

    return {
        width: `${props.distributionWidth}px`,
    };
});

const scale = computed(() => props.scale || 3);

const productWidth = computed(() => (product.value?.width || 0) * scale.value);
const productHeight = computed(
    () => (product.value?.height || 0) * scale.value,
);

const style = computed(() => {
    //  Verifica se a imagem do produto está disponível
    // Se ele tem no caminho a palavra 'fallback', então não é uma imagem real
    if (
        !product.value?.image_url ||
        product.value.image_url.includes('fallback')
    ) { 
        return {
            width: `${productWidth.value || 20}px`,
            height: `${productHeight.value || 20}px`,
            backgroundPosition: 'center',
            backgroundSize: 'cover',
            backgroundRepeat: 'no-repeat',
            backgroundImage: `url('/img/fallback/fall6.jpg')`,
        };
    }

    return {
        width: `${productWidth.value || 10}px`,
        height: `${productHeight.value || 10}px`,
    };
});

const getDefaultImage = (event: Event) => {
    const target = event.target as HTMLImageElement;
    target.src = '/img/fallback/fall6.jpg';
};
</script>

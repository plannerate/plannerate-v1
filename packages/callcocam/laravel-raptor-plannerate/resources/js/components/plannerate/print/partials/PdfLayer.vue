<template>
    <div class="flex items-center">
        <div v-for="(_, index) in getQuantity" :key="index" class="z-20 cursor-pointer transition-all">
            <img v-if="product?.image_url_encoded" :src="product.image_url_encoded" :alt="product.name" :style="style"
                class="z-20 object-cover" />
            <div v-else :style="style" class="flex items-center justify-center border border-dashed bg-muted">
                <span class="text-xs text-muted-foreground">{{
                    product?.name || 'Sem imagem'
                    }}</span>
            </div>
        </div>
    </div>
</template>
<script setup lang="ts">

import { computed } from 'vue';
import type { Layer, Product, Segment } from '@/types/planogram';

interface Props {
    segment: Segment;
    layer: Layer | undefined;
    scale: number;
    isSelected?: boolean;
}

const props = defineProps<Props>();

const product = computed<Product | undefined>(() => props.layer?.product);


const getQuantity = computed(() => props.layer?.quantity || 1);

const scale = computed(() => props.scale || 3);

const productWidth = computed(
    () => (product.value?.width || 0) * scale.value,
);
const productHeight = computed(
    () => (product.value?.height || 0) * scale.value,
);

const style = computed(() => {
    //  Verifica se a imagem do produto está disponível
    // Se ele tem no caminho a palavra 'fallback', então não é uma imagem real
    if (!product.value?.image_url || product.value.image_url.includes('fallback')) {
        return {
            width: `${productWidth.value || 10}px`,
            backgroundPosition: 'center',
            backgroundSize: 'cover',
            backgroundRepeat: 'no-repeat',
        };
    }

    return {
        width: `${productWidth.value || 10}px`,
        height: `${productHeight.value || 10}px`,
    };
});

</script>
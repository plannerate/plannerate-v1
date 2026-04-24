<template>
    <div class="flex items-center">
        <div 
            v-for="(_, index) in getQuantity" 
            :key="index" 
            class="z-20 transition-all cursor-pointer hover:opacity-80"
            @click="emit('click')"
        >
            <img 
                v-if="product?.image_url" 
                :src="product.image_url" 
                :alt="product.name" 
                :style="style"
                class="z-20 object-cover" 
                @error="getDefaultImage" 
            />
            <div v-else :style="style" class="flex items-center justify-center border border-dashed bg-muted">
                <span class="text-xs text-muted-foreground">
                    {{ product?.name || 'Sem imagem' }}
                </span>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Layer, Product, Segment } from '@/types/planogram';
import { computed } from 'vue';

interface Props {
    segment: Segment;
    layer: Layer | undefined;
    scale: number;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'click'): void;
}>();

const product = computed<Product | undefined>(() => props.layer?.product);
const getQuantity = computed<number>(() => {
    const quantity = Number(props.layer?.quantity ?? 1);

    if (!Number.isFinite(quantity)) {
        return 1;
    }

    return Math.max(0, Math.min(500, Math.trunc(quantity)));
});
const scale = computed(() => props.scale || 3);

const productWidth = computed(
    () => (product.value?.width || 0) * scale.value,
);

const productHeight = computed(
    () => (product.value?.height || 0) * scale.value,
);

const style = computed(() => {
    if (!product.value?.image_url || product.value.image_url.includes('fallback')) {
        return {
            width: `${productWidth.value}px`,
            backgroundPosition: 'center',
            backgroundSize: 'contain',
            backgroundRepeat: 'no-repeat',
        };
    }
    return {
        width: `${productWidth.value}px`,
        height: `${productHeight.value}px`,
    };
});

const getDefaultImage = (event: Event) => {
    const target = event.target as HTMLImageElement;
    target.src = '/img/fallback/fall6.jpg';
};
</script>


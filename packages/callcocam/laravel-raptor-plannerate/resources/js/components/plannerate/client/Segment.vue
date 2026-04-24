<template>
    <div class="relative flex flex-col items-start">
        <!-- ABC Badge -->
        <AbcBadge v-if="layer?.product?.ean" :classification="abcClassification" />
        
        <!-- Stock Indicator -->
        <StockIndicator 
            :segment="segment" 
            :shelf-depth="shelfDepth" 
            @click="handleProductClick"
        />
        
        <div
            v-for="(_, index) in getQuantity"
            :key="`segment-layer-${index}`"
            class="flex flex-col"
        >
            <div v-if="layer">
                <LayerRenderer
                    :layer="layer"
                    :segment="segment"
                    :scale="props.scale"
                    @click="handleProductClick"
                />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Layer, Segment } from '@/types/planogram';
import { computed } from 'vue';
import { useAbcClassification } from '@/composables/plannerate/v3/useAbcClassification';
import LayerRenderer from './Layer.vue';
import AbcBadge from './AbcBadge.vue';
import StockIndicator from './StockIndicator.vue';

interface Props {
    segment: Segment;
    scale: number;
    shelfDepth?: number;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'product-click', productEan: string): void;
}>();

const layer = computed<Layer | undefined>(() => props.segment.layer);
const getQuantity = computed(() => props.segment.quantity || 1);

// Usa composable de classificação ABC
const { getClassification } = useAbcClassification();

const abcClassification = computed(() => {
    const ean = layer.value?.product?.ean;
    return getClassification(ean);
});

const handleProductClick = () => {
    if (layer.value?.product?.ean) {
        emit('product-click', layer.value.product.ean);
    }
};
</script>


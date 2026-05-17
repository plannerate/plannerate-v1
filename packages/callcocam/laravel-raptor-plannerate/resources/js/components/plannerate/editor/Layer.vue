<template>
    <!-- Produtos um do lado do outro (horizontalmente) -->
    <div class="flex items-center" :class="internalAlignmentClass" :style="distributionContainerStyle">
        <div
            v-for="(_, index) in getQuantity"
            :key="index"
            class="z-20 cursor-pointer transition-all"
        >
            <img
                v-if="displayImageUrl"
                :src="displayImageUrl"
                :alt="product?.name"
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
                    product?.name || t('plannerate.sidebar.product_image_card.no_image')
                }}</span>
            </div>
        </div>
    </div>
</template>
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useT } from '@/composables/useT';
import { useProductImageStore } from '@/composables/useProductImageStore';
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
const { t } = useT();
const { getImage, listenForProductImages } = useProductImageStore();

const product = computed<Product | undefined>(() => props.layer?.product);

const page = usePage();
const tenantId = (page.props.tenant as { id?: string } | null)?.id ?? null;
if (tenantId) {
    listenForProductImages(tenantId);
}

const displayImageUrl = computed<string | null>(() => {
    const fromStore = getImage(product.value?.ean, product.value?.id);
    return fromStore ?? product.value?.image_url ?? null;
});

const getQuantity = computed<number>(() => {
    const quantity = Number(props.layer?.quantity ?? 1);

    if (!Number.isFinite(quantity)) {
        return 1;
    }

    return Math.max(1, Math.min(500, Math.trunc(quantity)));
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
    const imageUrl = displayImageUrl.value;

    if (!imageUrl || imageUrl.includes('fallback')) {
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

<template>
    <!-- Produtos um do lado do outro (horizontalmente) -->
    <div class="flex items-center" :style="distributionContainerStyle">
        <div
            v-for="(_, index) in getQuantity"
            :key="index"
            class="z-20 cursor-pointer"
        >
            <img
                v-if="hasImage"
                :src="displayImageUrl!"
                :alt="product?.name"
                :style="style"
                class="z-20 object-cover"
                decoding="async"
                :data-module="moduleNumber"
                :data-shelf="shelfNumber"
                :data-ean="product?.ean"
                @error="onImageError"
            />
            <ProductPlaceholder
                v-else
                class="z-20"
                :width="placeholderWidth"
                :height="placeholderHeight"
                :name="product?.name"
                :ean="product?.ean"
                :data-module="moduleNumber"
                :data-shelf="shelfNumber"
                :data-ean="product?.ean"
            />
        </div>
    </div>
</template>
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useProductImageStore } from '@/composables/useProductImageStore';
import type { Layer, Product, Segment } from '../../../types/planogram';
import ProductPlaceholder from './ProductPlaceholder.vue';

interface Props {
    segment: Segment;
    layer: Layer | undefined;
    scale: number;
    isSelected?: boolean;
    /**
     * Gap uniforme (px) aplicado como column-gap entre as frentes do produto no
     * modo justificar. Quando definido, os produtos deste layer ficam espaçados
     * com o mesmo vão usado entre os segmentos da prateleira.
     */
    facingGap?: number;
    /** Diagnóstico: nº do módulo e da prateleira, expostos como data-attrs na imagem
     *  para correlacionar a lentidão de clique (qual módulo/prateleira) no profiling. */
    moduleNumber?: number | string;
    shelfNumber?: number | string;
}

const props = defineProps<Props>();
const { getImage, listenForProductImages, listenForBatchComplete } = useProductImageStore();

const product = computed<Product | undefined>(() => props.layer?.product);

const page = usePage();
const tenantId = (page.props.tenant as { id?: string } | null)?.id ?? null;
const userId = (page.props.auth as { user?: { id: string } } | null)?.user?.id ?? null;

if (tenantId) {
listenForProductImages(tenantId);
}

if (userId) {
listenForBatchComplete(userId);
}

const displayImageUrl = computed<string | null>(() => {
    const fromStore = getImage(product.value?.ean, product.value?.id);

    return fromStore ?? product.value?.image_url ?? null;
});

/**
 * O accessor `image_url` do pacote devolve uma arte de fallback (img/fallback/*)
 * quando o produto não tem imagem — esticá-la na caixa do produto distorce a
 * arte. Tratamos essas URLs como "sem imagem" e desenhamos o placeholder SVG,
 * que se adapta a qualquer proporção.
 */
const isFallbackUrl = (url: string): boolean => url.includes('/img/fallback/');

const imageFailed = ref(false);

watch(displayImageUrl, () => {
    imageFailed.value = false;
});

const onImageError = (): void => {
    imageFailed.value = true;
};

const hasImage = computed<boolean>(() => {
    const url = displayImageUrl.value;

    return Boolean(url) && !isFallbackUrl(url!) && !imageFailed.value;
});

const getQuantity = computed<number>(() => {
    const quantity = Number(props.layer?.quantity ?? 1);

    if (!Number.isFinite(quantity)) {
        return 1;
    }

    return Math.max(1, Math.min(500, Math.trunc(quantity)));
});

const distributionContainerStyle = computed(() => {
    if (props.facingGap == null) {
        return undefined;
    }

    return {
        columnGap: `${props.facingGap}px`,
    };
});

const scale = computed(() => props.scale || 3);

const productWidth = computed(() => (product.value?.width || 0) * scale.value);
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

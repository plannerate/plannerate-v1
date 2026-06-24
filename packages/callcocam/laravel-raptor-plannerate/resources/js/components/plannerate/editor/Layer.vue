<template>
    <!-- Produtos um do lado do outro (horizontalmente) -->
    <div class="flex items-center" :style="distributionContainerStyle">
        <div
            v-for="(_, index) in getQuantity"
            :key="index"
            class="z-20 cursor-pointer"
        >
            <img
                v-if="displayImageUrl"
                :src="displayImageUrl"
                :alt="product?.name"
                :style="style"
                class="z-20 object-cover"
                decoding="async"
                :data-module="moduleNumber"
                :data-shelf="shelfNumber"
                :data-ean="product?.ean" 
            />
            <div
                v-else
                :style="style"
                class="flex items-center justify-center border border-dashed bg-muted"
                :data-module="moduleNumber"
                :data-shelf="shelfNumber"
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
const { t } = useT();
const { getImage, listenForProductImages, listenForBatchComplete } = useProductImageStore();

const product = computed<Product | undefined>(() => props.layer?.product);

const page = usePage();
const tenantId = (page.props.tenant as { id?: string } | null)?.id ?? null;
const userId = (page.props.auth as { user?: { id: string } } | null)?.user?.id ?? null;

if (tenantId) listenForProductImages(tenantId);
if (userId) listenForBatchComplete(userId);

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

<template>
    <div class="space-y-4">
        <!-- Cabeçalho da aba -->
        <div>
            <h3 class="text-xl font-bold leading-tight text-foreground">
                {{ t('plannerate.sidebar.segment_details.headers.images_title') }}
            </h3>
            <p class="text-sm text-muted-foreground">
                {{ t('plannerate.sidebar.segment_details.headers.images_subtitle') }}
            </p>
        </div>

        <!-- Card: Imagem Principal -->
        <SegmentCard
            :icon="ImageIcon"
            color="blue"
            :title="t('plannerate.sidebar.segment_details.cards.main_image')"
        >
            <div v-if="product" class="flex flex-col items-center gap-4">
                <img
                    v-if="!isFallback"
                    :src="product.image_url"
                    :alt="product.name"
                    class="h-40 w-40 rounded-md border object-contain"
                />
                <div
                    v-else
                    class="flex h-40 w-40 items-center justify-center rounded-md border bg-muted"
                >
                    <span class="text-xs text-muted-foreground">
                        {{ t('plannerate.sidebar.segment_details.images.no_image') }}
                    </span>
                </div>

                <!-- Botões de ação da imagem -->
                <div v-if="showUploadButton" class="flex flex-wrap justify-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="isDownloading || !product.ean"
                        :title="product.ean
                            ? t('plannerate.sidebar.product_image_card.download_and_update')
                            : t('plannerate.sidebar.product_image_card.product_without_ean')"
                        @click="handleDownload"
                    >
                        <Loader2 v-if="isDownloading" class="mr-2 size-3.5 animate-spin" />
                        <Download v-else class="mr-2 size-3.5" />
                        {{ t('plannerate.sidebar.segment_details.images.download') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :title="t('plannerate.sidebar.product_image_card.manual_upload')"
                        @click="$emit('upload')"
                    >
                        <Upload class="mr-2 size-3.5" />
                        {{ t('plannerate.sidebar.segment_details.images.replace') }}
                    </Button>
                    <Button
                        v-if="!isFallback"
                        type="button"
                        variant="outline"
                        size="sm"
                        class="border-destructive/40 text-destructive hover:bg-destructive/10 hover:text-destructive"
                        :title="t('plannerate.sidebar.product_image_card.remove_image')"
                        @click="$emit('delete')"
                    >
                        <Trash2 class="mr-2 size-3.5" />
                        {{ t('plannerate.sidebar.segment_details.images.delete') }}
                    </Button>
                </div>
            </div>
        </SegmentCard>

        <!-- Card: Imagens Complementares -->
        <SegmentCard
            :icon="Images"
            color="purple"
            :title="t('plannerate.sidebar.segment_details.cards.complementary_images')"
        >
            <div class="grid grid-cols-2 gap-2">
                <div
                    v-for="slot in complementarySlots"
                    :key="slot.key"
                    class="flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-border py-5 text-center"
                >
                    <span class="text-sm font-medium text-muted-foreground">{{ slot.label }}</span>
                    <Camera class="size-6 text-muted-foreground/50" />
                    <span class="text-xs text-muted-foreground">
                        {{ t('plannerate.sidebar.segment_details.images.not_available_image') }}
                    </span>
                </div>
            </div>
        </SegmentCard>

        <!-- Card: Status das Imagens -->
        <SegmentCard
            :icon="ShieldCheck"
            color="emerald"
            :title="t('plannerate.sidebar.segment_details.cards.images_status')"
        >
            <div class="grid grid-cols-1 gap-2">
                <div
                    v-for="status in imageStatuses"
                    :key="status.key"
                    class="space-y-1 rounded-lg border border-border p-3"
                >
                    <p class="text-xs text-muted-foreground">{{ status.label }}</p>
                    <p
                        class="flex items-center gap-1.5 text-sm font-semibold"
                        :class="status.available ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'"
                    >
                        <CheckCircle2 v-if="status.available" class="size-4" />
                        <MinusCircle v-else class="size-4" />
                        {{ status.available
                            ? t('plannerate.sidebar.segment_details.images.available')
                            : t('plannerate.sidebar.segment_details.images.not_available') }}
                    </p>
                </div>
            </div>
        </SegmentCard>
    </div>
</template>

<script setup lang="ts">
import {
    Camera,
    CheckCircle2,
    Download,
    Image as ImageIcon,
    Images,
    Loader2,
    MinusCircle,
    ShieldCheck,
    Trash2,
    Upload,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useProductImage } from '@/composables/plannerate/products/useProductImage';
import { useT } from '@/composables/useT';
import type { Product } from '@/types/planogram';
import SegmentCard from './SegmentCard.vue';

interface Props {
    /** Produto do segmento */
    product?: Product | null;
    /** Exibe botões de upload/deleção */
    showUploadButton?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showUploadButton: true,
});
const { t } = useT();
const { isDownloading, downloadAndUpdateImage } = useProductImage();

defineEmits<{
    upload: [];
    delete: [];
}>();

/**
 * Detecta se a imagem é o fallback (fall4.jpg).
 */
const isFallback = computed(() => {
    return !props.product?.image_url || props.product.image_url.includes('fall4.jpg');
});

/** Slots de imagens complementares (ainda sem suporte de upload) */
const complementarySlots = computed(() => [
    { key: 'side', label: t('plannerate.sidebar.segment_details.images.side') },
    { key: 'top', label: t('plannerate.sidebar.segment_details.images.top') },
]);

/** Status de disponibilidade de cada ângulo de imagem */
const imageStatuses = computed(() => [
    { key: 'frontal', label: t('plannerate.sidebar.segment_details.images.status_frontal'), available: !isFallback.value },
    { key: 'lateral', label: t('plannerate.sidebar.segment_details.images.status_lateral'), available: false },
    { key: 'top', label: t('plannerate.sidebar.segment_details.images.status_top'), available: false },
]);

async function handleDownload() {
    if (props.product?.id && props.product?.ean) {
        await downloadAndUpdateImage(props.product.id, props.product.ean);
    }
}
</script>

<template>
    <div class="space-y-4">
        <!-- Imagem Frontal -->
        <div class="space-y-2">
            <p class="text-xs font-semibold text-foreground">
                {{ t('plannerate.sidebar.segment_details.images.front') }}
            </p>
            <div v-if="product" class="flex flex-col items-center gap-3">
                <!-- Imagem -->
                <img
                    v-if="!isFallback"
                    :src="product.image_url"
                    :alt="product.name"
                    class="h-36 w-36 rounded-md border object-contain"
                />
                <div
                    v-else
                    class="flex h-36 w-36 items-center justify-center rounded-md border bg-muted"
                >
                    <span class="text-xs text-muted-foreground">
                        {{ t('plannerate.sidebar.segment_details.images.no_image') }}
                    </span>
                </div>

                <!-- Botões de ação -->
                <div v-if="showUploadButton" class="flex gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        size="icon"
                        class="h-7 w-7"
                        :disabled="isDownloading || !product.ean"
                        :title="product.ean
                            ? t('plannerate.sidebar.product_image_card.download_and_update')
                            : t('plannerate.sidebar.product_image_card.product_without_ean')"
                        @click="handleDownload"
                    >
                        <Loader2 v-if="isDownloading" class="h-3.5 w-3.5 animate-spin" />
                        <Download v-else class="h-3.5 w-3.5" />
                    </Button>
                    <Button
                        type="button"
                        variant="secondary"
                        size="icon"
                        class="h-7 w-7"
                        :title="t('plannerate.sidebar.product_image_card.manual_upload')"
                        @click="$emit('upload')"
                    >
                        <Upload class="h-3.5 w-3.5" />
                    </Button>
                    <Button
                        v-if="!isFallback"
                        type="button"
                        variant="destructive"
                        size="icon"
                        class="h-7 w-7"
                        :title="t('plannerate.sidebar.product_image_card.remove_image')"
                        @click="$emit('delete')"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                    </Button>
                </div>
            </div>
        </div>

        <Separator />

        <!-- Imagem Lateral (placeholder) -->
        <div class="space-y-2">
            <p class="text-xs font-semibold text-foreground">
                {{ t('plannerate.sidebar.segment_details.images.side') }}
            </p>
            <div class="flex h-20 w-full items-center justify-center rounded-md border-2 border-dashed border-muted-foreground/30 bg-muted/20">
                <span class="text-xs text-muted-foreground">
                    {{ t('plannerate.sidebar.segment_details.images.not_available') }}
                </span>
            </div>
        </div>

        <!-- Imagem Topo (placeholder) -->
        <div class="space-y-2">
            <p class="text-xs font-semibold text-foreground">
                {{ t('plannerate.sidebar.segment_details.images.top') }}
            </p>
            <div class="flex h-20 w-full items-center justify-center rounded-md border-2 border-dashed border-muted-foreground/30 bg-muted/20">
                <span class="text-xs text-muted-foreground">
                    {{ t('plannerate.sidebar.segment_details.images.not_available') }}
                </span>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Download, Loader2, Trash2, Upload } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useProductImage } from '@/composables/plannerate/products/useProductImage';
import { useT } from '@/composables/useT';
import type { Product } from '@/types/planogram';

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
 * O accessor getImageUrlAttribute sempre retorna uma URL, mesmo sem imagem.
 */
const isFallback = computed(() => {
    return !props.product?.image_url || props.product.image_url.includes('fall4.jpg');
});

async function handleDownload() {
    if (props.product?.id && props.product?.ean) {
        await downloadAndUpdateImage(props.product.id, props.product.ean);
    }
}
</script>

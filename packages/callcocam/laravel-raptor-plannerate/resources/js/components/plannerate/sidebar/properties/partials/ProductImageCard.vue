<template>
    <div class="flex items-start gap-3">
        <div class="group relative">
            <img v-if="hasImage" :src="product.image_url" :alt="product.name"
                class="h-20 w-20 rounded border object-contain" @error="onImageError" />
            <ProductPlaceholder v-else :width="80" :height="80" :name="product.name" :ean="product.ean" />
        </div>
        <div class="min-w-0 flex-1 space-y-2">

            <!-- Código e EAN lado a lado -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <p class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                        {{ t('plannerate.sidebar.product_image_card.product_code') }}
                    </p>
                    <p class="font-mono text-xs">{{ product.codigo_erp || '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                        {{ t('plannerate.sidebar.product_image_card.ean') }}
                    </p>
                    <p class="font-mono text-xs">
                        {{ product.ean || t('plannerate.sidebar.product_image_card.no_ean') }}
                    </p>
                </div>
            </div>

            <!-- Nome do produto -->
            <div>
                <p class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                    {{ t('plannerate.sidebar.product_image_card.name') }}
                </p>
                <p class="text-sm font-semibold leading-tight">{{ product.name || '—' }}</p>
            </div>
            <!-- Categoria --> 

            <!-- Botões de ação da imagem -->
            <div class="mt-3 flex w-full justify-end gap-1">
                <Button type="button" variant="secondary" size="icon" :disabled="isDownloading || !product.ean"
                    class="h-6 w-6" @click="handleDownload" :title="product.ean
                        ? t(
                            'plannerate.sidebar.product_image_card.download_and_update',
                        )
                        : t('plannerate.sidebar.product_image_card.product_without_ean')
                        ">
                    <Loader2 v-if="isDownloading" class="h-3 w-3 animate-spin" />
                    <Download v-else class="h-3 w-3" />
                </Button>

                <Button type="button" v-if="showUploadButton" variant="secondary" size="icon" class="h-6 w-6"
                    @click="$emit('upload')" :title="t('plannerate.sidebar.product_image_card.manual_upload')">
                    <Upload class="h-3 w-3" />
                </Button>

                <Button type="button" v-if="showUploadButton && product.image_url" variant="destructive" size="icon"
                    class="h-6 w-6" @click="$emit('delete')"
                    :title="t('plannerate.sidebar.product_image_card.remove_image')">
                    <Trash2 class="h-3 w-3" />
                </Button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Download, Loader2, Trash2, Upload } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { useProductImage } from '@/composables/plannerate/products/useProductImage';
import { useT } from '@/composables/useT';
import type { Product } from '@/types/planogram';
import ProductPlaceholder from '../../../editor/ProductPlaceholder.vue';

interface Props {
    product: Product;
    showUploadButton?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showUploadButton: false,
});
const { t } = useT();

defineEmits<{
    upload: [];
    delete: [];
}>();

const { isDownloading, downloadAndUpdateImage } = useProductImage();

/**
 * `product.image_url` costuma vir preenchido antes do arquivo existir de fato
 * no storage — sem tratamento de erro, o `<img>` mostrava o ícone quebrado do
 * navegador em vez do placeholder "sem imagem" que já existia para o caso de
 * URL ausente.
 */
const imageFailed = ref(false);

watch(
    () => props.product.image_url,
    () => {
        imageFailed.value = false;
    },
);

const onImageError = (): void => {
    imageFailed.value = true;
};

const hasImage = computed(() => Boolean(props.product.image_url) && !imageFailed.value);

async function handleDownload() {
    if (props.product?.id && props.product?.ean) {
        await downloadAndUpdateImage(props.product.id, props.product.ean);
    }
}
</script>

<template>
    <div class="flex items-start gap-3">
        <div class="group relative">
            <img
                v-if="product.image_url"
                :src="product.image_url"
                :alt="product.name"
                class="h-20 w-20 rounded border object-contain"
            />
            <div
                v-else
                class="flex h-20 w-20 items-center justify-center rounded border bg-muted"
            >
                <span class="text-xs text-muted-foreground">Sem imagem</span>
            </div>
        </div>
        <div class="min-w-0 flex-1">
            <p class="flex-wrap font-medium">{{ product.name }}</p>
            <Badge variant="outline" class="mt-1">{{
                product.ean || 'Sem EAN'
            }}</Badge>
            <p
                class="text-xs text-muted-foreground"
                v-if="product.category_full_path"
            >
                {{ product.category_full_path }}
            </p>
            <p class="text-xs text-muted-foreground" v-else>
                {{ product.code }}
            </p>

            <!-- Botões de ação da imagem -->
            <div class="mt-3 flex w-full justify-end gap-1">
                <Button
                    type="button"
                    variant="secondary"
                    size="icon"
                    :disabled="isDownloading || !product.ean"
                    class="h-6 w-6"
                    @click="handleDownload"
                    :title="
                        product.ean
                            ? 'Baixar e atualizar imagem do servidor'
                            : 'Produto sem EAN'
                    "
                >
                    <Loader2
                        v-if="isDownloading"
                        class="h-3 w-3 animate-spin"
                    />
                    <Download v-else class="h-3 w-3" />
                </Button>

                <Button
                    type="button"
                    v-if="showUploadButton"
                    variant="secondary"
                    size="icon"
                    class="h-6 w-6"
                    @click="$emit('upload')"
                    title="Upload manual de imagem"
                >
                    <Upload class="h-3 w-3" />
                </Button>

                <Button
                    type="button"
                    v-if="showUploadButton && product.image_url"
                    variant="destructive"
                    size="icon"
                    class="h-6 w-6"
                    @click="$emit('delete')"
                    title="Remover imagem"
                >
                    <Trash2 class="h-3 w-3" />
                </Button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useProductImage } from '@/composables/plannerate/v3/useProductImage';
import type { Product } from '@/types/planogram';
import { Download, Loader2, Trash2, Upload } from 'lucide-vue-next';

interface Props {
    product: Product;
    showUploadButton?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showUploadButton: false,
});

defineEmits<{
    upload: [];
    delete: [];
}>();

const { isDownloading, downloadAndUpdateImage } = useProductImage(); 
async function handleDownload() {
    if (props.product?.id && props.product?.ean) { 
        await downloadAndUpdateImage(props.product.id, props.product.ean);
    }
}
</script>

<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import { useProductImageStore } from '@/composables/useProductImageStore';

type ProductImageProcessedPayload = {
    product_id?: string;
    ean?: string;
    image_path?: string | null;
    image_url?: string | null;
};

const page = usePage();
const isEchoConfigured = typeof window !== 'undefined' && window.__plannerateEchoConfigured === true;
const { setImage } = useProductImageStore();

const tenantId = computed(() => {
    const tenant = (page.props.tenant ?? null) as { id?: string } | null;

    return typeof tenant?.id === 'string' && tenant.id !== '' ? tenant.id : null;
});

if (isEchoConfigured && tenantId.value) {
    useEcho(`tenant.${tenantId.value}`, '.product.image.processed', (raw: ProductImageProcessedPayload) => {
        const ean = raw.ean ?? '';
        const productId = raw.product_id ?? '';
        const imageUrl = raw.image_url ?? null;

        if (imageUrl) {
            setImage(ean, productId, imageUrl);
            toast.success(`Imagem atualizada: EAN ${ean}`);
        } else {
            toast.warning(`Imagem não encontrada no repositório: EAN ${ean}`);
        }
    });
}
</script>

<template>
    <span class="hidden" aria-hidden="true" />
</template>

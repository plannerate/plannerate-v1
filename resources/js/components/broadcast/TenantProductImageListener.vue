<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';

type ProductImageProcessedPayload = {
    product_id?: string;
    ean?: string;
    image_path?: string | null;
    image_url?: string | null;
};

const page = usePage();
const isEchoConfigured = typeof window !== 'undefined' && window.__plannerateEchoConfigured === true;

const tenantId = computed(() => {
    const tenant = (page.props.tenant ?? null) as { id?: string } | null;

    return typeof tenant?.id === 'string' && tenant.id !== '' ? tenant.id : null;
});

if (isEchoConfigured && tenantId.value) {
    useEcho(`tenant.${tenantId.value}`, '.product.image.processed', (raw: ProductImageProcessedPayload) => {
        const ean = raw.ean ?? '';
        const hasImage = raw.image_url !== null && raw.image_url !== undefined;

        if (hasImage) {
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

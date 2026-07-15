<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { RefreshCw } from 'lucide-vue-next';
import { ref } from 'vue';
import ProductController from '@/actions/App/Http/Controllers/Tenant/ProductController';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';

/**
 * Botão reutilizável que dispara a busca sob demanda dos dados de UM produto
 * (produto + vendas) na API configurada do tenant, grava (upsert) e recarrega a
 * página via Inertia. O feedback vem do flash `toast` do backend.
 *
 * Pode ser usado em qualquer lugar que tenha o produto em mãos (página de vendas,
 * listagem, detalhe), bastando passar `:product`.
 */
interface SyncableProduct {
    id: string;
    name?: string | null;
    ean?: string | null;
    codigo_erp?: string | null;
}

const props = withDefaults(
    defineProps<{
        product: SyncableProduct;
        label?: string;
        variant?: 'default' | 'gradient' | 'outline' | 'secondary' | 'ghost' | 'link';
        size?: 'default' | 'sm' | 'lg';
    }>(),
    {
        label: undefined,
        variant: 'outline',
        size: 'sm',
    },
);

const emit = defineEmits<{ synced: [] }>();

const { t } = useT();

const isSyncing = ref(false);

function sync(): void {
    if (isSyncing.value) {
        return;
    }

    isSyncing.value = true;

    router.post(
        ProductController.syncSingle.url(),
        { product: props.product.id },
        {
            preserveScroll: true,
            onSuccess: () => emit('synced'),
            onFinish: () => {
                isSyncing.value = false;
            },
        },
    );
}
</script>

<template>
    <Button
        type="button"
        :variant="variant"
        :size="size"
        :disabled="isSyncing"
        @click="sync"
    >
        <RefreshCw class="size-3.5 shrink-0" :class="{ 'animate-spin': isSyncing }" />
        {{ isSyncing ? t('app.tenant.products.sync.loading') : (label ?? t('app.tenant.products.sync.button')) }}
    </Button>
</template>

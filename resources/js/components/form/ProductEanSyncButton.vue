<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ProductController from '@/actions/App/Http/Controllers/Tenant/ProductController';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';

type ErrorPayload = {
    message: string;
    ean: string;
    storeIds: string[];
    errors?: Record<string, string>;
};

const props = withDefaults(
    defineProps<{
        subdomain: string;
        ean: string | null | undefined;
        storeIds?: string[];
        label?: string;
        loadingLabel?: string;
        disabled?: boolean;
    }>(),
    {
        storeIds: () => [],
        label: 'Buscar na API',
        loadingLabel: 'Buscando...',
        disabled: false,
    },
);

const emit = defineEmits<{
    (event: 'sync-error', payload: ErrorPayload): void;
    (event: 'sync-finish'): void;
}>();

const syncing = ref(false);

const normalizedEan = computed(() => (props.ean ?? '').trim());
const isDisabled = computed(
    () => props.disabled || syncing.value || normalizedEan.value === '',
);

function sync(): void {
    if (isDisabled.value) {
        return;
    }

    syncing.value = true;
    const safeStoreIds = props.storeIds.filter((id) => id.trim() !== '');

    router.post(
        tenantWayfinderPath(ProductController.syncSingle.url(props.subdomain)),
        {
            produto: normalizedEan.value,
            store_ids: safeStoreIds,
        },
        {
            preserveScroll: true,
            preserveState: false,
            onError: (errors) => {
                const message =
                    Object.values(errors)[0] ??
                    'Falha ao sincronizar produto pela API.';

                emit('sync-error', {
                    message,
                    ean: normalizedEan.value,
                    storeIds: safeStoreIds,
                    errors,
                });
            },
            onSuccess: () => {},
            onFinish: () => {
                syncing.value = false;
                emit('sync-finish');
            },
        },
    );
}
</script>

<template>
    <button
        type="button"
        class="h-9 w-full rounded-lg bg-primary px-3 text-sm font-medium text-primary-foreground transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="isDisabled"
        @click="sync"
    >
        {{ syncing ? loadingLabel : label }}
    </button>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { toast } from 'vue-sonner';
import { useT } from '@/composables/useT';

type IntegrationProcessFinishedPayload = {
    tenant_id: string;
    integration_id: string;
    resource: string;
    reference_date: string;
    status: 'success' | 'failed';
    error_message?: string | null;
};

const page = usePage();
const { t } = useT();

const tenantId = computed(() => {
    const tenant = (page.props.tenant ?? null) as { id?: string } | null;

    return typeof tenant?.id === 'string' && tenant.id !== '' ? tenant.id : null;
});

if (typeof window !== 'undefined' && tenantId.value) {
    useEcho(`tenant.${tenantId.value}`, '.integration.process.finished', (raw: IntegrationProcessFinishedPayload) => {
        const detail = t('app.landlord.tenant_integrations.messages.process_finished_detail', {
            resource: raw.resource,
            date: raw.reference_date,
            status: raw.status,
        });

        if (raw.status === 'failed') {
            toast.error(raw.error_message || detail);

            return;
        }

        toast.success(detail);
    });
}
</script>

<template>
    <span class="hidden" aria-hidden="true" />
</template>

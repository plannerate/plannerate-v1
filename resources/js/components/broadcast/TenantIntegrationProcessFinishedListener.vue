<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import { useT } from '@/composables/useT';

type IntegrationProcessFinishedPayload = {
    tenant_id?: string;
    tenantId?: string;
    integration_id?: string;
    integrationId?: string;
    resource?: string;
    reference_date?: string;
    referenceDate?: string;
    status?: 'success' | 'failed';
    error_message?: string | null;
    errorMessage?: string | null;
};

const page = usePage();
const { t } = useT();

const tenantId = computed(() => {
    const tenant = (page.props.tenant ?? null) as { id?: string } | null;

    return typeof tenant?.id === 'string' && tenant.id !== '' ? tenant.id : null;
});

if (typeof window !== 'undefined' && tenantId.value) {
    useEcho(`tenant.${tenantId.value}`, '.integration.process.finished', (raw: IntegrationProcessFinishedPayload) => {
        const resource = raw.resource ?? 'integration';
        const referenceDate = raw.reference_date ?? raw.referenceDate ?? 'N/A';
        const status = raw.status ?? 'success';
        const errorMessage = raw.error_message ?? raw.errorMessage ?? null;

        const detail = t('app.landlord.tenant_integrations.messages.process_finished_detail', {
            resource,
            date: referenceDate,
            status,
        });

        if (status === 'failed') {
            toast.error(errorMessage || detail);

            return;
        }

        toast.success(detail);
    });
}
</script>

<template>
    <span class="hidden" aria-hidden="true" />
</template>

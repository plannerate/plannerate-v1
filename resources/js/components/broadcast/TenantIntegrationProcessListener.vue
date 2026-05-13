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

type IntegrationProcessStartedPayload = {
    tenant_id?: string;
    tenantId?: string;
    integration_id?: string;
    integrationId?: string;
    resource?: string;
    reference_date?: string;
    referenceDate?: string;
};

type TenantIsolationCheckedPayload = {
    tenant_id?: string;
    tenantId?: string;
    current_tenant_id?: string;
    currentTenantId?: string;
    tenant_slug?: string;
    tenantSlug?: string;
    resource?: string;
    tested_at?: string;
    testedAt?: string;
    status?: 'ok' | 'mismatch';
};

const page = usePage();
const { t } = useT();
const isEchoConfigured = typeof window !== 'undefined' && window.__plannerateEchoConfigured === true;

const tenantId = computed(() => {
    const tenant = (page.props.tenant ?? null) as { id?: string } | null;

    return typeof tenant?.id === 'string' && tenant.id !== '' ? tenant.id : null;
});

if (isEchoConfigured && tenantId.value) {
    useEcho(`tenant.${tenantId.value}`, '.integration.process.started', (raw: IntegrationProcessStartedPayload) => {
        const resource = raw.resource ?? 'integration';
        const referenceDate = raw.reference_date ?? raw.referenceDate ?? 'N/A';

        toast.info(t('app.landlord.tenant_integrations.messages.process_started_detail', {
            resource,
            date: referenceDate,
        }));
    });

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

    useEcho(`tenant.${tenantId.value}`, '.tenant.isolation.checked', (raw: TenantIsolationCheckedPayload) => {
        const status = raw.status ?? 'mismatch';
        const currentTenantId = raw.current_tenant_id ?? raw.currentTenantId ?? 'N/A';
        const resource = raw.resource ?? 'isolation_test';

        if (status === 'ok') {
            toast.success(`Tenant isolation OK [${resource}] (tenant atual: ${currentTenantId})`);

            return;
        }

        toast.error(`Tenant isolation mismatch [${resource}] (tenant atual: ${currentTenantId})`);
    });
}
</script>

<template>
    <span class="hidden" aria-hidden="true" />
</template>

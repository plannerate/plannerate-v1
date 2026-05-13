<script setup lang="ts">
import { useEcho } from '@laravel/echo-vue';
import { toast } from 'vue-sonner';

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

const isEchoConfigured = typeof window !== 'undefined' && window.__plannerateEchoConfigured === true;

if (isEchoConfigured) {
    useEcho('landlord.diagnostics', '.tenant.isolation.checked', (raw: TenantIsolationCheckedPayload) => {
        const status = raw.status ?? 'mismatch';
        const tenantId = raw.tenant_id ?? raw.tenantId ?? 'N/A';
        const tenantSlug = raw.tenant_slug ?? raw.tenantSlug ?? 'N/A';
        const currentTenantId = raw.current_tenant_id ?? raw.currentTenantId ?? 'N/A';
        const resource = raw.resource ?? 'isolation_test';

        if (status === 'ok') {
            toast.success(`Tenant isolation OK [${resource}] (${tenantSlug} | ${tenantId})`);

            return;
        }

        toast.error(`Tenant isolation mismatch [${resource}] (${tenantSlug} | esperado: ${tenantId} | atual: ${currentTenantId})`);
    });
}
</script>

<template>
    <span class="hidden" aria-hidden="true" />
</template>

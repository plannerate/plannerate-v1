<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import TenantCategoriesImportListener from '@/components/broadcast/TenantCategoriesImportListener.vue';
import TenantIntegrationProcessFinishedListener from '@/components/broadcast/TenantIntegrationProcessFinishedListener.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem, LayoutPageHeader } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
    pageHeader?: LayoutPageHeader;
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    pageHeader: () => ({}),
});

const page = usePage();

const hasEchoPrivateChannelUser = computed(() => {
    const id = (page.props.auth as { user?: { id: string } } | undefined)?.user?.id;

    return typeof id === 'string' && id !== '';
});

const hasEchoPrivateTenantChannel = computed(() => {
    const tenant = (page.props.tenant ?? null) as { id?: string } | null;

    return typeof tenant?.id === 'string' && tenant.id !== '';
});
</script>

<template>
    <AppShell variant="sidebar">
        <TenantCategoriesImportListener v-if="hasEchoPrivateChannelUser" />
        <TenantIntegrationProcessFinishedListener v-if="hasEchoPrivateTenantChannel" />
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" :page-header="pageHeader">
                <template #actions>
                    <slot name="header-actions" />
                </template>
            </AppSidebarHeader>
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>

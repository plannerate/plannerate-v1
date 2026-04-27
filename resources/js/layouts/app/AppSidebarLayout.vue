<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import TenantCategoriesImportListener from '@/components/broadcast/TenantCategoriesImportListener.vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
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
</script>

<template>
    <AppShell variant="sidebar">
        <TenantCategoriesImportListener v-if="hasEchoPrivateChannelUser" />
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

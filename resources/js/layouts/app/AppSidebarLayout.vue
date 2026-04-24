<script setup lang="ts">
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
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" :page-header="pageHeader">
                <slot name="header-actions">
                    <slot name="header-action" />
                </slot>
            </AppSidebarHeader>
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>

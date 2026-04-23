<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
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
    <AppShell variant="header">
        <AppHeader :breadcrumbs="breadcrumbs" :page-header="pageHeader" />
        <AppContent variant="header">
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>

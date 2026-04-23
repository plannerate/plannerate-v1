<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem, LayoutPageHeader } from '@/types';

const { breadcrumbs = [], pageHeader = {} } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
    pageHeader?: LayoutPageHeader;
}>();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :page-header="pageHeader">
        <slot />
    </AppLayout>
</template>

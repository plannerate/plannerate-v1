<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem, LayoutPageHeader } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
        pageHeader?: LayoutPageHeader;
    }>(),
    {
        breadcrumbs: () => [],
        pageHeader: () => ({}),
    },
);
</script>

<template>
    <header
        class="shrink-0 border-b border-sidebar-border/70 px-6 py-3 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:py-2 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div v-if="pageHeader.title || pageHeader.description" class="mt-2 pl-8">
            <p v-if="pageHeader.title" class="text-base font-semibold leading-6 text-foreground">
                {{ pageHeader.title }}
            </p>
            <p v-if="pageHeader.description" class="text-sm text-muted-foreground">
                {{ pageHeader.description }}
            </p>
        </div>
    </header>
</template>

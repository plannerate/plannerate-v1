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
        class="shrink-0 border-b bg-muted/15 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:py-2 mb-6"
    >
        <div class="flex h-12 items-center gap-2 border-b border-border/60 px-4">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div
            v-if="pageHeader.title || pageHeader.description"
            class=" px-4 py-3"
        >
            <p
                v-if="pageHeader.title"
                class="text-2xl leading-tight font-semibold tracking-tight text-foreground"
            >
                {{ pageHeader.title }}
            </p>
            <p
                v-if="pageHeader.description"
                class="mt-1 text-sm text-muted-foreground mb-2"
            >
                {{ pageHeader.description }}
            </p>
        </div>
    </header>
</template>

<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem, LayoutPageHeader } from '@/types';

const props = withDefaults(
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
        class="w-full border-b  bg-muted/15  ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:py-2 mb-6">
        <div class="flex h-12 w-full items-center gap-2 border-b border-border/60 px-4">
            <SidebarTrigger class="-ml-1" />
            <template v-if="props.breadcrumbs && props.breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="props.breadcrumbs" />
            </template>
        </div>

        <div class="flex w-full items-center px-4 py-3">
            <div>
                <h1 v-if="props.pageHeader.title"
                    class="truncate text-lg font-semibold leading-tight tracking-tight text-foreground md:text-xl">
                    {{ props.pageHeader.title }}
                </h1>
                <p v-if="props.pageHeader.description" class="mt-0.5 text-sm leading-snug text-muted-foreground">
                    {{ props.pageHeader.description }}
                </p>
            </div> 
            <div class="ml-auto flex  items-center justify-end gap-2">
                <slot name="actions" />
            </div>
        </div>
    </header>
</template>

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
        class="shrink-0 border-b border-sidebar-border/70 bg-muted/15 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:py-2"
    >
        <div class="flex h-12 items-center gap-2 border-b border-border/60 px-4">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div
            v-if="pageHeader.title || pageHeader.description || $slots.default"
            class="flex items-center justify-between gap-4 px-6 py-3 md:px-4"
        >
            <div class="flex items-start gap-3">
                <div
                    v-if="pageHeader.title || pageHeader.description"
                    class="mt-0.5 h-6 w-0.5 shrink-0 rounded-full bg-primary/60"
                />
                <div>
                    <h1
                        v-if="pageHeader.title"
                        class="text-xl font-semibold leading-tight tracking-tight text-foreground"
                    >
                        {{ pageHeader.title }}
                    </h1>
                    <p
                        v-if="pageHeader.description"
                        class="mt-0.5 text-sm leading-snug text-muted-foreground"
                    >
                        {{ pageHeader.description }}
                    </p>
                </div>
            </div>

            <div v-if="$slots.default" class="flex shrink-0 items-center gap-2">
                <slot />
            </div>
        </div>
    </header>
</template>

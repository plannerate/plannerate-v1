<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
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
        class="shrink-0 border-b border-sidebar-border/70 bg-muted/15 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:py-2"
    >
        <div class="flex h-12 items-center gap-2 border-b border-border/60 px-4">
            <SidebarTrigger class="-ml-1" />
            <template v-if="props.breadcrumbs && props.breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="props.breadcrumbs" />
            </template>
        </div>

        <div
            v-if="
                props.pageHeader.title ||
                props.pageHeader.description ||
                props.pageHeader.createRoute ||
                (props.pageHeader.actions && props.pageHeader.actions.length > 0) ||
                $slots.actions ||
                $slots.default
            "
            class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex min-w-0 items-start gap-3">
                <div
                    v-if="props.pageHeader.title || props.pageHeader.description"
                    class="mt-0.5 h-9 w-1 shrink-0 rounded-full bg-primary/60"
                />
                <div class="min-w-0">
                    <h1
                        v-if="props.pageHeader.title"
                        class="truncate text-lg font-semibold leading-tight tracking-tight text-foreground md:text-xl"
                    >
                        {{ props.pageHeader.title }}
                    </h1>
                    <p
                        v-if="props.pageHeader.description"
                        class="mt-0.5 text-sm leading-snug text-muted-foreground"
                    >
                        {{ props.pageHeader.description }}
                    </p>
                </div>
            </div>

            <div
                v-if="
                    props.pageHeader.createRoute ||
                    (props.pageHeader.actions && props.pageHeader.actions.length > 0) ||
                    $slots.actions ||
                    $slots.default
                "
                class="flex w-full shrink-0 items-center justify-start sm:w-auto sm:justify-end"
            >
                <div
                    class="inline-flex flex-wrap items-center gap-2 rounded-lg border border-border/70 bg-background/90 p-1 shadow-xs [&>*]:inline-flex [&>*]:shrink-0 [&>*]:items-center"
                >
                    <template
                        v-for="action in props.pageHeader.actions ?? []"
                        :key="`${action.label}-${action.href}`"
                    >
                        <Button
                            :variant="action.variant ?? 'outline'"
                            size="sm"
                            as-child
                        >
                            <Link :href="action.href">
                                {{ action.label }}
                            </Link>
                        </Button>
                    </template>
                    <Button
                        v-if="props.pageHeader.createRoute"
                        variant="gradient"
                        size="pill-sm"
                        as-child
                    >
                        <Link :href="props.pageHeader.createRoute">
                            {{ props.pageHeader.createLabel ?? 'Novo' }}
                        </Link>
                    </Button>
                    <slot name="actions">
                        <slot />
                    </slot>
                </div>
            </div>
        </div>
    </header>
</template>

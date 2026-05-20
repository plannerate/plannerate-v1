<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import type { Paginator } from '@/types';

const props = defineProps<{
    meta: Omit<Paginator<unknown>, 'data'>;
    label?: string;
}>();

const hasMultiplePages = computed(() => props.meta.last_page > 1);
const shouldSticky = computed(() => hasMultiplePages.value);
const paginationEl = ref<HTMLElement | null>(null);
const fixedLeft = ref<number | null>(null);
const fixedWidth = ref<number | null>(null);

let resizeObserver: ResizeObserver | null = null;
let mutationObserver: MutationObserver | null = null;
let animationFrameId: number | null = null;
let syncTimeoutId: ReturnType<typeof setTimeout> | null = null;
let handleWindowResizeSync: (() => void) | null = null;

const fixedStyles = computed(() => {
    if (!shouldSticky.value || fixedLeft.value === null || fixedWidth.value === null) {
        return {};
    }

    return {
        left: `${fixedLeft.value}px`,
        width: `${fixedWidth.value}px`,
    };
});

const prevLink = computed(() => props.meta.links[0] ?? null);
const nextLink = computed(() => props.meta.links[props.meta.links.length - 1] ?? null);
const pageLinks = computed(() => props.meta.links.slice(1, -1));

const countText = computed(() => {
    if (!props.meta.from || !props.meta.to) {
        return 'Nenhum resultado';
    }

    const suffix = props.label ? ` ${props.label}` : '';

    return `Mostrando ${props.meta.from}–${props.meta.to} de ${props.meta.total}${suffix}`;
});

function updateFixedPosition(): void {
    if (!shouldSticky.value || !paginationEl.value) {
        return;
    }

    const container = paginationEl.value.closest('.list-page-container') as HTMLElement | null;
    const rect = container?.getBoundingClientRect();

    if (!rect) {
        return;
    }

    fixedLeft.value = rect.left;
    fixedWidth.value = rect.width;
}

function startSidebarSync(duration = 350): void {
    if (!shouldSticky.value) {
        return;
    }

    if (syncTimeoutId) {
        clearTimeout(syncTimeoutId);
    }

    const tick = () => {
        updateFixedPosition();
        animationFrameId = window.requestAnimationFrame(tick);
    };

    if (animationFrameId === null) {
        animationFrameId = window.requestAnimationFrame(tick);
    }

    syncTimeoutId = setTimeout(() => {
        if (animationFrameId !== null) {
            window.cancelAnimationFrame(animationFrameId);
            animationFrameId = null;
        }
    }, duration);
}

onMounted(() => {
    updateFixedPosition();

    window.addEventListener('resize', updateFixedPosition, { passive: true });
    window.addEventListener('scroll', updateFixedPosition, { passive: true });
    handleWindowResizeSync = () => startSidebarSync(450);
    window.addEventListener('resize', handleWindowResizeSync, { passive: true });

    const container = paginationEl.value?.closest('.list-page-container') as HTMLElement | null;

    if (container) {
        resizeObserver = new ResizeObserver(() => {
            updateFixedPosition();
            startSidebarSync();
        });
        resizeObserver.observe(container);
    }

    const sidebarWrapper = document.querySelector('[data-slot="sidebar-wrapper"]');

    if (sidebarWrapper) {
        mutationObserver = new MutationObserver(() => {
            updateFixedPosition();
            startSidebarSync();
        });
        mutationObserver.observe(sidebarWrapper, {
            attributes: true,
            childList: true,
            subtree: true,
            attributeFilter: ['class', 'style', 'data-state', 'data-collapsible'],
        });
    }
});

onUnmounted(() => {
    window.removeEventListener('resize', updateFixedPosition);
    window.removeEventListener('scroll', updateFixedPosition);

    if (handleWindowResizeSync) {
        window.removeEventListener('resize', handleWindowResizeSync);
        handleWindowResizeSync = null;
    }

    resizeObserver?.disconnect();
    mutationObserver?.disconnect();

    if (animationFrameId !== null) {
        window.cancelAnimationFrame(animationFrameId);
        animationFrameId = null;
    }

    if (syncTimeoutId) {
        clearTimeout(syncTimeoutId);
        syncTimeoutId = null;
    }
});
</script>

<template>
    <template v-if="meta.total > 0">
        <div
            ref="paginationEl"
            :class="[
                'mt-4 flex flex-wrap items-center justify-center gap-3 rounded-xl border border-border bg-background/95 px-3 py-2 shadow-lg backdrop-blur supports-backdrop-filter:bg-background/75',
                shouldSticky
                    ? 'fixed bottom-1 z-40'
                    : 'border-t border-border pt-4',
            ]"
            :style="fixedStyles"
        >
            <!-- Count -->
            <p class="text-sm text-muted-foreground">
                {{ countText }}
            </p>

            <div v-if="hasMultiplePages" class="flex items-center gap-1">
                <!-- Previous -->
                <Button
                    v-if="prevLink?.url"
                    variant="outline"
                    size="sm"
                    as-child
                    class="size-8 rounded-lg p-0"
                >
                    <Link :href="prevLink.url" preserve-scroll>
                        <ChevronLeft class="size-4" />
                    </Link>
                </Button>
                <Button v-else variant="outline" size="sm" disabled class="size-8 rounded-lg p-0">
                    <ChevronLeft class="size-4" />
                </Button>

                <!-- Page numbers -->
                <template v-for="page in pageLinks" :key="page.label">
                    <!-- Ellipsis -->
                    <span v-if="page.url === null && page.label === '...'" class="px-1 text-sm text-muted-foreground">
                        …
                    </span>

                    <!-- Active page -->
                    <Button v-else-if="page.active" variant="secondary" size="sm" class="size-8 rounded-lg p-0 font-semibold">
                        <span v-html="page.label" />
                    </Button>

                    <!-- Page link -->
                    <Button v-else-if="page.url" variant="ghost" size="sm" as-child class="size-8 rounded-lg p-0">
                        <Link :href="page.url" preserve-scroll>
                            <span v-html="page.label" />
                        </Link>
                    </Button>
                </template>

                <!-- Next -->
                <Button
                    v-if="nextLink?.url"
                    variant="outline"
                    size="sm"
                    as-child
                    class="size-8 rounded-lg p-0"
                >
                    <Link :href="nextLink.url" preserve-scroll>
                        <ChevronRight class="size-4" />
                    </Link>
                </Button>
                <Button v-else variant="outline" size="sm" disabled class="size-8 rounded-lg p-0">
                    <ChevronRight class="size-4" />
                </Button>
            </div>
        </div>

        <div v-if="shouldSticky" class="h-20" aria-hidden="true" />
    </template>
</template>

<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { Paginator } from '@/types';

const props = defineProps<{
    meta: Omit<Paginator<unknown>, 'data'>;
    label?: string;
}>();

const hasMultiplePages = computed(() => props.meta.last_page > 1);

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
</script>

<template>
    <div
        v-if="meta.total > 0"
        class="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4"
    >
        <!-- Count -->
        <p class="text-sm text-muted-foreground">
            {{ countText }}
        </p>

        <!-- Navigation -->
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
</template>

<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, ChevronsUpDown } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    field: string;
}>();

const page = usePage();

const params = computed(() => {
    const qs = page.url.includes('?') ? page.url.split('?')[1] : '';

    return new URLSearchParams(qs);
});

const isActive = computed(() => params.value.get('sort') === props.field);
const currentDirection = computed(() => params.value.get('direction') as 'asc' | 'desc' | null);
const nextDirection = computed(() => (isActive.value && currentDirection.value === 'asc') ? 'desc' : 'asc');

function sort() {
    const newParams = Object.fromEntries(params.value.entries());
    newParams.sort = props.field;
    newParams.direction = nextDirection.value;
    router.get(window.location.pathname, newParams, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <th class="px-4 py-3 font-medium">
        <button
            type="button"
            class="inline-flex items-center gap-1 transition-colors hover:text-foreground"
            :class="isActive ? 'text-foreground' : ''"
            @click="sort"
        >
            <slot />
            <ChevronUp v-if="isActive && currentDirection === 'asc'" class="size-3.5" />
            <ChevronDown v-else-if="isActive && currentDirection === 'desc'" class="size-3.5" />
            <ChevronsUpDown v-else class="size-3.5 opacity-40" />
        </button>
    </th>
</template>

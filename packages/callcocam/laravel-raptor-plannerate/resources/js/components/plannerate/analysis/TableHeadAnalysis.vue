<template>
    <TableHead class="cursor-pointer hover:bg-gray-200">
        <button 
            type="button" 
            class="w-full flex items-center justify-between text-gray-700 dark:text-gray-300 font-semibold" 
            @click.stop="handleClick"
        >
            <span :class="{ 'truncate max-w-20': currentSortKey !== 'name' }">{{ label }}</span>
            <span class="ml-1 text-gray-600 dark:text-gray-400">
                <ArrowUpDown v-if="String(sortConfig.key) !== String(currentSortKey)" class="h-4 w-4" />
                <ArrowUp v-else-if="sortConfig.direction === 'asc'" class="h-4 w-4" />
                <ArrowDown v-else class="h-4 w-4" />
            </span>
        </button>
    </TableHead>
</template>

<script setup lang="ts">
import { TableHead } from '@/components/ui/table';
import { ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    label: string;
    sortKey: string;
    sortConfig: {
        key: string | number | symbol;
        direction: 'asc' | 'desc';
    };
}

interface Emits {
    (e: 'sort', key: string): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const currentSortKey = computed(() => props.sortKey);

const handleClick = (event: MouseEvent) => {
    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();
    emit('sort', props.sortKey);
};
</script>

<style scoped>
:deep(th) {
    color: rgb(55 65 81) !important;
}

.dark :deep(th) {
    color: rgb(209 213 219) !important;
}
</style>
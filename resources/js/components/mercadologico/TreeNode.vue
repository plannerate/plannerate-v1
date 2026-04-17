<script setup lang="ts">
import { ChevronRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import TreeNode from './TreeNode.vue';
import type { CategoryNode } from '@/types/mercadologico';

export type { CategoryNode };

const props = withDefaults(
    defineProps<{
        node: CategoryNode;
        depth?: number;
        moveUrl: string;
        isRootCard?: boolean;
    }>(),
    { depth: 0, isRootCard: false },
);

const emit = defineEmits<{
    moved: [categoryId: string, newParentId: string | null];
}>();

const expanded = ref(true);
const isDragging = ref(false);
const isDropTarget = ref(false);

const hasChildren = computed(() => props.node.children?.length > 0);

const toggle = () => {
    expanded.value = !expanded.value;
};

let draggedNodeId: string | null = null;

function handleDragStart(e: DragEvent) {
    if (!e.dataTransfer) return;
    draggedNodeId = props.node.id;
    isDragging.value = true;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('application/json', JSON.stringify({ id: props.node.id }));
    e.dataTransfer.setData('text/plain', props.node.id);
}

function handleDragEnd() {
    isDragging.value = false;
    isDropTarget.value = false;
    draggedNodeId = null;
}

function handleDragOver(e: DragEvent) {
    e.preventDefault();
    if (!e.dataTransfer) return;
    if (draggedNodeId === props.node.id) {
        e.dataTransfer.dropEffect = 'none';
        return;
    }
    e.dataTransfer.dropEffect = 'move';
    isDropTarget.value = true;
}

function handleDragLeave() {
    isDropTarget.value = false;
}

function handleDrop(e: DragEvent) {
    e.preventDefault();
    isDropTarget.value = false;
    const raw = e.dataTransfer?.getData('application/json');
    if (!raw) return;
    try {
        const { id } = JSON.parse(raw) as { id: string };
        if (id === props.node.id) return;
        emit('moved', id, props.node.id);
    } catch {
        // ignore
    }
}
</script>

<template>
    <div class="select-none">
        <!-- Linha do nó -->
        <div
            class="group flex items-center gap-2 rounded-lg border border-transparent px-3 py-2 transition-colors"
            :class="[
                depth === 0 && isRootCard && 'rounded-t-xl rounded-b-none border-0 px-4 py-3',
                depth > 0 && 'mx-2 mb-0.5 rounded-md border-l-0',
                depth === 1 && 'bg-muted/40 dark:bg-muted/20',
                depth === 2 && 'bg-muted/25 dark:bg-muted/10',
                depth >= 3 && 'bg-muted/10 dark:bg-muted/5',
                isDragging && 'opacity-50',
                isDropTarget && 'border-primary bg-primary/10 ring-1 ring-primary/30',
            ]"
            :style="depth > 0 ? { paddingLeft: `${12 + (depth - 1) * 20}px` } : undefined"
            draggable="true"
            @dragstart="handleDragStart"
            @dragend="handleDragEnd"
            @dragover="handleDragOver"
            @dragleave="handleDragLeave"
            @drop="handleDrop"
        >
            <button
                type="button"
                class="flex shrink-0 items-center justify-center rounded p-1 hover:bg-muted/80"
                :class="{ 'rotate-90': expanded }"
                :aria-label="expanded ? 'Recolher' : 'Expandir'"
                @click.stop="toggle"
            >
                <ChevronRight
                    v-if="hasChildren"
                    class="size-4 text-muted-foreground"
                />
                <span v-else class="inline-block size-4" />
            </button>
            <span
                class="min-w-0 flex-1 truncate font-medium text-foreground"
                :class="depth === 0 ? 'text-base' : 'text-sm'"
            >
                {{ node.name }}
            </span>
            <span
                v-if="node.slug"
                class="shrink-0 max-w-[8rem] truncate text-xs text-muted-foreground"
            >
                {{ node.slug }}
            </span>
        </div>
        <!-- Subárvore com linha vertical (tree line) -->
        <div
            v-show="expanded && hasChildren"
            class="relative border-l-2 border-muted-foreground/20 pl-2 dark:border-muted-foreground/30"
            :class="isRootCard ? 'ml-4 mr-3 mb-3 mt-0' : 'ml-2'"
        >
            <TreeNode
                v-for="child in node.children"
                :key="child.id"
                :node="child"
                :depth="depth + 1"
                :move-url="moveUrl"
                :is-root-card="false"
                @moved="emit('moved', $event[0], $event[1])"
            />
        </div>
    </div>
</template>

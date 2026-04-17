<script setup lang="ts">
import { ChevronRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { colorWithAlpha } from '@/composables/useMercadologicoTree';
import type { CategoryNode } from '@/types/mercadologico';
import { MERCADOLOGICO_PRODUCTS_DRAG_TYPE } from '@/constants/mercadologicoProductsDrag';
import MercadologicoPanelTreeItem from './MercadologicoPanelTreeItem.vue';

const props = defineProps<{
    node: CategoryNode;
    expandIds: string[];
    selectedId: string | null;
    nodeColorFn: (node: CategoryNode, depth: number) => string;
    depth?: number;
}>();

const emit = defineEmits<{
    select: [node: CategoryNode, event?: MouseEvent];
    expandToggle: [nodeId: string, expanded: boolean];
    dropProducts: [targetCategoryId: string, productIds: string[]];
}>();

const depth = computed(() => props.depth ?? (props.node.depth ?? 1));
const hasChildren = computed(() => (props.node.children?.length ?? 0) > 0);
const isExpanded = computed(() => props.expandIds.includes(props.node.id));
const isSelected = computed(() => props.selectedId === props.node.id);
const color = computed(() => props.nodeColorFn(props.node, depth.value));
const productsCount = computed(() => props.node.products_count ?? 0);
const isDropTarget = ref(false);
const rowStyle = computed(() => ({
    paddingLeft: `${(depth.value - 1) * 20 + 8}px`,
    backgroundColor: isSelected.value ? colorWithAlpha(color.value, 0.12) : undefined,
}));

function onRowClick(e: MouseEvent) {
    emit('select', props.node, e);
}

function onChevronClick() {
    emit('expandToggle', props.node.id, !isExpanded.value);
}

function onDragOver(e: DragEvent) {
    const data = e.dataTransfer?.types?.includes(MERCADOLOGICO_PRODUCTS_DRAG_TYPE);
    if (data) {
        e.preventDefault();
        e.dataTransfer!.dropEffect = 'move';
        isDropTarget.value = true;
    }
}

function onDragLeave() {
    isDropTarget.value = false;
}

function onDrop(e: DragEvent) {
    isDropTarget.value = false;
    const raw = e.dataTransfer?.getData(MERCADOLOGICO_PRODUCTS_DRAG_TYPE);
    if (!raw) return;
    e.preventDefault();
    try {
        const { productIds } = JSON.parse(raw) as { productIds: string[]; sourceCategoryId?: string };
        if (Array.isArray(productIds) && productIds.length > 0) {
            emit('dropProducts', props.node.id, productIds);
        }
    } catch {
        // ignore invalid payload
    }
}
</script>

<template>
    <div class="tree-item select-none">
        <div
            class="tree-row relative flex h-8 cursor-pointer items-center gap-0 rounded-md pr-3 transition-colors"
            :class="[
                !isSelected && 'hover:bg-muted/60',
                isDropTarget && 'ring-1 ring-primary bg-primary/10',
            ]"
            :style="rowStyle"
            @click="onRowClick"
            @dragover="onDragOver"
            @dragleave="onDragLeave"
            @drop="onDrop"
        >
            <span
                v-if="isSelected"
                class="absolute left-0 top-0 bottom-0 w-0.5"
                :style="{ background: color }"
            />
            <div class="flex h-[18px] w-[18px] shrink-0 items-center justify-center">
                <button
                    v-if="hasChildren"
                    type="button"
                    class="flex size-full items-center justify-center text-muted-foreground transition-transform"
                    :class="{ 'rotate-90': isExpanded }"
                    :aria-label="isExpanded ? 'Recolher' : 'Expandir'"
                    @click.stop="onChevronClick"
                >
                    <ChevronRight class="size-4" />
                </button>
                <span v-else class="inline-block size-4" aria-hidden="true" />
            </div>
            <div
                class="level-dot ml-1 mr-2 h-1.5 w-1.5 shrink-0 rounded-full"
                :style="{ background: color }"
            />
            <span class="tree-label min-w-0 flex-1 truncate text-xs font-medium text-foreground">
                {{ node.name }}
            </span>
            <span
                v-if="productsCount > 0"
                class="shrink-0 rounded border border-border bg-muted/60 px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground"
            >
                {{ productsCount }} {{ productsCount === 1 ? 'produto' : 'produtos' }}
            </span>
        </div>
        <div v-show="isExpanded && hasChildren" class="tree-children">
            <MercadologicoPanelTreeItem
                v-for="child in node.children"
                :key="child.id"
                :node="child"
                :expand-ids="expandIds"
                :selected-id="selectedId"
                :node-color-fn="nodeColorFn"
                :depth="(node.depth ?? depth) + 1"
                @select="(node, ev) => emit('select', node, ev)"
                @expand-toggle="(id, expanded) => emit('expandToggle', id, expanded)"
                @drop-products="(id, ids) => emit('dropProducts', id, ids)"
            />
        </div>
    </div>
</template>

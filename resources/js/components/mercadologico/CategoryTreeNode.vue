<script setup lang="ts">
import { ChevronRight, GripVertical, Package } from 'lucide-vue-next';
import { computed } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/composables/useT';

import type { TreeNode } from './types';
import type { CategoryDragController } from './useCategoryDrag';
import type { CategoryTreeStore, NodeState } from './useCategoryTree';

defineOptions({ name: 'CategoryTreeNode' });

const props = defineProps<{
    state: NodeState;
    store: CategoryTreeStore;
    drag: CategoryDragController;
    onOpenProducts: (node: TreeNode) => void;
    depth?: number;
}>();

const { t } = useT();

const node = computed(() => props.state.node);
const hasChildren = computed(() => node.value.children_count > 0);
const isDragging = computed(() => props.drag.draggingId.value === node.value.id);
const isDropTarget = computed(
    () => props.drag.dragOverTarget.value === node.value.id,
);
const children = computed(() => props.store.childrenStates(node.value.id));
</script>

<template>
    <div>
        <div
            class="group flex items-center gap-1.5 rounded-md px-1.5 py-1 transition-colors select-none"
            :class="{
                'opacity-40': isDragging,
                'bg-primary/10 ring-1 ring-primary/40': isDropTarget,
                'hover:bg-muted/60': !isDropTarget,
            }"
            draggable="true"
            @dragstart="drag.onDragStart(node.id, $event)"
            @dragend="drag.onDragEnd()"
            @dragover.prevent="drag.onDragOver(node.id, $event)"
            @dragleave="drag.onDragLeave(node.id)"
            @drop.prevent="drag.onDrop(node.id)"
        >
            <!-- Expand / collapse -->
            <button
                type="button"
                class="flex size-5 shrink-0 items-center justify-center rounded text-muted-foreground transition hover:bg-muted disabled:opacity-0"
                :disabled="!hasChildren"
                :aria-label="state.expanded ? t('app.landlord.mercadologico.tree.collapse') : t('app.landlord.mercadologico.tree.expand')"
                @click="store.toggle(node.id)"
            >
                <ChevronRight
                    class="size-4 transition-transform"
                    :class="{ 'rotate-90': state.expanded }"
                />
            </button>

            <GripVertical
                class="size-3.5 shrink-0 cursor-grab text-muted-foreground/40 group-hover:text-muted-foreground"
            />

            <span class="min-w-0 flex-1 truncate text-sm font-medium">
                {{ node.name }}
            </span>

            <Badge
                v-if="node.is_placeholder"
                variant="outline"
                class="shrink-0 text-[10px]"
            >
                {{ t('app.landlord.mercadologico.tree.placeholder_badge') }}
            </Badge>

            <Badge
                v-if="node.level_name"
                variant="secondary"
                class="hidden shrink-0 text-[10px] sm:inline-flex"
            >
                {{ node.level_name }}
            </Badge>

            <button
                type="button"
                class="flex shrink-0 items-center gap-1 rounded-md px-2 py-0.5 text-xs text-muted-foreground transition hover:bg-muted hover:text-foreground"
                :title="t('app.landlord.mercadologico.tree.view_products')"
                @click="onOpenProducts(node)"
            >
                <Package class="size-3.5" />
                {{ node.products_count }}
            </button>
        </div>

        <!-- Filhos (lazy) -->
        <div
            v-if="state.expanded"
            class="ml-3 border-l border-border/60 pl-2"
        >
            <div v-if="state.loading" class="space-y-1 py-1">
                <Skeleton class="h-6 w-3/4" />
                <Skeleton class="h-6 w-2/3" />
            </div>

            <p
                v-else-if="children.length === 0"
                class="px-2 py-1 text-xs text-muted-foreground/70"
            >
                {{ t('app.landlord.mercadologico.tree.empty') }}
            </p>

            <CategoryTreeNode
                v-for="child in children"
                :key="child.node.id"
                :state="child"
                :store="store"
                :drag="drag"
                :on-open-products="onOpenProducts"
                :depth="(depth ?? 0) + 1"
            />
        </div>
    </div>
</template>

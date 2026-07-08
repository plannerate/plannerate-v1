<script setup lang="ts">
import { CornerLeftUp } from 'lucide-vue-next';
import { computed } from 'vue';

import { useT } from '@/composables/useT';

import CategoryTreeNode from './CategoryTreeNode.vue';
import type { TreeNode } from './types';
import { ROOT_TARGET } from './useCategoryDrag';
import type { CategoryDragController } from './useCategoryDrag';
import type { CategoryTreeStore, NodeState } from './useCategoryTree';

const props = defineProps<{
    store: CategoryTreeStore;
    drag: CategoryDragController;
    onOpenProducts: (node: TreeNode) => void;
}>();

const { t } = useT();

const rootStates = computed((): NodeState[] =>
    props.store.rootIds.value
        .map((id) => props.store.getNode(id))
        .filter((state): state is NodeState => Boolean(state)),
);

const isDraggingSomething = computed(
    () => props.drag.draggingId.value !== null,
);
const isRootDropTarget = computed(
    () => props.drag.dragOverTarget.value === ROOT_TARGET,
);
</script>

<template>
    <div class="space-y-1">
        <!-- Zona de soltura para mover um nó de volta à raiz -->
        <div
            v-if="isDraggingSomething"
            class="flex items-center gap-2 rounded-md border border-dashed px-3 py-2 text-xs transition-colors"
            :class="
                isRootDropTarget
                    ? 'border-primary/50 bg-primary/10 text-primary'
                    : 'border-border text-muted-foreground'
            "
            @dragover.prevent="drag.onDragOver(ROOT_TARGET)"
            @dragleave="drag.onDragLeave(ROOT_TARGET)"
            @drop.prevent="drag.onDrop(ROOT_TARGET)"
        >
            <CornerLeftUp class="size-4" />
            {{ t('app.landlord.mercadologico.tree.move_to_root') }}
        </div>

        <p
            v-if="rootStates.length === 0"
            class="rounded-md border border-dashed border-border px-3 py-10 text-center text-sm text-muted-foreground"
        >
            {{ t('app.landlord.mercadologico.tree.empty') }}
        </p>

        <CategoryTreeNode
            v-for="state in rootStates"
            :key="state.node.id"
            :state="state"
            :store="store"
            :drag="drag"
            :on-open-products="onOpenProducts"
        />
    </div>
</template>

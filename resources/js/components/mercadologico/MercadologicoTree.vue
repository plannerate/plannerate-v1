<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { CategoryNode } from './TreeNode.vue';
import TreeNode from './TreeNode.vue';

const props = defineProps<{
    categories: CategoryNode[];
    moveUrl: string;
}>();

const rootDropActive = ref(false);

function handleMoved(categoryId: string, newParentId: string | null) {
    router.patch(props.moveUrl, {
        id: categoryId,
        category_id: newParentId,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            router.reload({ only: ['categories'] });
        },
    });
}

function onRootDragOver(e: DragEvent) {
    e.preventDefault();
    if (!e.dataTransfer) return;
    e.dataTransfer.dropEffect = 'move';
    rootDropActive.value = true;
}

function onRootDragLeave() {
    rootDropActive.value = false;
}

function onRootDrop(e: DragEvent) {
    e.preventDefault();
    rootDropActive.value = false;
    const raw = e.dataTransfer?.getData('application/json');
    if (!raw) return;
    try {
        const { id } = JSON.parse(raw) as { id: string };
        handleMoved(id, null);
    } catch {
        // ignore
    }
}
</script>

<template>
    <div class="space-y-4">
        <div
            class="rounded-lg border-2 border-dashed py-4 text-center text-sm text-muted-foreground transition-colors"
            :class="rootDropActive ? 'border-primary bg-primary/5' : 'border-muted-foreground/20 bg-muted/30'"
            @dragover="onRootDragOver"
            @dragleave="onRootDragLeave"
            @drop="onRootDrop"
        >
            Solte aqui para tornar raiz
        </div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="node in categories"
                :key="node.id"
                class="rounded-xl border border-border bg-card shadow-sm dark:bg-card/50"
            >
                <TreeNode
                    :node="node"
                    :move-url="moveUrl"
                    :depth="0"
                    :is-root-card="true"
                    @moved="handleMoved"
                />
            </div>
        </div>
    </div>
</template>

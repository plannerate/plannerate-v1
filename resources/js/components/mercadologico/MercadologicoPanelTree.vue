<script setup lang="ts">
import { computed } from 'vue';
import type { CategoryNode } from '@/composables/useMercadologicoTree';
import { LEVEL_COLORS, getRootId } from '@/composables/useMercadologicoTree';
import MercadologicoPanelTreeItem from './MercadologicoPanelTreeItem.vue';
import {ScrollArea} from '@/components/ui/scroll-area';

const props = withDefaults(
    defineProps<{
        categories: CategoryNode[];
        selectedId?: string | null;
        expandIds?: string[];
        rootColorIndex?: Record<string, number>;
        searchQuery: string;
        hasSelection?: boolean;
    }>(),
    { selectedId: null, expandIds: () => [], rootColorIndex: () => ({}), hasSelection: false },
);

const emit = defineEmits<{
    select: [node: CategoryNode, event?: MouseEvent];
    expandToggle: [nodeId: string, expanded: boolean];
    dropProducts: [targetCategoryId: string, productIds: string[]];
    'update:searchQuery': [value: string];
    clear: [];
}>();

const filteredRoots = computed(() => {
    const q = props.searchQuery.toLowerCase().trim();
    if (!q) return props.categories;
    return filterNodes(props.categories, q);
});

function filterNodes(nodes: CategoryNode[], q: string): CategoryNode[] {
    return nodes
        .map((node) => {
            const match = node.name.toLowerCase().includes(q);
            const children = node.children?.length ? filterNodes(node.children, q) : [];
            if (match || children.length) {
                return { ...node, children };
            }
            return null;
        })
        .filter(Boolean) as CategoryNode[];
}

function levelColor(depth: number): string {
    return LEVEL_COLORS[(depth - 1) % LEVEL_COLORS.length];
}

function nodeColor(node: CategoryNode, depth: number): string {
    const rootId = getRootId(props.categories, node.id);
    if (rootId) {
        const index = props.rootColorIndex?.[rootId];
        if (index !== undefined) {
            return LEVEL_COLORS[index % LEVEL_COLORS.length] ?? levelColor(depth);
        }
    }

    return levelColor(depth);
}
</script>

<template>
    <div class="flex h-full flex-col border-r border-border bg-muted/30">
        <div class="flex items-center gap-2 border-b border-border px-4 py-3">
            <h2 class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                Hierarquia
            </h2>
            <span
                class="rounded border border-border bg-background px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground"
            >
                {{ categories.length }} raízes
            </span>
            <button
                v-if="hasSelection"
                type="button"
                class="ml-auto rounded px-2 py-0.5 font-mono text-[10px] text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                @click="emit('clear')"
            >
                Limpar
            </button>
        </div>
        <div class="border-b border-border p-3">
            <input
                :value="searchQuery"
                type="text"
                placeholder="Buscar categoria..."
                class="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs outline-none transition-colors placeholder:text-muted-foreground focus:border-primary focus:ring-1 focus:ring-primary"
                @input="emit('update:searchQuery', (($event.target as HTMLInputElement).value))"
            />
        </div>
        <ScrollArea class="overflow-y-auto p-2" style="max-height: calc(100vh - 12rem)">
            <MercadologicoPanelTreeItem
                v-for="node in filteredRoots"
                :key="node.id"
                :node="node"
                :expand-ids="expandIds ?? []"
                :selected-id="selectedId ?? null"
                :node-color-fn="nodeColor"
                @select="(node, ev) => emit('select', node, ev)"
                @expand-toggle="(nodeId, expanded) => emit('expandToggle', nodeId, expanded)"
                @drop-products="(id, ids) => emit('dropProducts', id, ids)"
            />
        </ScrollArea>
    </div>
</template>

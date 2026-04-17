<script setup lang="ts">
import { computed } from 'vue';
import type { CategoryNode } from '@/composables/useMercadologicoTree';
import type { HierarchyLevelNames } from '@/composables/useMercadologicoTree';
import {
    colorWithAlpha,
    getRootId,
    getAncestorIds,
    LEVEL_COLORS,
    LEVEL_NAMES,
    countChildren,
    flattenByDepth,
} from '@/composables/useMercadologicoTree';
import { ScrollArea } from '@/components/ui/scroll-area';

const props = withDefaults(
    defineProps<{
        categories: CategoryNode[];
        moveUrl: string;
        selectedId?: string | null;
        rootColorIndex?: Record<string, number>;
        /** Nomes dos níveis (fonte: backend HIERARCHY_LEVEL_NAMES) */
        hierarchyLevelNames?: HierarchyLevelNames | null;
    }>(),
    { rootColorIndex: () => ({}) },
);

const emit = defineEmits<{
    moved: [categoryId: string, newParentId: string | null];
    select: [node: CategoryNode, event?: MouseEvent];
}>();

const byDepth = computed(() => {
    const map = flattenByDepth(props.categories);
    const max = Math.max(0, ...map.keys());
    const entries: [number, CategoryNode[]][] = [];
    for (let d = 1; d <= Math.min(max, 8); d++) {
        entries.push([d, map.get(d) ?? []]);
    }
    return entries;
});

/** Computed diretamente do byDepth para garantir reatividade quando filhos chegam. */
const highlightedIds = computed(() => {
    const sel = props.selectedId;
    if (!sel) {
        return [];
    }

    const result = new Set<string>([sel]);

    // Ancestrais do nó selecionado
    const ancestors = getAncestorIds(props.categories, sel) ?? [];
    ancestors.forEach((a) => result.add(a));

    // Percorre todos os nós visíveis no Kanban para incluir descendentes
    for (const [, nodes] of byDepth.value) {
        for (const node of nodes) {
            if (result.has(node.id)) {
                continue;
            }
            const nodeAncestors = getAncestorIds(props.categories, node.id) ?? [];
            if (nodeAncestors.includes(sel)) {
                result.add(node.id);
            }
        }
    }

    return [...result];
});

function levelColor(depth: number): string {
    return LEVEL_COLORS[(depth - 1) % LEVEL_COLORS.length];
}

function nodeColor(node: CategoryNode): string {
    const depth = node.depth ?? 1;
    const rootId = getRootId(props.categories, node.id);
    if (rootId) {
        const index = props.rootColorIndex?.[rootId];
        if (index !== undefined) {
            return LEVEL_COLORS[index % LEVEL_COLORS.length] ?? levelColor(depth);
        }
    }

    return levelColor(depth);
}

function cardStyle(node: CategoryNode): Record<string, string> {
    const color = nodeColor(node);
    const highlighted = highlightedIds.value.includes(node.id);

    return {
        borderLeftWidth: '3px',
        borderLeftColor: color,
        backgroundColor: highlighted ? colorWithAlpha(color, 0.1) : '',
        borderColor: highlighted ? colorWithAlpha(color, 0.45) : '',
        boxShadow: highlighted ? `0 0 0 1px ${colorWithAlpha(color, 0.28)}` : '',
    };
}

function levelName(depth: number): string {
    return props.hierarchyLevelNames?.[depth] ?? LEVEL_NAMES[depth] ?? `Nível ${depth}`;
}

let draggedId: string | null = null;

function onCardDragStart(e: DragEvent, node: CategoryNode) {
    if (!e.dataTransfer) return;
    draggedId = node.id;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('application/json', JSON.stringify({ id: node.id }));
}

function onCardDragEnd() {
    draggedId = null;
}

function onZoneDragOver(e: DragEvent) {
    e.preventDefault();
    if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
}

function onZoneDrop(e: DragEvent) {
    e.preventDefault();
    const raw = e.dataTransfer?.getData('application/json');
    if (!raw) return;
    try {
        const { id } = JSON.parse(raw) as { id: string };
        emit('moved', id, null);
    } catch {
        //
    }
}

function onCardDrop(e: DragEvent, targetNode: CategoryNode) {
    e.preventDefault();
    e.stopPropagation();
    const raw = e.dataTransfer?.getData('application/json');
    if (!raw) return;
    try {
        const { id } = JSON.parse(raw) as { id: string };
        if (id !== targetNode.id) emit('moved', id, targetNode.id);
    } catch {
        //
    }
}

function onCardDragOver(e: DragEvent, targetId: string) {
    e.preventDefault();
    if (e.dataTransfer && draggedId !== targetId) e.dataTransfer.dropEffect = 'move';
}
</script>

<template>
    <div class="flex flex-1 gap-3 overflow-x-auto p-5">
        <div
            v-for="[depth, nodes] in byDepth"
            :key="depth"
            class="level-col flex w-72 shrink-0 flex-col gap-1.5"
        >
            <div class="level-header flex shrink-0 items-center gap-2 border-b-2 border-border pb-2">
                <div
                    class="level-pill flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-[10px] font-extrabold text-white"
                    :style="{ background: levelColor(depth) }"
                >
                    {{ depth }}
                </div>
                <span class="level-title flex-1 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                    {{ levelName(depth) }}
                </span>
                <span class="level-count font-mono text-[10px] text-muted-foreground">
                    {{ nodes.length }}
                </span>
            </div>
            <ScrollArea
                class="drop-zone flex flex-col gap-1 overflow-y-auto rounded-lg border border-dashed border-transparent p-1 pr-0.5 transition-colors" style="max-height: calc(100vh - 12rem)"
                :class="depth === 1 && 'border-muted-foreground/30'"
                @dragover="onZoneDragOver"
                @drop="depth === 1 ? onZoneDrop($event) : null"
            >
                <div
                    v-if="depth === 1 && nodes.length === 0"
                    class="empty-col rounded-lg border border-dashed border-border py-5 text-center text-[11px] text-muted-foreground"
                >
                    Solte aqui (raiz)
                </div>
                <div
                    v-for="node in nodes"
                    :key="node.id"
                    class="cat-card min-h-14 relative cursor-grab overflow-hidden rounded-lg border border-border bg-muted/40 px-3 py-2.5 transition-all hover:border-muted-foreground/40 hover:shadow-md active:cursor-grabbing"
                    :style="cardStyle(node)"
                    draggable="true"
                    @click="(e: MouseEvent) => emit('select', node, e)"
                    @dragstart="onCardDragStart($event, node)"
                    @dragend="onCardDragEnd"
                    @dragover="onCardDragOver($event, node.id)"
                    @drop="onCardDrop($event, node)"
                >
                    <div class="card-name mb-1 text-xs font-semibold leading-tight text-foreground">
                        {{ node.name }}
                    </div>
                    <div class="card-meta flex flex-wrap items-center justify-between gap-2">
                        <span class="card-slug truncate font-mono text-[9px] text-muted-foreground">
                            {{ node.slug ?? '—' }}
                        </span>
                        <div class="flex shrink-0 items-center gap-1">
                            <span
                                v-if="(node.products_count ?? 0) > 0"
                                class="rounded border border-border bg-background px-1.5 py-0.5 font-mono text-[9px] text-muted-foreground"
                            >
                                {{ node.products_count }} {{ (node.products_count ?? 0) === 1 ? 'produto' : 'produtos' }}
                            </span>
                            <span
                                v-if="countChildren(node) > 0"
                                class="rounded border border-border bg-background px-1.5 py-0.5 font-mono text-[9px] text-muted-foreground"
                            >
                                {{ countChildren(node) }} filhos
                            </span>
                        </div>
                    </div>
                </div>
            </ScrollArea>
        </div>
    </div>
</template>

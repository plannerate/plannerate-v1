<script setup lang="ts">
import { computed, ref } from 'vue';
import { visualCriterionMeta } from './slot-editor';
import type { VisualCriterionDirection, VisualCriterionItem, VisualCriterionKey } from './types';

/**
 * Editor de critérios visuais arrastáveis para ordenação de produtos no slot.
 * Lista de chips reordenáveis por drag-and-drop; o critério mais à esquerda domina.
 * null = usar comportamento legado (price_order/size_order/brand_exposure).
 */

const modelValue = defineModel<VisualCriterionItem[] | null>({ required: true });

const ALL_KEYS = Object.keys(visualCriterionMeta) as VisualCriterionKey[];

/** Critérios ativos (na lista ordenada) */
const active = computed<VisualCriterionItem[]>(() => modelValue.value ?? []);

/** Critérios disponíveis para adicionar (não estão na lista) */
const available = computed<VisualCriterionKey[]>(() => {
    const activeKeys = new Set(active.value.map((c) => c.key));
    return ALL_KEYS.filter((k) => !activeKeys.has(k));
});

/** Se visual_criteria é null, o slot usa o modo legado */
const isLegacyMode = computed(() => modelValue.value === null);

function enableCustomMode(): void {
    modelValue.value = [];
}

function revertToLegacy(): void {
    modelValue.value = null;
}

function addCriterion(key: VisualCriterionKey): void {
    const meta = visualCriterionMeta[key];
    const defaultDirection: VisualCriterionDirection = meta.supportsDirection ? 'asc' : 'none';
    modelValue.value = [...(modelValue.value ?? []), { key, direction: defaultDirection }];
}

function removeCriterion(index: number): void {
    const next = [...(modelValue.value ?? [])];
    next.splice(index, 1);
    modelValue.value = next;
}

function toggleDirection(index: number): void {
    const items = [...(modelValue.value ?? [])];
    const item = items[index];
    if (!visualCriterionMeta[item.key].supportsDirection) {
        return;
    }

    const cycle: VisualCriterionDirection[] = ['asc', 'desc', 'none'];
    const current = cycle.indexOf(item.direction);
    items[index] = { ...item, direction: cycle[(current + 1) % cycle.length] };
    modelValue.value = items;
}

// Drag-and-drop — estado local
const dragIndex = ref<number | null>(null);
const dragOverIndex = ref<number | null>(null);

function onDragStart(index: number, event: DragEvent): void {
    dragIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function onDragOver(index: number, event: DragEvent): void {
    event.preventDefault();
    dragOverIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
}

function onDrop(targetIndex: number): void {
    const from = dragIndex.value;
    if (from === null || from === targetIndex) {
        dragIndex.value = null;
        dragOverIndex.value = null;
        return;
    }

    const items = [...(modelValue.value ?? [])];
    const [moved] = items.splice(from, 1);
    items.splice(targetIndex, 0, moved);
    modelValue.value = items;
    dragIndex.value = null;
    dragOverIndex.value = null;
}

function onDragEnd(): void {
    dragIndex.value = null;
    dragOverIndex.value = null;
}

function directionLabel(item: VisualCriterionItem): string {
    if (!visualCriterionMeta[item.key].supportsDirection) {
        return '';
    }

    return item.direction === 'asc' ? '↑' : item.direction === 'desc' ? '↓' : '—';
}

function directionTitle(item: VisualCriterionItem): string {
    if (!visualCriterionMeta[item.key].supportsDirection) {
        return '';
    }

    return item.direction === 'asc' ? 'Crescente — clique para inverter' : item.direction === 'desc' ? 'Decrescente — clique para inverter' : 'Sem direção — clique para definir';
}
</script>

<template>
    <div class="flex flex-col gap-y-3">
        <!-- Cabeçalho com toggle de modo -->
        <div class="flex items-center justify-between">
            <div class="flex flex-col gap-y-0.5">
                <span class="text-sm font-medium">Critérios de ordenação visual</span>
                <span class="text-xs text-muted-foreground">
                    {{ isLegacyMode ? 'Usando ordenação padrão (preço / tamanho / marca).' : 'Critérios ativos — arraste para reordenar. O mais à esquerda domina.' }}
                </span>
            </div>
            <button
                type="button"
                class="text-xs text-muted-foreground underline underline-offset-2 hover:text-foreground"
                @click="isLegacyMode ? enableCustomMode() : revertToLegacy()"
            >
                {{ isLegacyMode ? 'Personalizar' : 'Usar padrão' }}
            </button>
        </div>

        <!-- Modo personalizado -->
        <template v-if="!isLegacyMode">
            <!-- Lista de critérios ativos (drag-and-drop) -->
            <div
                v-if="active.length > 0"
                class="flex flex-wrap gap-2"
                role="list"
                aria-label="Critérios de ordenação (mais à esquerda = maior prioridade)"
            >
                <div
                    v-for="(item, index) in active"
                    :key="item.key"
                    role="listitem"
                    draggable="true"
                    class="group flex cursor-grab items-center gap-1 rounded-full border px-3 py-1 text-sm select-none active:cursor-grabbing"
                    :class="[
                        dragOverIndex === index && dragIndex !== index
                            ? 'border-primary bg-primary/10 ring-1 ring-primary'
                            : 'border-border bg-muted',
                        dragIndex === index ? 'opacity-40' : '',
                    ]"
                    :aria-label="`${visualCriterionMeta[item.key].label}, prioridade ${index + 1}`"
                    @dragstart="onDragStart(index, $event)"
                    @dragover="onDragOver(index, $event)"
                    @drop="onDrop(index)"
                    @dragend="onDragEnd"
                >
                    <!-- Indicador de posição -->
                    <span class="text-xs font-mono text-muted-foreground">{{ index + 1 }}</span>

                    <!-- Nome do critério -->
                    <span class="font-medium">{{ visualCriterionMeta[item.key].label }}</span>

                    <!-- Botão de direção -->
                    <button
                        v-if="visualCriterionMeta[item.key].supportsDirection"
                        type="button"
                        class="rounded px-1 text-xs font-bold text-muted-foreground hover:bg-background hover:text-foreground"
                        :title="directionTitle(item)"
                        @click="toggleDirection(index)"
                    >
                        {{ directionLabel(item) }}
                    </button>

                    <!-- Botão remover -->
                    <button
                        type="button"
                        class="ml-1 rounded-full text-muted-foreground opacity-0 transition-opacity hover:text-destructive group-hover:opacity-100"
                        title="Remover critério"
                        @click="removeCriterion(index)"
                    >
                        ×
                    </button>
                </div>
            </div>

            <p v-else class="text-xs text-muted-foreground italic">
                Nenhum critério ativo — adicione abaixo ou reverta para o padrão.
            </p>

            <!-- Critérios disponíveis para adicionar -->
            <div v-if="available.length > 0" class="flex flex-wrap gap-2">
                <span class="self-center text-xs text-muted-foreground">Adicionar:</span>
                <button
                    v-for="key in available"
                    :key="key"
                    type="button"
                    class="flex items-center gap-1 rounded-full border border-dashed border-border px-3 py-1 text-xs text-muted-foreground hover:border-primary hover:text-foreground"
                    @click="addCriterion(key)"
                >
                    + {{ visualCriterionMeta[key].label }}
                </button>
            </div>
        </template>
    </div>
</template>

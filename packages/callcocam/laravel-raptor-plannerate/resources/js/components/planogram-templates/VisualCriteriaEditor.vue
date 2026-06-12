<script setup lang="ts">
import { computed, ref } from 'vue';
import { useT } from '@/composables/useT';
import { visualCriterionMeta } from './slot-editor';
import type { VisualCriterionDirection, VisualCriterionItem, VisualCriterionKey } from './types';

/**
 * Editor de critérios visuais arrastáveis para ordenação de produtos no slot.
 * Lista de chips reordenáveis por drag-and-drop; o critério mais à esquerda domina.
 * null = usar comportamento legado (price_order/size_order/brand_exposure).
 */

const modelValue = defineModel<VisualCriterionItem[] | null>({ required: true });

const { t } = useT();

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

/** Índice do critério 'embalagem' na lista ativa (ou -1) */
const packagingCriterionIndex = computed(() => active.value.findIndex((c) => c.key === 'embalagem'));

/** Lista de tipos de embalagem do critério ativo */
const packagingOrder = computed<string[]>(() => {
    const idx = packagingCriterionIndex.value;
    if (idx === -1) return [];
    return active.value[idx].packaging_order ?? [];
});

const newPackagingType = ref('');
const packagingDragIndex = ref<number | null>(null);
const packagingDragOverIndex = ref<number | null>(null);

function enableCustomMode(): void {
    modelValue.value = [];
}

function revertToLegacy(): void {
    modelValue.value = null;
}

function addCriterion(key: VisualCriterionKey): void {
    const meta = visualCriterionMeta[key];
    const defaultDirection: VisualCriterionDirection = meta.supportsDirection ? 'asc' : 'none';
    const item: VisualCriterionItem =
        key === 'embalagem'
            ? { key, direction: 'none', packaging_order: [] }
            : { key, direction: defaultDirection };
    modelValue.value = [...(modelValue.value ?? []), item];
}

/** score_abc na posição 0 é obrigatório e não pode ser removido nem movido */
function isLocked(index: number): boolean {
    return index === 0 && active.value[0]?.key === 'score_abc';
}

function removeCriterion(index: number): void {
    if (isLocked(index)) return;
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

// Drag-and-drop dos critérios principais — estado local
const dragIndex = ref<number | null>(null);
const dragOverIndex = ref<number | null>(null);

function onDragStart(index: number, event: DragEvent): void {
    if (isLocked(index)) {
        event.preventDefault();
        return;
    }
    dragIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function onDragOver(index: number, event: DragEvent): void {
    // Bloqueia drop na posição 0 quando ela é score_abc
    if (isLocked(index)) return;
    event.preventDefault();
    dragOverIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
}

function onDrop(targetIndex: number): void {
    const from = dragIndex.value;
    if (from === null || from === targetIndex || isLocked(targetIndex)) {
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

    return item.direction === 'asc'
        ? t('planogram-templates.visual_criteria.direction_asc')
        : item.direction === 'desc'
            ? t('planogram-templates.visual_criteria.direction_desc')
            : t('planogram-templates.visual_criteria.direction_none');
}

/** Atualiza o packaging_order do critério embalagem preservando os demais campos */
function updatePackagingOrder(order: string[]): void {
    const idx = packagingCriterionIndex.value;
    if (idx === -1) return;
    const items = [...(modelValue.value ?? [])];
    items[idx] = { ...items[idx], packaging_order: order };
    modelValue.value = items;
}

function addPackagingType(): void {
    const val = newPackagingType.value.trim();
    if (!val || packagingOrder.value.includes(val)) {
        newPackagingType.value = '';
        return;
    }
    updatePackagingOrder([...packagingOrder.value, val]);
    newPackagingType.value = '';
}

function removePackagingType(index: number): void {
    const next = [...packagingOrder.value];
    next.splice(index, 1);
    updatePackagingOrder(next);
}

// Drag-and-drop dos tipos de embalagem
function onPackagingDragStart(index: number, event: DragEvent): void {
    packagingDragIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function onPackagingDragOver(index: number, event: DragEvent): void {
    event.preventDefault();
    packagingDragOverIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
}

function onPackagingDrop(targetIndex: number): void {
    const from = packagingDragIndex.value;
    if (from === null || from === targetIndex) {
        packagingDragIndex.value = null;
        packagingDragOverIndex.value = null;
        return;
    }
    const items = [...packagingOrder.value];
    const [moved] = items.splice(from, 1);
    items.splice(targetIndex, 0, moved);
    updatePackagingOrder(items);
    packagingDragIndex.value = null;
    packagingDragOverIndex.value = null;
}

function onPackagingDragEnd(): void {
    packagingDragIndex.value = null;
    packagingDragOverIndex.value = null;
}
</script>

<template>
    <div class="flex flex-col gap-y-3">
        <!-- Cabeçalho com toggle de modo -->
        <div class="flex items-center justify-between">
            <div class="flex flex-col gap-y-0.5">
                <span class="text-sm font-medium">{{ t('planogram-templates.visual_criteria.title') }}</span>
                <span class="text-xs text-muted-foreground">
                    {{ isLegacyMode ? t('planogram-templates.visual_criteria.description_legacy') : t('planogram-templates.visual_criteria.description_custom') }}
                </span>
            </div>
            <button
                type="button"
                class="text-xs text-muted-foreground underline underline-offset-2 hover:text-foreground"
                @click="isLegacyMode ? enableCustomMode() : revertToLegacy()"
            >
                {{ isLegacyMode ? t('planogram-templates.visual_criteria.customize_button') : t('planogram-templates.visual_criteria.use_default_button') }}
            </button>
        </div>

        <!-- Modo personalizado -->
        <template v-if="!isLegacyMode">
            <!-- Lista de critérios ativos (drag-and-drop) -->
            <div
                v-if="active.length > 0"
                class="flex flex-wrap gap-2"
                role="list"
                :aria-label="t('planogram-templates.visual_criteria.list_aria_label')"
            >
                <div
                    v-for="(item, index) in active"
                    :key="item.key"
                    role="listitem"
                    :draggable="!isLocked(index)"
                    class="group flex items-center gap-1 rounded-full border px-3 py-1 text-sm select-none"
                    :class="[
                        isLocked(index)
                            ? 'cursor-default border-primary/40 bg-primary/10 text-primary'
                            : 'cursor-grab active:cursor-grabbing',
                        !isLocked(index) && dragOverIndex === index && dragIndex !== index
                            ? 'border-primary bg-primary/10 ring-1 ring-primary'
                            : !isLocked(index) ? 'border-border bg-muted' : '',
                        dragIndex === index ? 'opacity-40' : '',
                    ]"
                    :aria-label="t('planogram-templates.visual_criteria.chip_aria_label', { label: t('planogram-templates.visual_criteria.criteria_labels.' + item.key), n: String(index + 1) })"
                    @dragstart="onDragStart(index, $event)"
                    @dragover="onDragOver(index, $event)"
                    @drop="onDrop(index)"
                    @dragend="onDragEnd"
                >
                    <!-- Indicador de posição -->
                    <span class="text-xs font-mono text-muted-foreground">{{ index + 1 }}</span>

                    <!-- Nome do critério -->
                    <span class="font-medium">{{ t('planogram-templates.visual_criteria.criteria_labels.' + item.key) }}</span>

                    <!-- Ícone de travado para score_abc (posição 0) -->
                    <span v-if="isLocked(index)" class="ml-0.5 text-xs text-primary/70" :title="t('planogram-templates.visual_criteria.abc_locked_tooltip')">🔒</span>

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

                    <!-- Botão remover (oculto para critério travado) -->
                    <button
                        v-if="!isLocked(index)"
                        type="button"
                        class="ml-1 rounded-full text-muted-foreground opacity-0 transition-opacity hover:text-destructive group-hover:opacity-100"
                        :title="t('planogram-templates.visual_criteria.remove_criterion_tooltip')"
                        @click="removeCriterion(index)"
                    >
                        ×
                    </button>
                </div>
            </div>

            <p v-else class="text-xs text-muted-foreground italic">
                {{ t('planogram-templates.visual_criteria.empty_message') }}
            </p>

            <!-- Sub-editor de ordem de tipos de embalagem -->
            <div v-if="packagingCriterionIndex !== -1" class="rounded-md border border-border p-3 flex flex-col gap-y-2">
                <span class="text-xs font-medium text-foreground">{{ t('planogram-templates.visual_criteria.packaging_order.title') }}</span>
                <span class="text-xs text-muted-foreground">{{ t('planogram-templates.visual_criteria.packaging_order.description') }}</span>

                <!-- Lista reordenável de tipos -->
                <div v-if="packagingOrder.length > 0" class="flex flex-wrap gap-2">
                    <div
                        v-for="(type, pIdx) in packagingOrder"
                        :key="type"
                        draggable="true"
                        class="group flex cursor-grab items-center gap-1 rounded border px-2 py-0.5 text-xs select-none active:cursor-grabbing"
                        :class="[
                            packagingDragOverIndex === pIdx && packagingDragIndex !== pIdx
                                ? 'border-primary bg-primary/10 ring-1 ring-primary'
                                : 'border-border bg-muted',
                            packagingDragIndex === pIdx ? 'opacity-40' : '',
                        ]"
                        @dragstart="onPackagingDragStart(pIdx, $event)"
                        @dragover="onPackagingDragOver(pIdx, $event)"
                        @drop="onPackagingDrop(pIdx)"
                        @dragend="onPackagingDragEnd"
                    >
                        <span class="font-mono text-muted-foreground">{{ pIdx + 1 }}</span>
                        <span>{{ type }}</span>
                        <button
                            type="button"
                            class="ml-1 rounded-full text-muted-foreground opacity-0 transition-opacity hover:text-destructive group-hover:opacity-100"
                            :title="t('planogram-templates.visual_criteria.packaging_order.remove_tooltip')"
                            @click="removePackagingType(pIdx)"
                        >×</button>
                    </div>
                </div>

                <p v-else class="text-xs text-muted-foreground italic">
                    {{ t('planogram-templates.visual_criteria.packaging_order.empty_message') }}
                </p>

                <!-- Input para adicionar novo tipo -->
                <div class="flex gap-2">
                    <input
                        v-model="newPackagingType"
                        type="text"
                        :placeholder="t('planogram-templates.visual_criteria.packaging_order.add_placeholder')"
                        class="flex h-7 flex-1 rounded-md border border-border bg-background px-2 text-xs ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring"
                        @keydown.enter.prevent="addPackagingType"
                    />
                    <button
                        type="button"
                        class="rounded-md border border-border px-2 py-1 text-xs hover:bg-muted"
                        @click="addPackagingType"
                    >
                        {{ t('planogram-templates.visual_criteria.packaging_order.add_button') }}
                    </button>
                </div>
            </div>

            <!-- Critérios disponíveis para adicionar -->
            <div v-if="available.length > 0" class="flex flex-wrap gap-2">
                <span class="self-center text-xs text-muted-foreground">{{ t('planogram-templates.visual_criteria.add_label') }}</span>
                <button
                    v-for="key in available"
                    :key="key"
                    type="button"
                    class="flex items-center gap-1 rounded-full border border-dashed border-border px-3 py-1 text-xs text-muted-foreground hover:border-primary hover:text-foreground"
                    @click="addCriterion(key)"
                >
                    + {{ t('planogram-templates.visual_criteria.criteria_labels.' + key) }}
                </button>
            </div>
        </template>
    </div>
</template>

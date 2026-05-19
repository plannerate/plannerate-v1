<script setup lang="ts">
import { Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useT } from '@/composables/useT';
import { getShelfLevel, getZoneConfig } from '@/composables/plannerate/useShelfZone';
import SlotCard from './SlotCard.vue';
import type { PlanogramTemplateSlot } from './types';

type DragPosition = { module_number: number; shelf_order: number };

const props = defineProps<{
    slots: PlanogramTemplateSlot[];
    numModules: number;
    numShelves: number;
    highlightNewModule?: number;
}>();

const emit = defineEmits<{
    'cell-click': [module: number, shelf: number, slot: PlanogramTemplateSlot | null];
    'slot-remove': [module: number, shelf: number];
    'slot-drop': [from: DragPosition, to: DragPosition];
}>();

const { t } = useT();

/** slot lookup indexed by "module-shelf" for O(1) access */
const slotMap = computed(() =>
    props.slots.reduce(
        (map, s) => {
            map[`${s.module_number}-${s.shelf_order}`] = s;
            return map;
        },
        {} as Record<string, PlanogramTemplateSlot>,
    ),
);

function getSlot(module: number, shelf: number): PlanogramTemplateSlot | null {
    return slotMap.value[`${module}-${shelf}`] ?? null;
}

/** shelves displayed top-to-bottom: shelf N at top, shelf 1 at bottom */
const shelvesTopToBottom = computed(() =>
    Array.from({ length: props.numShelves }, (_, i) => props.numShelves - i),
);

function shelfZoneConfig(indexFromTop: number) {
    return getZoneConfig(getShelfLevel(indexFromTop, props.numShelves));
}

/** modules left-to-right */
const modulesLeftToRight = computed(() =>
    Array.from({ length: props.numModules }, (_, i) => i + 1),
);

// ── drag and drop ──────────────────────────────────────────────────────────────
const dragOver = ref<string | null>(null);

function onDragOver(module: number, shelf: number): void {
    dragOver.value = `${module}-${shelf}`;
}

function onDragLeave(): void {
    dragOver.value = null;
}

function onDrop(event: DragEvent, toModule: number, toShelf: number): void {
    dragOver.value = null;
    const raw = event.dataTransfer?.getData('application/json');
    if (!raw) return;
    try {
        const from: DragPosition = JSON.parse(raw);
        if (from.module_number === toModule && from.shelf_order === toShelf) return;
        emit('slot-drop', from, { module_number: toModule, shelf_order: toShelf });
    } catch {
        // malformed drag data
    }
}

function isDragOver(module: number, shelf: number): boolean {
    return dragOver.value === `${module}-${shelf}`;
}
</script>

<template>
    <div class="overflow-x-auto rounded-lg border border-border">
        <div
            class="grid min-w-max"
            :style="{
                gridTemplateColumns: `4rem repeat(${numModules}, minmax(9rem, 42rem))`,
            }"
        >
            <!-- Header row: empty corner + module labels -->
            <div class="border-b border-r border-border bg-muted/50 px-2 py-1.5" />
            <div
                v-for="m in modulesLeftToRight"
                :key="`hdr-${m}`"
                class="border-b border-r border-border bg-muted/50 px-3 py-1.5 text-center text-xs font-semibold text-muted-foreground last:border-r-0"
            >
                {{ t('planogram-templates.grid.module_label') }}{{ m }}
            </div>

            <!-- Data rows: shelf label + cells -->
            <template v-for="(shelf, indexFromTop) in shelvesTopToBottom" :key="`row-${shelf}`">
                <!-- Shelf label with zone indicator -->
                <div
                    class="flex items-center justify-center border-b border-r border-border px-1 py-1 text-[11px] font-medium last:border-b-0"
                    :class="[shelfZoneConfig(indexFromTop).bgClass, shelfZoneConfig(indexFromTop).textClass]"
                    :title="shelfZoneConfig(indexFromTop).label"
                >
                    <span class="writing-mode-vertical rotate-0">{{ t('planogram-templates.grid.shelf_label') }}{{ shelf }} · {{ shelfZoneConfig(indexFromTop).labelShort }}</span>
                </div>

                <!-- Cells -->
                <div
                    v-for="m in modulesLeftToRight"
                    :key="`cell-${m}-${shelf}`"
                    class="relative min-h-20 border-b border-r border-border p-1 transition-colors last:border-r-0"
                    :class="{
                        'bg-blue-50 ring-2 ring-inset ring-blue-400': isDragOver(m, shelf),
                        'bg-amber-50/40': !isDragOver(m, shelf) && !getSlot(m, shelf) && props.highlightNewModule !== undefined && m >= props.highlightNewModule,
                        'bg-background': !isDragOver(m, shelf) && !getSlot(m, shelf) && !(props.highlightNewModule !== undefined && m >= props.highlightNewModule),
                    }"
                    @dragover.prevent="onDragOver(m, shelf)"
                    @dragleave="onDragLeave"
                    @drop.prevent="onDrop($event, m, shelf)"
                    @click="getSlot(m, shelf) === null ? emit('cell-click', m, shelf, null) : undefined"
                >
                    <!-- Slot card when occupied -->
                    <SlotCard
                        v-if="getSlot(m, shelf)"
                        :slot="getSlot(m, shelf)!"
                        @edit="emit('cell-click', m, shelf, getSlot(m, shelf))"
                        @remove="emit('slot-remove', m, shelf)"
                    />

                    <!-- Empty cell — click to add -->
                    <button
                        v-else
                        type="button"
                        class="flex h-full w-full cursor-pointer flex-col items-center justify-center gap-1 rounded border border-dashed text-xs transition"
                        :class="props.highlightNewModule !== undefined && m >= props.highlightNewModule
                            ? 'border-amber-400 text-amber-500/70 hover:border-amber-500 hover:bg-amber-50/50 hover:text-amber-600'
                            : 'border-border text-muted-foreground/50 hover:border-muted-foreground/40 hover:bg-muted/30 hover:text-muted-foreground'"
                        @click="emit('cell-click', m, shelf, null)"
                    >
                        <Plus class="size-3.5" />
                        <span>{{ t('planogram-templates.grid.add_button') }}</span>
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>

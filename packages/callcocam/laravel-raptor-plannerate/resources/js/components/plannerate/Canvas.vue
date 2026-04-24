<script setup lang="ts">
import { usePlanogramEditor } from '@/composables/plannerate/usePlanogramEditor';
import { usePlanogramSelection } from '@/composables/plannerate/usePlanogramSelection';
import type { Gondola } from '@/types/planogram';
import { onClickOutside } from '@vueuse/core';
import { Package } from 'lucide-vue-next';
import { computed, onMounted, useTemplateRef, watch } from 'vue';
import Sections from './editor/Sections.vue';
import Indicador from './Indicador.vue';

interface Props {
    record?: Gondola;
    loading?: boolean;
    openProducts?: boolean;
    openProperties?: boolean;
    containerHeight?: number;
    saveChangesRoute?: string;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
    openProducts: false,
    openProperties: false,
});
const emit = defineEmits<{
    closeProducts: [];
    openProducts: [];
    closeProperties: [];
    openProperties: [];
}>();

const editor = usePlanogramEditor();
const selection = usePlanogramSelection();

const sortedSections = computed(() => {
    return editor.sectionsOrdered.value;
});

const canvasTopPadding = computed(() => {
    const scale = editor.scaleFactor.value;
    let maxOverflow = 48; // baseline matches pt-12

    for (const section of sortedSections.value) {
        for (const shelf of (section.shelves ?? []).filter(s => !s.deleted_at)) {
            const shelfTopPx = shelf.shelf_position * scale;

            for (const segment of (shelf.segments ?? []).filter(s => !s.deleted_at)) {
                const productHeight = (segment.layer?.product?.height ?? 0) * scale;
                const qty = segment.quantity ?? 1;
                const overflow = productHeight * qty - shelfTopPx;
                if (overflow > maxOverflow) {
                    maxOverflow = overflow;
                }
            }
        }
    }

    return Math.ceil(maxOverflow) + 16;
});

function handleCanvasClick(event: MouseEvent) {
    const target = event.target as HTMLElement;

    // Verifica se o clique foi em um elemento que não deve deselecionar
    if (
        target.closest(
            '[data-shelf], [data-segment], [data-section], [data-properties-panel], [data-products-panel], [data-toolbar], [data-modal], [data-slot="select-trigger"], [data-slot="select-content"], [data-slot="select-item"]',
        )
    ) {
        return;
    }

    // Verifica se há um Dialog aberto (reka-ui usa data-state="open" nos elementos do Dialog)
    // DialogPortal renderiza os elementos diretamente no body, então verificamos se o clique foi dentro de um Dialog
    if (
        target.closest('[data-slot="dialog-overlay"]') ||
        target.closest('[data-slot="dialog-content"]') ||
        document.querySelector('[data-slot="dialog"][data-state="open"]')
    ) {
        return;
    }

    selection.clearSelection();
}

onMounted(() => {
    const manualOpen = localStorage.getItem('planogram-properties-manual-open');
    if (manualOpen === 'true' && !props.openProperties) {
        emit('openProperties');
    }
});

watch(
    () => selection.selectedItem.value,
    (newSelection) => {
        // Abre painel quando há item selecionado (nunca fecha automaticamente)
        if (newSelection?.type) {
            emit('openProperties');
            localStorage.setItem('planogram-properties-manual-open', 'true');
        }
    },
);

const target = useTemplateRef<HTMLElement>('target');

onClickOutside(target, (event) => handleCanvasClick(event));

// Computed para direção do fluxo
const flowDirection = computed(
    () => editor.currentGondola.value?.flow || 'left_to_right',
);
const isLeftToRight = computed(() => flowDirection.value === 'left_to_right');
</script>
<template>
    <div
        class="relative flex min-w-0 flex-1 flex-col bg-muted/30"
        ref="target"
        v-if="containerHeight"
    >
        <div
            class="relative overflow-auto border border-dashed border-border bg-background p-8 dark:bg-background"
            :style="{ height: containerHeight + 'px' }"
        >
            <!-- Grade de alinhamento -->
            <div
                v-if="editor.showGrid.value"
                class="pointer-events-none absolute inset-0 z-10"
                :style="{
                    backgroundImage:
                        'repeating-linear-gradient(0deg, transparent, transparent 49px, color-mix(in srgb, currentColor 15%, transparent) 49px, color-mix(in srgb, currentColor 15%, transparent) 50px), repeating-linear-gradient(90deg, transparent, transparent 49px, color-mix(in srgb, currentColor 15%, transparent) 49px, color-mix(in srgb, currentColor 15%, transparent) 50px)',
                }"
            />

            <!-- Indicador de Direção da Gôndola - Discreto -->
            <Indicador :isLeftToRight="isLeftToRight" />

            <!-- Sections do Planograma -->
            <div
                v-if="sortedSections.length > 0"
                class="flex min-h-full items-start pb-4"
                :style="{ paddingTop: canvasTopPadding + 'px' }"
                data-planogram-canvas
                @click="handleCanvasClick"
            >
                <Sections
                    :sections="sortedSections"
                    :scale="editor.scaleFactor.value"
                />
            </div>

            <!-- Placeholder quando não há sections -->
            <div
                v-else
                class="flex h-full flex-col items-center justify-center gap-4 p-12 text-center"
            >
                <div class="rounded-full bg-muted p-4">
                    <Package class="size-8 text-muted-foreground" />
                </div>
                <div class="space-y-2">
                    <h3 class="font-medium">Área de Planograma</h3>
                    <p class="max-w-sm text-sm text-muted-foreground">
                        O planograma será renderizado aqui. Arraste produtos do
                        painel esquerdo para começar a montar sua gôndola.
                    </p>
                </div>
                <div v-if="loading" class="mt-4">
                    <div class="h-2 w-48 animate-pulse rounded-full bg-muted" />
                </div>
            </div>
        </div>
    </div>
</template>

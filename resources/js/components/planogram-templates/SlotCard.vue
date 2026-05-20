<script setup lang="ts">
import { AlertTriangle, Pencil, X } from 'lucide-vue-next';
import { computed } from 'vue';
import { useT } from '@/composables/useT';
import { slotColor } from './types';
import type { PlanogramTemplateSlot, SlotFacingExpansion, SlotPriceOrder } from './types';

const props = defineProps<{
    slot: PlanogramTemplateSlot;
}>();

const emit = defineEmits<{
    edit: [];
    remove: [];
}>();

const { t } = useT();

const colors = computed(() => slotColor(props.slot.category_id ?? ''));

const priceOrderLabel: Record<SlotPriceOrder, string> = {
    asc: t('planogram-templates.slot_card.price_order.asc'),
    desc: t('planogram-templates.slot_card.price_order.desc'),
    none: '',
};

const expansionLabel: Record<SlotFacingExpansion, string> = {
    none: '',
    score: 'ABC',
    current_stock: 'Estoque',
    equal: 'Igual',
};

const facingsRange = computed(() => {
    const min = props.slot.min_facings;
    const max = props.slot.max_facings;
    return min === max ? `${min}f` : `${min}–${max}f`;
});

function onDragStart(event: DragEvent): void {
    event.dataTransfer?.setData('application/json', JSON.stringify({
        module_number: props.slot.module_number,
        shelf_order: props.slot.shelf_order,
    }));
}
</script>

<template>
    <div
        class="group relative flex h-full w-full cursor-grab flex-col gap-1 rounded border p-2 text-xs active:cursor-grabbing"
        :style="{
            backgroundColor: colors.background,
            borderColor: colors.border,
            color: colors.color,
        }"
        draggable="true"
        @dragstart="onDragStart"
    >
        <!-- Category name -->
        <span class="line-clamp-2 pr-10 font-semibold leading-tight">
            {{ slot.category_name ?? 'Sem categoria' }}
        </span>

        <!-- Category breadcrumb path -->
        <span v-if="slot.category_path" class="line-clamp-1 text-[10px] opacity-70">
            {{ slot.category_path }}
        </span>

        <!-- Facings range + expansion mode + priority -->
        <div class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[10px] opacity-90">
            <span class="font-medium">{{ facingsRange }}</span>
            <span
                v-if="slot.facing_expansion !== 'none'"
                class="rounded px-1 py-px font-medium"
                style="background-color: rgba(0,0,0,0.12)"
            >
                {{ expansionLabel[slot.facing_expansion] }}
            </span>
            <span v-if="slot.priority > 1">P{{ slot.priority }}</span>
            <span v-if="priceOrderLabel[slot.price_order]">· {{ priceOrderLabel[slot.price_order] }}</span>
        </div>

        <!-- Rejected products badge from historical runs -->
        <div
            v-if="slot.rejected_count && slot.rejected_count > 0"
            class="mt-auto flex items-center gap-0.5 rounded px-1 py-0.5 text-[10px] font-medium"
            style="background-color: rgba(0,0,0,0.12)"
            :title="`${slot.rejected_count} produto(s) rejeitado(s) no histórico de geração`"
        >
            <AlertTriangle class="size-2.5 shrink-0" />
            <span>{{ slot.rejected_count }} rejeitado{{ slot.rejected_count !== 1 ? 's' : '' }}</span>
        </div>

        <!-- Action buttons -->
        <div class="absolute right-1 top-1 flex gap-1">
            <button
                type="button"
                class="rounded p-1 transition hover:bg-black/10"
                @click.stop="emit('edit')"
            >
                <Pencil class="size-4" />
            </button>
            <button
                type="button"
                class="rounded p-1 transition hover:bg-black/10"
                @click.stop="emit('remove')"
            >
                <X class="size-4" />
            </button>
        </div>
    </div>
</template>

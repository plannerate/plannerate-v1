<script setup lang="ts">
import { Pencil, X } from 'lucide-vue-next';
import { computed } from 'vue';
import { useT } from '@/composables/useT';
import { groupingToColor } from './types';
import type { PlanogramTemplateSlot } from './types';

const props = defineProps<{
    slot: PlanogramTemplateSlot;
}>();

const emit = defineEmits<{
    edit: [];
    remove: [];
}>();

const { t } = useT();

const colors = computed(() => groupingToColor(props.slot.grouping));

const priceOrderLabel: Record<string, string> = {
    asc: t('planogram-templates.slot_card.price_order.asc'),
    desc: t('planogram-templates.slot_card.price_order.desc'),
    none: '',
};

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
        <span class="line-clamp-2 font-semibold leading-tight">{{ slot.grouping }}</span>
        <div class="flex flex-wrap gap-1 text-[10px] opacity-75">
            <span>{{ t('planogram-templates.slot_card.min_facings_label') }} {{ slot.min_facings }}{{ t('planogram-templates.slot_card.facings_abbr') }}</span>
            <span v-if="priceOrderLabel[slot.price_order]">· {{ priceOrderLabel[slot.price_order] }}</span>
            <span v-if="slot.priority > 1">· {{ t('planogram-templates.slot_card.priority_prefix') }}{{ slot.priority }}</span>
        </div>

        <!-- action buttons — visible on hover -->
        <div class="absolute right-1 top-1 hidden gap-0.5 group-hover:flex">
            <button
                type="button"
                class="rounded p-0.5 transition hover:bg-black/10"
                @click.stop="emit('edit')"
            >
                <Pencil class="size-3" />
            </button>
            <button
                type="button"
                class="rounded p-0.5 transition hover:bg-black/10"
                @click.stop="emit('remove')"
            >
                <X class="size-3" />
            </button>
        </div>
    </div>
</template>

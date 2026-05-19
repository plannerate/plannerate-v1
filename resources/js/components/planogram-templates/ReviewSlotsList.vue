<script setup lang="ts">
import type { PlanogramTemplateSlot } from '@/components/planogram-templates/types';

const props = defineProps<{
    slots: PlanogramTemplateSlot[];
    selectedSlotId: string | null;
}>();

const emit = defineEmits<{
    select: [slotId: string];
}>();
</script>

<template>
    <div class="rounded-lg border bg-card p-4 col-end-12 md:col-span-3 lg:col-span-4">
        <div>
            <slot />
        </div>
        <p class="mb-3 text-sm font-semibold">Slots criados</p>
        <div class="space-y-2">
            <button v-for="slot in props.slots" :key="slot.id" type="button"
                class="w-full rounded-md border px-3 py-2 text-left text-sm transition cursor-pointer" :class="props.selectedSlotId === slot.id
                    ? 'border-primary bg-primary/5'
                    : 'border-border hover:border-primary/50 hover:bg-muted/30'
                    " @click="slot.id ? emit('select', slot.id) : null">
                Prateleira
                {{ slot.shelf_order }} — {{ slot.grouping }}
            </button>
            <p v-if="props.slots.length === 0" class="text-sm text-muted-foreground">
                Nenhum slot criado para este módulo.
            </p>
        </div>
    </div>
</template>

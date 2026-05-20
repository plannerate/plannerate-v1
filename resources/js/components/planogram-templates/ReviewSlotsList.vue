<script setup lang="ts">
import { computed } from 'vue';
import type { PlanogramTemplateSlot } from '@/components/planogram-templates/types';

const props = defineProps<{
    slots: PlanogramTemplateSlot[];
    selectedSlotId: string | null;
}>();

const emit = defineEmits<{
    select: [slotId: string];
}>();

type SlotGroup = {
    categoryId: string | null;
    categoryName: string;
    slots: PlanogramTemplateSlot[];
};

const groupedSlots = computed((): SlotGroup[] => {
    const map = new Map<string, SlotGroup>();

    for (const slot of props.slots) {
        const key = slot.category_id ?? '__no_category__';
        if (!map.has(key)) {
            map.set(key, {
                categoryId: slot.category_id,
                categoryName: slot.category_name ?? slot.category_id ?? 'Sem categoria',
                slots: [],
            });
        }
        map.get(key)!.slots.push(slot);
    }

    return Array.from(map.values());
});
</script>

<template>
    <div class="rounded-lg border bg-card p-4 col-end-12 md:col-span-3 lg:col-span-4">
        <div>
            <slot />
        </div>
        <p class="mb-3 text-sm font-semibold">Slots criados</p>

        <div class="space-y-3">
            <template v-for="group in groupedSlots" :key="group.categoryId ?? '__no_category__'">
                <!-- Group header (when multiple shelves share the same category) -->
                <div v-if="group.slots.length > 1">
                    <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ group.categoryName }}
                        <span class="font-normal">({{ group.slots.length }} prateleiras)</span>
                    </p>
                    <div class="space-y-1 pl-2 border-l-2 border-border">
                        <button
                            v-for="slot in group.slots"
                            :key="slot.id"
                            type="button"
                            class="w-full rounded-md border px-3 py-2 text-left text-sm transition cursor-pointer"
                            :class="props.selectedSlotId === slot.id
                                ? 'border-primary bg-primary/5'
                                : 'border-border hover:border-primary/50 hover:bg-muted/30'"
                            @click="slot.id ? emit('select', slot.id) : null"
                        >
                            Prateleira {{ slot.shelf_order }}
                        </button>
                    </div>
                </div>

                <!-- Single slot — show full label like before -->
                <button
                    v-else-if="group.slots[0]"
                    :key="`single-${group.slots[0].id}`"
                    type="button"
                    class="w-full rounded-md border px-3 py-2 text-left text-sm transition cursor-pointer"
                    :class="props.selectedSlotId === group.slots[0].id
                        ? 'border-primary bg-primary/5'
                        : 'border-border hover:border-primary/50 hover:bg-muted/30'"
                    @click="group.slots[0].id ? emit('select', group.slots[0].id) : null"
                >
                    Prateleira {{ group.slots[0].shelf_order }} — {{ group.categoryName }}
                </button>
            </template>

            <p v-if="props.slots.length === 0" class="text-sm text-muted-foreground">
                Nenhum slot criado para este módulo.
            </p>
        </div>
    </div>
</template>

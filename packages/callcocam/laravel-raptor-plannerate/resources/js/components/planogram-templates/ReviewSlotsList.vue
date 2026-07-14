<script setup lang="ts">
import { AlertCircle, Pencil } from 'lucide-vue-next';
import { computed } from 'vue';
import type { PlanogramTemplateSlot } from '@/components/planogram-templates/types';
import { useT } from '@/composables/useT';

const { t } = useT();

const props = defineProps<{
    slots: PlanogramTemplateSlot[];
    selectedSlotId: string | null;
}>();

const emit = defineEmits<{
    select: [slotId: string];
    edit: [slot: PlanogramTemplateSlot];
}>();

type SlotGroup = {
    categoryId: string | null;
    categoryName: string;
    categoryPath: string;
    slots: PlanogramTemplateSlot[];
};

const groupedSlots = computed((): SlotGroup[] => {
    const map = new Map<string, SlotGroup>();

    for (const slot of props.slots) {
        const key = slot.category_id ?? '__no_category__';

        if (!map.has(key)) {
            const noCategory = t('planogram-templates.review_list.no_category');
            map.set(key, {
                categoryId: slot.category_id,
                categoryName: slot.category_name ?? slot.category_id ?? noCategory,
                categoryPath: slot.category_path ?? slot.category_name ?? slot.category_id ?? noCategory,
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
        <p class="mb-3 text-sm font-semibold">{{ t('planogram-templates.review_list.title') }}</p>

        <div class="space-y-3">
            <template v-for="group in groupedSlots" :key="group.categoryId ?? '__no_category__'">
                <!-- Group header (when multiple shelves share the same category) -->
                <div v-if="group.slots.length > 1">
                    <p class="mb-1 flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                        <AlertCircle v-if="!group.categoryId" class="size-3 text-amber-500 shrink-0" />
                        {{ group.categoryPath }}
                        <span class="font-normal">({{ t('planogram-templates.review_list.shelves_count', { count: String(group.slots.length) }) }})</span>
                    </p>
                    <div class="space-y-1 pl-2 border-l-2 border-border">
                        <div
                            v-for="slot in group.slots"
                            :key="slot.id"
                            class="flex items-center gap-1"
                        >
                            <button
                                type="button"
                                class="flex-1 rounded-md border px-3 py-2 text-left text-sm transition cursor-pointer"
                                :class="props.selectedSlotId === slot.id
                                    ? 'border-primary bg-primary/5'
                                    : !slot.category_id
                                        ? 'border-amber-300 bg-amber-50/50 hover:border-amber-400 dark:border-amber-700 dark:bg-amber-950/20'
                                        : 'border-border hover:border-primary/50 hover:bg-muted/30'"
                                @click="slot.id ? emit('select', slot.id) : null"
                            >
                                {{ t('planogram-templates.review_list.shelf_label', { n: String(slot.shelf_order) }) }}
                            </button>
                            <button
                                type="button"
                                class="shrink-0 rounded-md border p-2 text-muted-foreground transition hover:border-primary/50 hover:text-foreground cursor-pointer"
                                :class="!slot.category_id ? 'border-amber-300 dark:border-amber-700' : 'border-border'"
                                :title="t('planogram-templates.review_list.edit_slot_tooltip')"
                                @click="emit('edit', slot)"
                            >
                                <Pencil class="size-3.5" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Single slot — show full label like before -->
                <div
                    v-else-if="group.slots[0]"
                    :key="`single-${group.slots[0].id}`"
                    class="flex items-center gap-1"
                >
                    <button
                        type="button"
                        class="flex-1 rounded-md border px-3 py-2 text-left text-sm transition cursor-pointer"
                        :class="props.selectedSlotId === group.slots[0].id
                            ? 'border-primary bg-primary/5'
                            : !group.slots[0].category_id
                                ? 'border-amber-300 bg-amber-50/50 hover:border-amber-400 dark:border-amber-700 dark:bg-amber-950/20'
                                : 'border-border hover:border-primary/50 hover:bg-muted/30'"
                        @click="group.slots[0].id ? emit('select', group.slots[0].id) : null"
                    >
                        <span class="flex items-center gap-1.5">
                            <AlertCircle v-if="!group.slots[0].category_id" class="size-3.5 text-amber-500 shrink-0" />
                            {{ t('planogram-templates.review_list.shelf_label', { n: String(group.slots[0].shelf_order) }) }} — {{ group.categoryPath }}
                        </span>
                    </button>
                    <button
                        type="button"
                        class="shrink-0 rounded-md border p-2 text-muted-foreground transition hover:border-primary/50 hover:text-foreground cursor-pointer"
                        :class="!group.slots[0].category_id ? 'border-amber-300 dark:border-amber-700' : 'border-border'"
                        :title="t('planogram-templates.review_list.edit_slot_tooltip')"
                        @click="emit('edit', group.slots[0])"
                    >
                        <Pencil class="size-3.5" />
                    </button>
                </div>
            </template>

            <p v-if="props.slots.length === 0" class="text-sm text-muted-foreground">
                {{ t('planogram-templates.review_list.empty_message') }}
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useT } from '@/composables/useT';
import type { SlotDraft } from './slot-editor';
import SlotEditorFields from './SlotEditorFields.vue';
import type { PlanogramSlotDefaults, PlanogramTemplateSlot } from './types';
import { validateSlotDraft } from './validation';
import type { SlotValidationErrors } from './validation';

const props = defineProps<{
    open: boolean;
    moduleNumber: number;
    shelfOrder: number;
    templateSlot?: PlanogramTemplateSlot | null;
    slotDefaults?: PlanogramSlotDefaults | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    save: [slot: SlotDraft];
}>();

const draft = reactive<SlotDraft>({
    module_number: props.moduleNumber,
    shelf_order: props.shelfOrder,
    category_id: null,
    min_facings: 1,
    max_facings: 5,
    priority: 1,
    price_order: 'none',
    size_order: 'none',
    brand_exposure: 'horizontal',
    flavor_exposure: 'horizontal',
    space_fallback: 'reduce_c',
    use_target_stock: false,
    facing_expansion: 'none',
    role_override: null,
});

const errors = ref<SlotValidationErrors>({});

watch(
    () =>
        [
            props.open,
            props.templateSlot,
            props.moduleNumber,
            props.shelfOrder,
        ] as const,
    ([open, slot, module, shelf]) => {
        if (!open) {
            return;
        }

        errors.value = {};
        draft.module_number = module;
        draft.shelf_order = shelf;
        draft.category_id = slot?.category_id ?? props.slotDefaults?.category_id ?? null;
        draft.min_facings = slot?.min_facings ?? props.slotDefaults?.min_facings ?? 1;
        draft.max_facings = slot?.max_facings ?? props.slotDefaults?.max_facings ?? 5;
        draft.priority = slot?.priority ?? props.slotDefaults?.priority ?? 1;
        draft.price_order = slot?.price_order ?? props.slotDefaults?.price_order ?? 'none';
        draft.size_order = slot?.size_order ?? props.slotDefaults?.size_order ?? 'none';
        draft.brand_exposure = slot?.brand_exposure ?? props.slotDefaults?.brand_exposure ?? 'horizontal';
        draft.flavor_exposure = slot?.flavor_exposure ?? props.slotDefaults?.flavor_exposure ?? 'horizontal';
        draft.space_fallback = slot?.space_fallback ?? props.slotDefaults?.space_fallback ?? 'reduce_c';
        draft.use_target_stock = slot?.use_target_stock ?? props.slotDefaults?.use_target_stock ?? false;
        draft.facing_expansion = slot?.facing_expansion ?? props.slotDefaults?.facing_expansion ?? 'none';
        draft.role_override = slot?.role_override ?? null;
    },
    { immediate: true },
);

const { t } = useT();

function saveSlot(): void {
    const result = validateSlotDraft(draft);

    if (Object.keys(result).length > 0) {
        errors.value = result;

        return;
    }

    errors.value = {};
    emit('save', { ...draft });
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[80vh] w-[95vw] max-w-[95vw] sm:max-w-6xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle
                    >{{ t('planogram-templates.slot_editor.title') }} —
                    {{ t('planogram-templates.slot_editor.module')
                    }}{{ moduleNumber }},
                    {{ t('planogram-templates.slot_editor.shelf')
                    }}{{ shelfOrder }}</DialogTitle
                >
            </DialogHeader>

            <SlotEditorFields v-model:draft="draft" :errors="errors" />

            <DialogFooter>
                <Button variant="ghost" @click="emit('update:open', false)">{{
                    t('planogram-templates.slot_editor.cancel_button')
                }}</Button>
                <Button @click="saveSlot">{{
                    t('planogram-templates.slot_editor.save_button')
                }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

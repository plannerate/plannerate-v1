<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import FormSelectField from '@/components/form/FormSelectField.vue';
import FormSwitchField from '@/components/form/FormSwitchField.vue';
import FormTextField from '@/components/form/FormTextField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useT } from '@/composables/useT';
import type { PlanogramSlotDefaults, PlanogramTemplateSlot } from './types';

type ModuleDefaultsDraft = {
    min_facings: number;
    priority: number;
    price_order: PlanogramTemplateSlot['price_order'];
    size_order: PlanogramTemplateSlot['size_order'];
    brand_exposure: PlanogramTemplateSlot['brand_exposure'];
    flavor_exposure: PlanogramTemplateSlot['flavor_exposure'];
    space_fallback: PlanogramTemplateSlot['space_fallback'];
    use_target_stock: boolean;
};

const props = defineProps<{
    open: boolean;
    moduleNumber: number;
    slotDefaults?: PlanogramSlotDefaults | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    save: [defaults: ModuleDefaultsDraft];
}>();

const draft = reactive<ModuleDefaultsDraft>({
    min_facings: 1,
    priority: 1,
    price_order: 'none',
    size_order: 'none',
    brand_exposure: 'horizontal',
    flavor_exposure: 'horizontal',
    space_fallback: 'reduce_c',
    use_target_stock: false,
});

watch(
    () => [props.open, props.slotDefaults] as const,
    ([open, defaults]) => {
        if (!open) {
            return;
        }

        draft.min_facings = defaults?.min_facings ?? 1;
        draft.priority = defaults?.priority ?? 1;
        draft.price_order = defaults?.price_order ?? 'none';
        draft.size_order = defaults?.size_order ?? 'none';
        draft.brand_exposure = defaults?.brand_exposure ?? 'horizontal';
        draft.flavor_exposure = defaults?.flavor_exposure ?? 'horizontal';
        draft.space_fallback = defaults?.space_fallback ?? 'reduce_c';
        draft.use_target_stock = defaults?.use_target_stock ?? false;
    },
    { immediate: true },
);

const { t } = useT();

const minFacingsModel = computed({
    get: () => draft.min_facings,
    set: (value: string | number) => {
        const parsed = Number(value);
        draft.min_facings = Number.isFinite(parsed) ? parsed : 1;
    },
});

const priorityModel = computed({
    get: () => draft.priority,
    set: (value: string | number) => {
        const parsed = Number(value);
        draft.priority = Number.isFinite(parsed) ? parsed : 1;
    },
});

function saveDefaults(): void {
    emit('save', { ...draft });
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[90vh] max-w-2xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>
                    Configuração padrão — Módulo {{ moduleNumber }}
                </DialogTitle>
            </DialogHeader>

            <div class="grid gap-5 py-2">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormTextField
                        id="module-default-min-facings"
                        v-model="minFacingsModel"
                        name="min_facings"
                        type="number"
                        :label="t('planogram-templates.slot_editor.min_facings_label')"
                        :min="1"
                        :max="20"
                    />
                    <FormTextField
                        id="module-default-priority"
                        v-model="priorityModel"
                        name="priority"
                        type="number"
                        :label="t('planogram-templates.slot_editor.priority_label')"
                        :hint="t('planogram-templates.slot_editor.priority_hint')"
                        :min="1"
                        :max="10"
                    />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormSelectField
                        id="module-default-price-order"
                        v-model="draft.price_order"
                        name="price_order"
                        :label="t('planogram-templates.slot_editor.price_order_label')"
                    >
                        <option value="none">{{ t('planogram-templates.slot_editor.price_order_options.none') }}</option>
                        <option value="asc">{{ t('planogram-templates.slot_editor.price_order_options.asc') }}</option>
                        <option value="desc">{{ t('planogram-templates.slot_editor.price_order_options.desc') }}</option>
                    </FormSelectField>
                    <FormSelectField
                        id="module-default-size-order"
                        v-model="draft.size_order"
                        name="size_order"
                        :label="t('planogram-templates.slot_editor.size_order_label')"
                    >
                        <option value="none">{{ t('planogram-templates.slot_editor.size_order_options.none') }}</option>
                        <option value="asc">{{ t('planogram-templates.slot_editor.size_order_options.asc') }}</option>
                        <option value="desc">{{ t('planogram-templates.slot_editor.size_order_options.desc') }}</option>
                    </FormSelectField>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormSelectField
                        id="module-default-brand-exposure"
                        v-model="draft.brand_exposure"
                        name="brand_exposure"
                        :label="t('planogram-templates.slot_editor.brand_exposure_label')"
                    >
                        <option value="vertical">{{ t('planogram-templates.slot_editor.exposure_options.vertical') }}</option>
                        <option value="horizontal">{{ t('planogram-templates.slot_editor.exposure_options.horizontal') }}</option>
                        <option value="mixed">{{ t('planogram-templates.slot_editor.exposure_options.mixed') }}</option>
                    </FormSelectField>
                    <FormSelectField
                        id="module-default-flavor-exposure"
                        v-model="draft.flavor_exposure"
                        name="flavor_exposure"
                        :label="t('planogram-templates.slot_editor.flavor_exposure_label')"
                    >
                        <option value="vertical">{{ t('planogram-templates.slot_editor.exposure_options.vertical') }}</option>
                        <option value="horizontal">{{ t('planogram-templates.slot_editor.exposure_options.horizontal') }}</option>
                        <option value="mixed">{{ t('planogram-templates.slot_editor.exposure_options.mixed') }}</option>
                    </FormSelectField>
                </div>

                <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-2">
                    <FormSelectField
                        id="module-default-space-fallback"
                        v-model="draft.space_fallback"
                        name="space_fallback"
                        :label="t('planogram-templates.slot_editor.space_fallback_label')"
                    >
                        <option value="reduce_c">{{ t('planogram-templates.slot_editor.space_fallback_options.reduce_c') }}</option>
                        <option value="reduce_facings">{{ t('planogram-templates.slot_editor.space_fallback_options.reduce_facings') }}</option>
                        <option value="skip">{{ t('planogram-templates.slot_editor.space_fallback_options.skip') }}</option>
                    </FormSelectField>
                    <FormSwitchField
                        id="module-default-target-stock"
                        v-model="draft.use_target_stock"
                        name="use_target_stock"
                        :label="t('planogram-templates.slot_editor.target_stock_label')"
                    />
                </div>
            </div>

            <DialogFooter>
                <Button variant="ghost" @click="emit('update:open', false)">{{
                    t('planogram-templates.slot_editor.cancel_button')
                }}</Button>
                <Button @click="saveDefaults">Salvar padrão</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

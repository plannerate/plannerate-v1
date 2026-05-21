<script setup lang="ts">
import { computed } from 'vue';
import FormSelectField from '@/components/form/FormSelectField.vue';
import FormSwitchField from '@/components/form/FormSwitchField.vue';
import FormTextField from '@/components/form/FormTextField.vue';
import CategoryCascadeSelect from '@/components/tenant/CategoryCascadeSelect.vue';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';
import { categoryRoleOptions } from './slot-editor';
import type { SlotDraft } from './slot-editor';
import type { SlotValidationErrors } from './validation';

const draft = defineModel<SlotDraft>('draft', { required: true });

defineProps<{
    errors: SlotValidationErrors;
}>();

const { t } = useT();

const minFacingsModel = computed({
    get: () => draft.value.min_facings,
    set: (value: string | number) => {
        const parsed = Number(value);
        draft.value.min_facings = Number.isFinite(parsed) ? parsed : 1;
    },
});

const maxFacingsModel = computed({
    get: () => draft.value.max_facings,
    set: (value: string | number) => {
        const parsed = Number(value);
        draft.value.max_facings = Number.isFinite(parsed) ? parsed : 5;
    },
});

const priorityModel = computed({
    get: () => draft.value.priority,
    set: (value: string | number) => {
        const parsed = Number(value);
        draft.value.priority = Number.isFinite(parsed) ? parsed : 1;
    },
});
</script>

<template>
    <div class="grid gap-5 py-2">
        <div class="flex flex-col gap-y-1.5">
            <Label class="text-sm font-medium">
                Categoria
                <span class="text-destructive">*</span>
            </Label>
            <p class="text-xs text-muted-foreground">
                Define quais produtos entram neste slot. Selecionar uma categoria pai inclui todos os produtos das subcategorias.
            </p>
            <CategoryCascadeSelect
                v-model="draft.category_id"
                :cascade-levels="5"
                :cols="2"
            />
            <p v-if="errors.category_id" class="text-xs text-destructive">
                {{ errors.category_id }}
            </p>
        </div>

        <div class="flex flex-col gap-y-1.5">
            <Label for="slot-role-override" class="text-sm font-medium">Papel da categoria</Label>
            <p class="text-xs text-muted-foreground">
                Orienta a posição macro e a estratégia do bloco. "Herdar da categoria" usa o papel configurado na categoria selecionada.
            </p>
            <select
                id="slot-role-override"
                v-model="draft.role_override"
                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            >
                <option v-for="opt in categoryRoleOptions" :key="opt.value" :value="opt.value || null">
                    {{ opt.label }}
                </option>
            </select>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="flex flex-col gap-y-1">
                <FormTextField
                    id="slot-min-facings"
                    v-model="minFacingsModel"
                    name="min_facings"
                    type="number"
                    :label="t('planogram-templates.slot_editor.min_facings_label')"
                    :min="1"
                    :max="20"
                />
                <p v-if="errors.min_facings" class="text-xs text-destructive">{{ errors.min_facings }}</p>
            </div>
            <div class="flex flex-col gap-y-1">
                <FormTextField
                    id="slot-max-facings"
                    v-model="maxFacingsModel"
                    name="max_facings"
                    type="number"
                    label="Frentes máximas"
                    hint="Teto de expansão por SKU"
                    :min="1"
                    :max="20"
                />
                <p v-if="errors.max_facings" class="text-xs text-destructive">{{ errors.max_facings }}</p>
            </div>
            <div class="flex flex-col gap-y-1">
                <FormTextField
                    id="slot-priority"
                    v-model="priorityModel"
                    name="priority"
                    type="number"
                    :label="t('planogram-templates.slot_editor.priority_label')"
                    :hint="t('planogram-templates.slot_editor.priority_hint')"
                    :min="1"
                    :max="10"
                />
                <p v-if="errors.priority" class="text-xs text-destructive">{{ errors.priority }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormSelectField
                id="slot-price-order"
                v-model="draft.price_order"
                name="price_order"
                :label="t('planogram-templates.slot_editor.price_order_label')"
            >
                <option value="none">{{ t('planogram-templates.slot_editor.price_order_options.none') }}</option>
                <option value="asc">{{ t('planogram-templates.slot_editor.price_order_options.asc') }}</option>
                <option value="desc">{{ t('planogram-templates.slot_editor.price_order_options.desc') }}</option>
            </FormSelectField>
            <FormSelectField
                id="slot-size-order"
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
                id="slot-brand-exposure"
                v-model="draft.brand_exposure"
                name="brand_exposure"
                :label="t('planogram-templates.slot_editor.brand_exposure_label')"
            >
                <option value="vertical">{{ t('planogram-templates.slot_editor.exposure_options.vertical') }}</option>
                <option value="horizontal">{{ t('planogram-templates.slot_editor.exposure_options.horizontal') }}</option>
                <option value="mixed">{{ t('planogram-templates.slot_editor.exposure_options.mixed') }}</option>
            </FormSelectField>
            <FormSelectField
                id="slot-flavor-exposure"
                v-model="draft.flavor_exposure"
                name="flavor_exposure"
                :label="t('planogram-templates.slot_editor.flavor_exposure_label')"
            >
                <option value="vertical">{{ t('planogram-templates.slot_editor.exposure_options.vertical') }}</option>
                <option value="horizontal">{{ t('planogram-templates.slot_editor.exposure_options.horizontal') }}</option>
                <option value="mixed">{{ t('planogram-templates.slot_editor.exposure_options.mixed') }}</option>
            </FormSelectField>
        </div>

        <FormSelectField
            id="slot-facing-expansion"
            v-model="draft.facing_expansion"
            name="facing_expansion"
            label="Expansão de frentes"
            hint="Como usar espaço livre acima do mínimo"
        >
            <option value="none">Não expandir</option>
            <option value="score">Por score ABC / vendas</option>
            <option value="current_stock">Por estoque atual</option>
            <option value="target_stock">Por déficit de estoque</option>
            <option value="equal">Distribuição igual</option>
        </FormSelectField>

        <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-2">
            <FormSelectField
                id="slot-space-fallback"
                v-model="draft.space_fallback"
                name="space_fallback"
                :label="t('planogram-templates.slot_editor.space_fallback_label')"
            >
                <option value="reduce_c">{{ t('planogram-templates.slot_editor.space_fallback_options.reduce_c') }}</option>
                <option value="reduce_facings">{{ t('planogram-templates.slot_editor.space_fallback_options.reduce_facings') }}</option>
                <option value="skip">{{ t('planogram-templates.slot_editor.space_fallback_options.skip') }}</option>
            </FormSelectField>
            <FormSwitchField
                id="slot-target-stock"
                v-model="draft.use_target_stock"
                name="use_target_stock"
                :label="t('planogram-templates.slot_editor.target_stock_label')"
            />
        </div>
    </div>
</template>

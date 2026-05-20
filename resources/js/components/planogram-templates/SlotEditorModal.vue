<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import CategoryCascadeSelect from '@/components/tenant/CategoryCascadeSelect.vue';
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
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';
import type { PlanogramSlotDefaults, PlanogramTemplateSlot } from './types';

type SlotDraft = {
    module_number: number;
    shelf_order: number;
    category_id: string | null;
    min_facings: number;
    max_facings: number;
    priority: number;
    price_order: PlanogramTemplateSlot['price_order'];
    size_order: PlanogramTemplateSlot['size_order'];
    brand_exposure: PlanogramTemplateSlot['brand_exposure'];
    flavor_exposure: PlanogramTemplateSlot['flavor_exposure'];
    space_fallback: PlanogramTemplateSlot['space_fallback'];
    use_target_stock: boolean;
    facing_expansion: PlanogramTemplateSlot['facing_expansion'];
};

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
});

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

const maxFacingsModel = computed({
    get: () => draft.max_facings,
    set: (value: string | number) => {
        const parsed = Number(value);
        draft.max_facings = Number.isFinite(parsed) ? parsed : 5;
    },
});

const priorityModel = computed({
    get: () => draft.priority,
    set: (value: string | number) => {
        const parsed = Number(value);
        draft.priority = Number.isFinite(parsed) ? parsed : 1;
    },
});

function saveSlot(): void {
    if (!draft.category_id) {
        return;
    }

    emit('save', { ...draft });
    emit('update:open', false);
}

</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[90vh] max-w-2xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle
                    >{{ t('planogram-templates.slot_editor.title') }} —
                    {{ t('planogram-templates.slot_editor.module')
                    }}{{ moduleNumber }},
                    {{ t('planogram-templates.slot_editor.shelf')
                    }}{{ shelfOrder }}</DialogTitle
                >
            </DialogHeader>

            <div class="grid gap-5 py-2">
                <!-- Categoria -->
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
                </div>

                <!-- Min facings / Max facings / Prioridade -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <FormTextField
                        id="slot-min-facings"
                        v-model="minFacingsModel"
                        name="min_facings"
                        type="number"
                        :label="
                            t(
                                'planogram-templates.slot_editor.min_facings_label',
                            )
                        "
                        :min="1"
                        :max="20"
                    />
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
                    <FormTextField
                        id="slot-priority"
                        v-model="priorityModel"
                        name="priority"
                        type="number"
                        :label="
                            t('planogram-templates.slot_editor.priority_label')
                        "
                        :hint="
                            t('planogram-templates.slot_editor.priority_hint')
                        "
                        :min="1"
                        :max="10"
                    />
                </div>

                <!-- Ordem por preço / tamanho -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormSelectField
                        id="slot-price-order"
                        v-model="draft.price_order"
                        name="price_order"
                        :label="
                            t(
                                'planogram-templates.slot_editor.price_order_label',
                            )
                        "
                    >
                        <option value="none">
                            {{
                                t(
                                    'planogram-templates.slot_editor.price_order_options.none',
                                )
                            }}
                        </option>
                        <option value="asc">
                            {{
                                t(
                                    'planogram-templates.slot_editor.price_order_options.asc',
                                )
                            }}
                        </option>
                        <option value="desc">
                            {{
                                t(
                                    'planogram-templates.slot_editor.price_order_options.desc',
                                )
                            }}
                        </option>
                    </FormSelectField>
                    <FormSelectField
                        id="slot-size-order"
                        v-model="draft.size_order"
                        name="size_order"
                        :label="
                            t(
                                'planogram-templates.slot_editor.size_order_label',
                            )
                        "
                    >
                        <option value="none">
                            {{
                                t(
                                    'planogram-templates.slot_editor.size_order_options.none',
                                )
                            }}
                        </option>
                        <option value="asc">
                            {{
                                t(
                                    'planogram-templates.slot_editor.size_order_options.asc',
                                )
                            }}
                        </option>
                        <option value="desc">
                            {{
                                t(
                                    'planogram-templates.slot_editor.size_order_options.desc',
                                )
                            }}
                        </option>
                    </FormSelectField>
                </div>

                <!-- Exposição marca / fragrância -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormSelectField
                        id="slot-brand-exposure"
                        v-model="draft.brand_exposure"
                        name="brand_exposure"
                        :label="
                            t(
                                'planogram-templates.slot_editor.brand_exposure_label',
                            )
                        "
                    >
                        <option value="vertical">
                            {{
                                t(
                                    'planogram-templates.slot_editor.exposure_options.vertical',
                                )
                            }}
                        </option>
                        <option value="horizontal">
                            {{
                                t(
                                    'planogram-templates.slot_editor.exposure_options.horizontal',
                                )
                            }}
                        </option>
                        <option value="mixed">
                            {{
                                t(
                                    'planogram-templates.slot_editor.exposure_options.mixed',
                                )
                            }}
                        </option>
                    </FormSelectField>
                    <FormSelectField
                        id="slot-flavor-exposure"
                        v-model="draft.flavor_exposure"
                        name="flavor_exposure"
                        :label="
                            t(
                                'planogram-templates.slot_editor.flavor_exposure_label',
                            )
                        "
                    >
                        <option value="vertical">
                            {{
                                t(
                                    'planogram-templates.slot_editor.exposure_options.vertical',
                                )
                            }}
                        </option>
                        <option value="horizontal">
                            {{
                                t(
                                    'planogram-templates.slot_editor.exposure_options.horizontal',
                                )
                            }}
                        </option>
                        <option value="mixed">
                            {{
                                t(
                                    'planogram-templates.slot_editor.exposure_options.mixed',
                                )
                            }}
                        </option>
                    </FormSelectField>
                </div>

                <!-- Expansão de frentes -->
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
                    <option value="equal">Distribuição igual</option>
                </FormSelectField>

                <!-- Se faltar espaço / Estoque alvo -->
                <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-2">
                    <FormSelectField
                        id="slot-space-fallback"
                        v-model="draft.space_fallback"
                        name="space_fallback"
                        :label="
                            t(
                                'planogram-templates.slot_editor.space_fallback_label',
                            )
                        "
                    >
                        <option value="reduce_c">
                            {{
                                t(
                                    'planogram-templates.slot_editor.space_fallback_options.reduce_c',
                                )
                            }}
                        </option>
                        <option value="reduce_facings">
                            {{
                                t(
                                    'planogram-templates.slot_editor.space_fallback_options.reduce_facings',
                                )
                            }}
                        </option>
                        <option value="skip">
                            {{
                                t(
                                    'planogram-templates.slot_editor.space_fallback_options.skip',
                                )
                            }}
                        </option>
                    </FormSelectField>
                    <FormSwitchField
                        id="slot-target-stock"
                        v-model="draft.use_target_stock"
                        name="use_target_stock"
                        :label="
                            t(
                                'planogram-templates.slot_editor.target_stock_label',
                            )
                        "
                    />
                </div>
            </div>

            <DialogFooter>
                <Button variant="ghost" @click="emit('update:open', false)">{{
                    t('planogram-templates.slot_editor.cancel_button')
                }}</Button>
                <Button :disabled="!draft.category_id" @click="saveSlot">{{
                    t('planogram-templates.slot_editor.save_button')
                }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

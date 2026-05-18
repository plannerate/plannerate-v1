<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import FormSelectField from '@/components/form/FormSelectField.vue';
import FormSwitchField from '@/components/form/FormSwitchField.vue';
import FormTextField from '@/components/form/FormTextField.vue';
import RemoteAutocompleteField from '@/components/form/RemoteAutocompleteField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useT } from '@/composables/useT';
import type { PlanogramTemplateSlot } from './types';

type SlotDraft = Omit<
    PlanogramTemplateSlot,
    | 'id'
    | 'subtemplate_id'
    | 'grouping_normalized'
    | 'ordering'
    | 'category'
    | 'subcategory'
> & {
    category: string;
    subcategory: string;
};

const props = defineProps<{
    open: boolean;
    moduleNumber: number;
    shelfOrder: number;
    templateSlot?: PlanogramTemplateSlot | null;
    groupingSearchUrl?: string | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    save: [slot: SlotDraft];
}>();

const draft = reactive<SlotDraft>({
    module_number: props.moduleNumber,
    shelf_order: props.shelfOrder,
    grouping: '',
    category: '',
    subcategory: '',
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
        draft.grouping = slot?.grouping ?? '';
        draft.category = slot?.category ?? '';
        draft.subcategory = slot?.subcategory ?? '';
        draft.min_facings = slot?.min_facings ?? 1;
        draft.priority = slot?.priority ?? 1;
        draft.price_order = slot?.price_order ?? 'none';
        draft.size_order = slot?.size_order ?? 'none';
        draft.brand_exposure = slot?.brand_exposure ?? 'horizontal';
        draft.flavor_exposure = slot?.flavor_exposure ?? 'horizontal';
        draft.space_fallback = slot?.space_fallback ?? 'reduce_c';
        draft.use_target_stock = slot?.use_target_stock ?? false;
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

function saveSlot(): void {
    if (!draft.grouping.trim()) {
        return;
    }

    emit('save', { ...draft });
    emit('update:open', false);
}

function applyGroupingHierarchy(grouping: string): void {
    const segments = grouping
        .split('|')
        .map((segment) => segment.trim())
        .filter((segment) => segment !== '');

    if (segments.length === 0) {
        draft.category = '';
        draft.subcategory = '';

        return;
    }

    if (segments.length === 1) {
        draft.category = segments[0];
        draft.subcategory = segments[0];

        return;
    }

    draft.category = segments[segments.length - 2];
    draft.subcategory = segments[segments.length - 1];
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
                <!-- Grouping -->
                <RemoteAutocompleteField
                    id="slot-grouping"
                    v-model="draft.grouping"
                    :label="t('planogram-templates.slot_editor.grouping_label')"
                    :search-url="groupingSearchUrl"
                    :placeholder="
                        t('planogram-templates.slot_editor.grouping_example')
                    "
                    :hint="t('planogram-templates.slot_editor.grouping_hint')"
                    :empty-text="'Nenhum sortiment_attribute encontrado.'"
                    required
                    @select="applyGroupingHierarchy"
                />

                <!-- Categoria / Subcategoria -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormTextField
                        id="slot-category"
                        v-model="draft.category"
                        name="category"
                        :label="
                            t('planogram-templates.slot_editor.category_label')
                        "
                        :placeholder="
                            t(
                                'planogram-templates.slot_editor.category_example',
                            )
                        "
                    />
                    <FormTextField
                        id="slot-subcategory"
                        v-model="draft.subcategory"
                        name="subcategory"
                        :label="
                            t(
                                'planogram-templates.slot_editor.subcategory_label',
                            )
                        "
                        :placeholder="
                            t(
                                'planogram-templates.slot_editor.subcategory_example',
                            )
                        "
                    />
                </div>

                <!-- Min facings / Prioridade -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                <Button :disabled="!draft.grouping.trim()" @click="saveSlot">{{
                    t('planogram-templates.slot_editor.save_button')
                }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

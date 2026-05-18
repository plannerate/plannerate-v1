<script setup lang="ts">
import { reactive, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useT } from '@/composables/useT';
import type { PlanogramTemplateSlot } from './types';

type SlotDraft = Omit<PlanogramTemplateSlot, 'id' | 'subtemplate_id' | 'grouping_normalized' | 'ordering'>;

const props = defineProps<{
    open: boolean;
    moduleNumber: number;
    shelfOrder: number;
    slot?: PlanogramTemplateSlot | null;
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
    () => [props.open, props.slot, props.moduleNumber, props.shelfOrder] as const,
    ([open, slot, module, shelf]) => {
        if (!open) return;
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

function saveSlot(): void {
    if (!draft.grouping.trim()) return;
    emit('save', { ...draft });
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[90vh] max-w-lg overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ t('planogram-templates.slot_editor.title') }} — {{ t('planogram-templates.slot_editor.module') }}{{ moduleNumber }}, {{ t('planogram-templates.slot_editor.shelf') }}{{ shelfOrder }}</DialogTitle>
            </DialogHeader>

            <div class="grid gap-4 py-2">
                <!-- Grouping -->
                <div class="grid gap-1.5">
                    <Label for="slot-grouping">{{ t('planogram-templates.slot_editor.grouping_label') }} {{ t('planogram-templates.slot_editor.grouping_required') }}</Label>
                    <Input
                        id="slot-grouping"
                        v-model="draft.grouping"
                        :placeholder="t('planogram-templates.slot_editor.grouping_example')"
                    />
                    <p class="text-xs text-muted-foreground">{{ t('planogram-templates.slot_editor.grouping_hint') }}</p>
                </div>

                <!-- Categoria / Subcategoria -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label for="slot-category">{{ t('planogram-templates.slot_editor.category_label') }}</Label>
                        <Input id="slot-category" v-model="draft.category" :placeholder="t('planogram-templates.slot_editor.category_example')" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="slot-subcategory">{{ t('planogram-templates.slot_editor.subcategory_label') }}</Label>
                        <Input id="slot-subcategory" v-model="draft.subcategory" :placeholder="t('planogram-templates.slot_editor.subcategory_example')" />
                    </div>
                </div>

                <!-- Min facings / Prioridade -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label for="slot-min-facings">{{ t('planogram-templates.slot_editor.min_facings_label') }}</Label>
                        <Input
                            id="slot-min-facings"
                            v-model.number="draft.min_facings"
                            type="number"
                            :min="1"
                            :max="20"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="slot-priority">{{ t('planogram-templates.slot_editor.priority_label') }}</Label>
                        <Input
                            id="slot-priority"
                            v-model.number="draft.priority"
                            type="number"
                            :min="1"
                            :max="10"
                        />
                        <p class="text-xs text-muted-foreground">{{ t('planogram-templates.slot_editor.priority_hint') }}</p>
                    </div>
                </div>

                <!-- Ordem por preço / tamanho -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label>{{ t('planogram-templates.slot_editor.price_order_label') }}</Label>
                        <Select v-model="draft.price_order">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">{{ t('planogram-templates.slot_editor.price_order_options.none') }}</SelectItem>
                                <SelectItem value="asc">{{ t('planogram-templates.slot_editor.price_order_options.asc') }}</SelectItem>
                                <SelectItem value="desc">{{ t('planogram-templates.slot_editor.price_order_options.desc') }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>{{ t('planogram-templates.slot_editor.size_order_label') }}</Label>
                        <Select v-model="draft.size_order">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">{{ t('planogram-templates.slot_editor.size_order_options.none') }}</SelectItem>
                                <SelectItem value="asc">{{ t('planogram-templates.slot_editor.size_order_options.asc') }}</SelectItem>
                                <SelectItem value="desc">{{ t('planogram-templates.slot_editor.size_order_options.desc') }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <!-- Exposição marca / fragrância -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label>{{ t('planogram-templates.slot_editor.brand_exposure_label') }}</Label>
                        <Select v-model="draft.brand_exposure">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="vertical">{{ t('planogram-templates.slot_editor.exposure_options.vertical') }}</SelectItem>
                                <SelectItem value="horizontal">{{ t('planogram-templates.slot_editor.exposure_options.horizontal') }}</SelectItem>
                                <SelectItem value="mixed">{{ t('planogram-templates.slot_editor.exposure_options.mixed') }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>{{ t('planogram-templates.slot_editor.flavor_exposure_label') }}</Label>
                        <Select v-model="draft.flavor_exposure">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="vertical">{{ t('planogram-templates.slot_editor.exposure_options.vertical') }}</SelectItem>
                                <SelectItem value="horizontal">{{ t('planogram-templates.slot_editor.exposure_options.horizontal') }}</SelectItem>
                                <SelectItem value="mixed">{{ t('planogram-templates.slot_editor.exposure_options.mixed') }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <!-- Se faltar espaço / Estoque alvo -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5 w-full">
                        <Label>{{ t('planogram-templates.slot_editor.space_fallback_label') }}</Label>
                        <Select v-model="draft.space_fallback">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="reduce_c">{{ t('planogram-templates.slot_editor.space_fallback_options.reduce_c') }}</SelectItem>
                                <SelectItem value="reduce_facings">{{ t('planogram-templates.slot_editor.space_fallback_options.reduce_facings') }}</SelectItem>
                                <SelectItem value="skip">{{ t('planogram-templates.slot_editor.space_fallback_options.skip') }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex items-end gap-3 pb-1">
                        <Switch
                            id="slot-target-stock"
                            :checked="draft.use_target_stock"
                            @update:checked="draft.use_target_stock = $event"
                        />
                        <Label for="slot-target-stock" class="cursor-pointer">{{ t('planogram-templates.slot_editor.target_stock_label') }}</Label>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button variant="ghost" @click="emit('update:open', false)">{{ t('planogram-templates.slot_editor.cancel_button') }}</Button>
                <Button :disabled="!draft.grouping.trim()" @click="saveSlot">{{ t('planogram-templates.slot_editor.save_button') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

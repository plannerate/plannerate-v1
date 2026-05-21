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
import { useT } from '@/composables/useT';
import type { FlowDirection, PlanogramSlotDefaults, PlanogramTemplateSlot, ZonePriority } from './types';

type ModuleDefaultsDraft = {
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
    hot_zone_priority: ZonePriority | null;
    cold_zone_priority: ZonePriority | null;
    flow_direction: FlowDirection | null;
};

const props = defineProps<{
    open: boolean;
    moduleNumber: number;
    slotDefaults?: PlanogramSlotDefaults | null;
    hotZonePriority?: ZonePriority | null;
    coldZonePriority?: ZonePriority | null;
    flowDirection?: FlowDirection | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    save: [defaults: ModuleDefaultsDraft];
}>();

const draft = reactive<ModuleDefaultsDraft>({
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
    hot_zone_priority: null,
    cold_zone_priority: null,
    flow_direction: null,
});

watch(
    () => [props.open, props.slotDefaults, props.hotZonePriority, props.coldZonePriority] as const,
    ([open, defaults, hotPriority, coldPriority]) => {
        if (!open) {
            return;
        }

        draft.min_facings = defaults?.min_facings ?? 1;
        draft.max_facings = defaults?.max_facings ?? 5;
        draft.category_id = defaults?.category_id ?? null;
        draft.priority = defaults?.priority ?? 1;
        draft.price_order = defaults?.price_order ?? 'none';
        draft.size_order = defaults?.size_order ?? 'none';
        draft.brand_exposure = defaults?.brand_exposure ?? 'horizontal';
        draft.flavor_exposure = defaults?.flavor_exposure ?? 'horizontal';
        draft.space_fallback = defaults?.space_fallback ?? 'reduce_c';
        draft.use_target_stock = defaults?.use_target_stock ?? false;
        draft.facing_expansion = defaults?.facing_expansion ?? 'none';
        draft.hot_zone_priority = hotPriority ?? null;
        draft.cold_zone_priority = coldPriority ?? null;
        draft.flow_direction = props.flowDirection ?? null;
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
                <div class="flex flex-col gap-y-1.5">
                    <span class="text-sm font-medium">Categoria padrão</span>
                    <p class="text-xs text-muted-foreground">
                        Novos slots deste módulo já abrem com essa categoria selecionada.
                    </p>
                    <CategoryCascadeSelect
                        v-model="draft.category_id"
                        :cascade-levels="5"
                        :cols="2"
                    />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
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
                        id="module-default-max-facings"
                        v-model="maxFacingsModel"
                        name="max_facings"
                        type="number"
                        label="Frentes máximas"
                        hint="Teto de expansão por SKU"
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

                <FormSelectField
                    id="module-default-facing-expansion"
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

                <!-- Sentido de leitura do cliente -->
                <div class="rounded-md border border-border p-3">
                    <p class="mb-3 text-sm font-medium">Sentido de leitura</p>
                    <p class="mb-3 text-xs text-muted-foreground">
                        Define a direção do fluxo do cliente na frente da gôndola. Afeta a posição física dos
                        produtos: "preço crescente no fluxo" coloca o produto mais barato no início do fluxo.
                    </p>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="flex items-center gap-1.5 rounded-md border px-3 py-2 text-sm transition-colors"
                            :class="
                                !draft.flow_direction || draft.flow_direction === 'left_to_right'
                                    ? 'border-primary bg-primary/10 text-primary font-medium'
                                    : 'border-border text-muted-foreground hover:border-primary/50'
                            "
                            @click="draft.flow_direction = 'left_to_right'"
                        >
                            <span>→</span> Esquerda → Direita <span class="ml-1 text-xs opacity-60">(padrão)</span>
                        </button>
                        <button
                            type="button"
                            class="flex items-center gap-1.5 rounded-md border px-3 py-2 text-sm transition-colors"
                            :class="
                                draft.flow_direction === 'right_to_left'
                                    ? 'border-primary bg-primary/10 text-primary font-medium'
                                    : 'border-border text-muted-foreground hover:border-primary/50'
                            "
                            @click="draft.flow_direction = 'right_to_left'"
                        >
                            <span>←</span> Direita → Esquerda
                        </button>
                    </div>
                </div>

                <!-- Priorização por zona térmica -->
                <div class="rounded-md border border-border p-3">
                    <p class="mb-3 text-sm font-medium">Priorização por zona térmica</p>
                    <p class="mb-3 text-xs text-muted-foreground">
                        Define qual critério de ordenação é aplicado aos produtos em prateleiras quentes (olhos / mãos)
                        e frias (alta / chão). Não filtra produtos — apenas reordena dentro do slot.
                    </p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormSelectField
                            id="module-default-hot-zone-priority"
                            v-model="draft.hot_zone_priority"
                            name="hot_zone_priority"
                            label="Zona quente (olhos + mãos)"
                            hint="Eye + Hand: área nobre da gôndola"
                        >
                            <option :value="null">Sem critério (padrão)</option>
                            <option value="maior_margem">Maior margem</option>
                            <option value="maior_giro">Maior giro (vendas)</option>
                            <option value="maior_valor_vendido">Maior valor vendido</option>
                            <option value="curva_a">Curva A primeiro</option>
                        </FormSelectField>
                        <FormSelectField
                            id="module-default-cold-zone-priority"
                            v-model="draft.cold_zone_priority"
                            name="cold_zone_priority"
                            label="Zona fria (alto + chão)"
                            hint="High + Low: área de menor visibilidade"
                        >
                            <option :value="null">Sem critério (padrão)</option>
                            <option value="menor_margem">Menor margem</option>
                            <option value="complementar_fria">Complementar / sazonais</option>
                            <option value="maior_volume">Maior volume físico</option>
                            <option value="menor_prioridade">Menor prioridade geral</option>
                        </FormSelectField>
                    </div>
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

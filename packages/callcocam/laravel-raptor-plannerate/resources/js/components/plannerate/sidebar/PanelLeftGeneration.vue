<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowUpDown, ChevronDown, ChevronRight, LayoutGrid, RefreshCw, RotateCcw, Send, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { reorderGondola, redistributeGondola } from '@/actions/App/Http/Controllers/AutoPlanogramController';

const overrideUrl = (gondolaId: string) => `/api/gondolas/${gondolaId}/generation-overrides`;
const overrideCategoryUrl = (gondolaId: string, categoryId: string) =>
    `/api/gondolas/${gondolaId}/generation-overrides/${categoryId}`;
const applyToTemplateUrl = (gondolaId: string, categoryId: string) =>
    `/api/gondolas/${gondolaId}/generation-overrides/${categoryId}/apply-to-template`;
import FormSelectField from '@/components/form/FormSelectField.vue';
import FormSwitchField from '@/components/form/FormSwitchField.vue';
import FormTextField from '@/components/form/FormTextField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useT } from '@/composables/useT';
import type { Gondola, GondolaSlotOverride } from '../../../types/planogram';

interface Props {
    open: boolean;
    gondola: Gondola;
}

interface Emits {
    (e: 'close'): void;
    (e: 'reload'): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();
const { t } = useT();

const modeLabel: Record<string, string> = {
    template: 'Template',
    automatic: 'Automático',
    manual: 'Manual',
};

const modeVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
    template: 'default',
    automatic: 'secondary',
    manual: 'outline',
};

const currentMode = props.gondola.generation_mode ?? 'manual';
const isReordering = ref(false);
const isRedistributing = ref(false);

interface CategoryInfo {
    category_id: string;
    category_name: string;
    slot_count: number;
    templateDefaults: Partial<GondolaSlotOverride>;
}

/** Categorias únicas encontradas nos template_slot das prateleiras desta gôndola, com defaults do template */
const categories = computed((): CategoryInfo[] => {
    const seen = new Map<string, CategoryInfo>();

    for (const section of props.gondola.sections ?? []) {
        for (const shelf of section.shelves ?? []) {
            const slot = (shelf as any).template_slot;

            if (!slot?.category_id) {
                continue;
            }

            const existing = seen.get(slot.category_id);

            if (existing) {
                existing.slot_count++;
            } else {
                seen.set(slot.category_id, {
                    category_id: slot.category_id,
                    category_name: slot.category_name ?? slot.category_id,
                    slot_count: 1,
                    templateDefaults: {
                        min_facings: slot.min_facings ?? null,
                        max_facings: slot.max_facings ?? null,
                        price_order: slot.price_order ?? null,
                        size_order: slot.size_order ?? null,
                        brand_exposure: slot.brand_exposure ?? null,
                        flavor_exposure: slot.flavor_exposure ?? null,
                        space_fallback: slot.space_fallback ?? null,
                        facing_expansion: slot.facing_expansion ?? null,
                        use_target_stock: slot.use_target_stock ?? false,
                    },
                });
            }
        }
    }

    return Array.from(seen.values());
});

/** Mapa de overrides salvos indexados por category_id */
const savedOverrides = ref<Record<string, GondolaSlotOverride>>(
    Object.fromEntries(
        (props.gondola.generation_overrides ?? [])
            .filter((o) => o.category_id !== null)
            .map((o) => [o.category_id as string, o]),
    ),
);

/** Estado local em edição, por category_id */
const localEdits = ref<Record<string, Partial<GondolaSlotOverride>>>({});

/** Categorias com accordion aberto */
const openCategories = ref<Set<string>>(new Set());

function templateDefaults(categoryId: string): Partial<GondolaSlotOverride> {
    return categories.value.find((c) => c.category_id === categoryId)?.templateDefaults ?? {};
}

function toggleCategory(categoryId: string) {
    if (openCategories.value.has(categoryId)) {
        openCategories.value.delete(categoryId);
    } else {
        openCategories.value.add(categoryId);

        if (!localEdits.value[categoryId]) {
            const saved = savedOverrides.value[categoryId];
            localEdits.value[categoryId] = saved
                ? { ...saved }
                : { category_id: categoryId, ...templateDefaults(categoryId) };
        }
    }
}

function getEdit(categoryId: string): Partial<GondolaSlotOverride> {
    if (!localEdits.value[categoryId]) {
        const saved = savedOverrides.value[categoryId];
        localEdits.value[categoryId] = saved
            ? { ...saved }
            : { category_id: categoryId, ...templateDefaults(categoryId) };
    }

    return localEdits.value[categoryId]!;
}

function setEditField(categoryId: string, field: keyof GondolaSlotOverride, value: any) {
    if (!localEdits.value[categoryId]) {
        localEdits.value[categoryId] = { category_id: categoryId };
    }
    (localEdits.value[categoryId] as any)[field] = value || null;
}

function hasOverride(categoryId: string): boolean {
    return !!savedOverrides.value[categoryId];
}

const savingCategory = ref<string | null>(null);
const applyingCategory = ref<string | null>(null);
const resettingCategory = ref<string | null>(null);

/** Sincroniza savedOverrides e localEdits quando Inertia recarrega o prop gondola */
watch(
    () => props.gondola.generation_overrides,
    (overrides) => {
        savedOverrides.value = Object.fromEntries(
            (overrides ?? [])
                .filter((o) => o.category_id !== null)
                .map((o) => [o.category_id as string, o]),
        );

        for (const categoryId of openCategories.value) {
            const saved = savedOverrides.value[categoryId];
            localEdits.value[categoryId] = saved
                ? { ...saved }
                : { category_id: categoryId, ...templateDefaults(categoryId) };
        }
    },
    { deep: true },
);

function handleSave(categoryId: string) {
    savingCategory.value = categoryId;
    const payload = { ...getEdit(categoryId), category_id: categoryId };

    router.put(overrideUrl(props.gondola.id), payload, {
        preserveState: true,
        preserveScroll: true,
        only: ['record'],
        onSuccess: () => toast.success('Configuração salva localmente.'),
        onError: () => toast.error('Erro ao salvar configuração.'),
        onFinish: () => { savingCategory.value = null; },
    });
}

function handleApplyToTemplate(categoryId: string) {
    applyingCategory.value = categoryId;

    router.post(applyToTemplateUrl(props.gondola.id, categoryId), {}, {
        preserveState: true,
        preserveScroll: true,
        only: ['record'],
        onSuccess: () => toast.success('Configuração aplicada ao template.'),
        onError: (errors) => toast.error(errors.message ?? 'Erro ao aplicar ao template.'),
        onFinish: () => { applyingCategory.value = null; },
    });
}

function handleReset(categoryId: string) {
    resettingCategory.value = categoryId;

    router.delete(overrideCategoryUrl(props.gondola.id, categoryId), {
        preserveState: true,
        preserveScroll: true,
        only: ['record'],
        onSuccess: () => toast.success('Override removido. Usando configuração do template.'),
        onError: () => toast.error('Erro ao resetar configuração.'),
        onFinish: () => { resettingCategory.value = null; },
    });
}

async function handleReorder() {
    isReordering.value = true;
    try {
        await axios.post(reorderGondola.url(props.gondola.id));
        toast.success(t('plannerate.sidebar.generation.reorder.success'));
        router.reload({ only: ['record'] });
    } catch {
        toast.error(t('plannerate.sidebar.generation.reorder.error'));
    } finally {
        isReordering.value = false;
    }
}

async function handleRedistribute() {
    isRedistributing.value = true;
    try {
        await axios.post(redistributeGondola.url(props.gondola.id));
        toast.success(t('plannerate.sidebar.generation.redistribute.success'));
        router.reload({ only: ['record'] });
    } catch {
        toast.error(t('plannerate.sidebar.generation.redistribute.error'));
    } finally {
        isRedistributing.value = false;
    }
}

</script>

<template>
    <Transition
        enter-active-class="transition-transform duration-300 ease-out"
        enter-from-class="-translate-x-full"
        enter-to-class="translate-x-0"
        leave-active-class="transition-transform duration-300 ease-in"
        leave-from-class="translate-x-0"
        leave-to-class="-translate-x-full"
    >
        <div
            v-if="open"
            class="relative z-20 flex h-full w-full sm:w-80 2xl:w-96 flex-col border-r border-border bg-background"
        >
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-border px-4 py-3">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium">{{ t('plannerate.sidebar.generation.title') }}</span>
                    <Badge :variant="modeVariant[currentMode] ?? 'outline'">
                        {{ modeLabel[currentMode] ?? currentMode }}
                    </Badge>
                </div>
                <Button variant="ghost" size="icon" class="size-7 shrink-0" @click="$emit('close')" type="button">
                    <X class="size-4" />
                </Button>
            </div>

            <!-- Template info -->
            <div v-if="gondola.template_id" class="border-b border-border bg-muted/30 px-4 py-2">
                <p class="text-xs text-muted-foreground">{{ t('plannerate.sidebar.generation.template_in_use') }}</p>
                <p class="mt-0.5 text-xs font-medium text-foreground">{{ gondola.template_id }}</p>
            </div>

            <!-- Content -->
            <div class="flex flex-1 flex-col gap-3 overflow-y-auto p-4">

                <!-- Configurações por categoria -->
                <div v-if="categories.length > 0">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        Configurações por Categoria
                    </p>

                    <div class="space-y-2">
                        <div
                            v-for="cat in categories"
                            :key="cat.category_id"
                            class="rounded-lg border border-border bg-background shadow-sm"
                        >
                            <!-- Accordion header -->
                            <button
                                type="button"
                                class="flex w-full items-center justify-between px-3 py-2.5 text-left"
                                @click="toggleCategory(cat.category_id)"
                            >
                                <div class="flex items-center gap-2 min-w-0">
                                    <component
                                        :is="openCategories.has(cat.category_id) ? ChevronDown : ChevronRight"
                                        class="size-3.5 shrink-0 text-muted-foreground"
                                    />
                                    <span class="truncate text-sm font-medium">{{ cat.category_name }}</span>
                                    <span class="shrink-0 text-[10px] text-muted-foreground">({{ cat.slot_count }})</span>
                                </div>
                                <span
                                    v-if="hasOverride(cat.category_id)"
                                    class="ml-2 shrink-0 size-2 rounded-full bg-amber-500"
                                    title="Override local ativo"
                                />
                            </button>

                            <!-- Accordion body -->
                            <div v-if="openCategories.has(cat.category_id)" class="border-t border-border px-3 pb-3 pt-2.5 space-y-3">

                                <!-- Frentes mín / máx -->
                                <div class="grid grid-cols-2 gap-2">
                                    <FormTextField
                                        :id="`min-facings-${cat.category_id}`"
                                        name="min_facings"
                                        type="number"
                                        :min="1"
                                        :max="255"
                                        :label="t('planogram-templates.slot_editor.min_facings_label')"
                                        :model-value="getEdit(cat.category_id).min_facings ?? ''"
                                        @update:model-value="(v) => setEditField(cat.category_id, 'min_facings', v ? Number(v) : null)"
                                    />
                                    <FormTextField
                                        :id="`max-facings-${cat.category_id}`"
                                        name="max_facings"
                                        type="number"
                                        :min="1"
                                        :max="255"
                                        :label="t('planogram-templates.slot_editor_fields.facings.max_label')"
                                        :model-value="getEdit(cat.category_id).max_facings ?? ''"
                                        @update:model-value="(v) => setEditField(cat.category_id, 'max_facings', v ? Number(v) : null)"
                                    />
                                </div>

                                <!-- Ordenação por preço -->
                                <FormSelectField
                                    :id="`price-order-${cat.category_id}`"
                                    name="price_order"
                                    :label="t('planogram-templates.slot_editor.price_order_label')"
                                    :model-value="getEdit(cat.category_id).price_order ?? 'none'"
                                    @update:model-value="(v) => setEditField(cat.category_id, 'price_order', v)"
                                >
                                    <option value="none">{{ t('planogram-templates.slot_editor.price_order_options.none') }}</option>
                                    <option value="asc">{{ t('planogram-templates.slot_editor.price_order_options.asc') }}</option>
                                    <option value="desc">{{ t('planogram-templates.slot_editor.price_order_options.desc') }}</option>
                                </FormSelectField>

                                <!-- Ordenação por tamanho -->
                                <FormSelectField
                                    :id="`size-order-${cat.category_id}`"
                                    name="size_order"
                                    :label="t('planogram-templates.slot_editor.size_order_label')"
                                    :model-value="getEdit(cat.category_id).size_order ?? 'none'"
                                    @update:model-value="(v) => setEditField(cat.category_id, 'size_order', v)"
                                >
                                    <option value="none">{{ t('planogram-templates.slot_editor.size_order_options.none') }}</option>
                                    <option value="asc">{{ t('planogram-templates.slot_editor.size_order_options.asc') }}</option>
                                    <option value="desc">{{ t('planogram-templates.slot_editor.size_order_options.desc') }}</option>
                                </FormSelectField>

                                <!-- Exp. marca + sabor -->
                                <div class="grid grid-cols-2 gap-2">
                                    <FormSelectField
                                        :id="`brand-exposure-${cat.category_id}`"
                                        name="brand_exposure"
                                        :label="t('planogram-templates.slot_editor.brand_exposure_label')"
                                        :model-value="getEdit(cat.category_id).brand_exposure ?? 'mixed'"
                                        @update:model-value="(v) => setEditField(cat.category_id, 'brand_exposure', v)"
                                    >
                                        <option value="vertical">{{ t('planogram-templates.slot_editor.exposure_options.vertical') }}</option>
                                        <option value="horizontal">{{ t('planogram-templates.slot_editor.exposure_options.horizontal') }}</option>
                                        <option value="mixed">{{ t('planogram-templates.slot_editor.exposure_options.mixed') }}</option>
                                    </FormSelectField>
                                    <FormSelectField
                                        :id="`flavor-exposure-${cat.category_id}`"
                                        name="flavor_exposure"
                                        :label="t('planogram-templates.slot_editor.flavor_exposure_label')"
                                        :model-value="getEdit(cat.category_id).flavor_exposure ?? 'mixed'"
                                        @update:model-value="(v) => setEditField(cat.category_id, 'flavor_exposure', v)"
                                    >
                                        <option value="vertical">{{ t('planogram-templates.slot_editor.exposure_options.vertical') }}</option>
                                        <option value="horizontal">{{ t('planogram-templates.slot_editor.exposure_options.horizontal') }}</option>
                                        <option value="mixed">{{ t('planogram-templates.slot_editor.exposure_options.mixed') }}</option>
                                    </FormSelectField>
                                </div>

                                <!-- Política de falta de espaço -->
                                <FormSelectField
                                    :id="`space-fallback-${cat.category_id}`"
                                    name="space_fallback"
                                    :label="t('planogram-templates.slot_editor.space_fallback_label')"
                                    :model-value="getEdit(cat.category_id).space_fallback ?? 'reduce_c'"
                                    @update:model-value="(v) => setEditField(cat.category_id, 'space_fallback', v)"
                                >
                                    <option value="reduce_c">{{ t('planogram-templates.slot_editor.space_fallback_options.reduce_c') }}</option>
                                    <option value="reduce_facings">{{ t('planogram-templates.slot_editor.space_fallback_options.reduce_facings') }}</option>
                                    <option value="skip">{{ t('planogram-templates.slot_editor.space_fallback_options.skip') }}</option>
                                </FormSelectField>

                                <!-- Expansão de frentes -->
                                <FormSelectField
                                    :id="`facing-expansion-${cat.category_id}`"
                                    name="facing_expansion"
                                    :label="t('planogram-templates.facing_expansion.label')"
                                    :model-value="getEdit(cat.category_id).facing_expansion ?? 'none'"
                                    @update:model-value="(v) => setEditField(cat.category_id, 'facing_expansion', v)"
                                >
                                    <option value="none">{{ t('planogram-templates.facing_expansion.none') }}</option>
                                    <option value="score">{{ t('planogram-templates.facing_expansion.score') }}</option>
                                    <option value="current_stock">{{ t('planogram-templates.facing_expansion.current_stock') }}</option>
                                    <option value="target_stock">{{ t('planogram-templates.facing_expansion.target_stock') }}</option>
                                    <option value="equal">{{ t('planogram-templates.facing_expansion.equal') }}</option>
                                </FormSelectField>

                                <!-- Usar estoque alvo -->
                                <FormSwitchField
                                    :id="`use-target-stock-${cat.category_id}`"
                                    name="use_target_stock"
                                    :label="t('planogram-templates.slot_editor.target_stock_label')"
                                    :model-value="getEdit(cat.category_id).use_target_stock ?? false"
                                    @update:model-value="(v) => setEditField(cat.category_id, 'use_target_stock', v)"
                                />

                                <!-- Ações -->
                                <div class="grid grid-cols-3 gap-1.5 pt-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 text-xs text-muted-foreground hover:text-destructive"
                                        :disabled="!hasOverride(cat.category_id) || resettingCategory === cat.category_id"
                                        @click="handleReset(cat.category_id)"
                                        type="button"
                                        title="Resetar para template"
                                    >
                                        <RotateCcw class="mr-1 size-3" />
                                        Resetar
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-7 text-xs"
                                        :disabled="savingCategory === cat.category_id"
                                        @click="handleSave(cat.category_id)"
                                        type="button"
                                    >
                                        <span v-if="savingCategory === cat.category_id" class="mr-1 size-3 animate-spin rounded-full border border-current border-t-transparent" />
                                        Salvar
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-7 text-xs"
                                        :disabled="!hasOverride(cat.category_id) || applyingCategory === cat.category_id"
                                        @click="handleApplyToTemplate(cat.category_id)"
                                        type="button"
                                        title="Aplicar ao template"
                                    >
                                        <Send class="mr-1 size-3" />
                                        Template
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mensagem quando não há categorias -->
                <div v-else-if="gondola.template_id" class="rounded-lg border border-dashed border-border p-4 text-center">
                    <p class="text-xs text-muted-foreground">Nenhuma categoria configurada nos slots deste template.</p>
                </div>

                <Separator v-if="categories.length > 0" />

                <!-- Regerar -->
                <div class="rounded-lg border border-border bg-background p-4 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 rounded-md bg-primary/10 p-2">
                            <RefreshCw class="size-4 text-primary" />
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium">{{ t('plannerate.sidebar.generation.regenerate.title') }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ t('plannerate.sidebar.generation.regenerate.description') }}
                            </p>
                            <p class="mt-2 text-xs text-muted-foreground/70 italic">
                                {{ t('plannerate.sidebar.generation.regenerate.hint') }}
                            </p>
                        </div>
                    </div>
                </div>

                <Separator />

                <!-- Redistribuir -->
                <div class="rounded-lg border border-border bg-background p-4 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 rounded-md bg-amber-500/10 p-2">
                            <LayoutGrid class="size-4 text-amber-600" />
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium">{{ t('plannerate.sidebar.generation.redistribute.title') }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ t('plannerate.sidebar.generation.redistribute.description') }}
                            </p>
                            <Button
                                variant="outline"
                                size="sm"
                                class="mt-3 w-full"
                                :disabled="isRedistributing"
                                @click="handleRedistribute"
                                type="button"
                            >
                                <LayoutGrid v-if="!isRedistributing" class="mr-2 size-3.5" />
                                <span v-if="isRedistributing" class="mr-2 size-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                {{ t('plannerate.sidebar.generation.redistribute.title') }}
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Reordenar -->
                <div class="rounded-lg border border-border bg-background p-4 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 rounded-md bg-blue-500/10 p-2">
                            <ArrowUpDown class="size-4 text-blue-600" />
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium">{{ t('plannerate.sidebar.generation.reorder.title') }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ t('plannerate.sidebar.generation.reorder.description') }}
                            </p>
                            <Button
                                variant="outline"
                                size="sm"
                                class="mt-3 w-full"
                                :disabled="isReordering"
                                @click="handleReorder"
                                type="button"
                            >
                                <ArrowUpDown v-if="!isReordering" class="mr-2 size-3.5" />
                                <span v-if="isReordering" class="mr-2 size-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                {{ t('plannerate.sidebar.generation.reorder.title') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Pencil, RotateCcw, Search, Send } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import FormSelectField from '@/components/form/FormSelectField.vue';
import FormSwitchField from '@/components/form/FormSwitchField.vue';
import FormTextField from '@/components/form/FormTextField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { selectedTemplateCategoryId } from '@/composables/plannerate/core/useGondolaState';
import { useT } from '@/composables/useT';
import type { Gondola, GondolaSlotOverride } from '../../../types/planogram';

interface Props {
    gondola: Gondola;
}

const props = defineProps<Props>();
const { t } = useT();

const overrideUrl = (gondolaId: string) => `/api/gondolas/${gondolaId}/generation-overrides`;
const overrideCategoryUrl = (gondolaId: string, categoryId: string) =>
    `/api/gondolas/${gondolaId}/generation-overrides/${categoryId}`;
const applyToTemplateUrl = (gondolaId: string, categoryId: string) =>
    `/api/gondolas/${gondolaId}/generation-overrides/${categoryId}/apply-to-template`;

interface CategoryInfo {
    category_id: string;
    category_name: string;
    category_full_path: string | null;
    subcategory: string | null;
    slot_count: number;
    modules: number[];
    shelves: number[];
    section_names: string[];
    templateDefaults: Partial<GondolaSlotOverride>;
}

const searchQuery = ref('');

/** Categorias únicas encontradas nos template_slot das prateleiras desta gôndola */
const categories = computed((): CategoryInfo[] => {
    const seen = new Map<
        string,
        {
            name: string;
            full_path: string | null;
            subcategory: string | null;
            modules: Set<number>;
            shelves: Set<number>;
            sections: Set<string>;
            count: number;
            defaults: Partial<GondolaSlotOverride>;
        }
    >();

    for (const section of props.gondola.sections ?? []) {
        for (const shelf of section.shelves ?? []) {
            const slot = (shelf as any).template_slot;

            if (!slot?.category_id) {
                continue;
            }

            const existing = seen.get(slot.category_id);

            if (existing) {
                existing.count++;
                existing.modules.add(slot.module_number);
                existing.shelves.add(slot.shelf_order);
                existing.sections.add(section.name);
            } else {
                seen.set(slot.category_id, {
                    name: slot.category_name ?? slot.category_id,
                    full_path: slot.category_full_path ?? null,
                    subcategory: slot.subcategory ?? null,
                    modules: new Set([slot.module_number]),
                    shelves: new Set([slot.shelf_order]),
                    sections: new Set([section.name]),
                    count: 1,
                    defaults: {
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

    return [...seen.entries()]
        .map(([categoryId, data]) => ({
            category_id: categoryId,
            category_name: data.name,
            category_full_path: data.full_path,
            subcategory: data.subcategory,
            slot_count: data.count,
            modules: [...data.modules].sort((a, b) => a - b),
            shelves: [...data.shelves].sort((a, b) => a - b),
            section_names: [...data.sections].sort(),
            templateDefaults: data.defaults,
        }))
        .sort((a, b) => a.category_name.localeCompare(b.category_name, 'pt-BR'));
});

/** Gera uma cor HSL determinística a partir de uma string (para diferenciar visualmente IDs duplicados) */
function idColor(id: string): string {
    let hash = 0;
    for (let i = 0; i < id.length; i++) {
        hash = (hash * 31 + id.charCodeAt(i)) >>> 0;
    }
    const hue = hash % 360;
    return `hsl(${hue}, 65%, 45%)`;
}

/** Verifica se o nome desta categoria é duplicado na lista (para exibir o ID curto) */
const duplicateNames = computed((): Set<string> => {
    const names = categories.value.map((c) => c.category_name);
    const counts = new Map<string, number>();
    for (const name of names) {
        counts.set(name, (counts.get(name) ?? 0) + 1);
    }
    return new Set([...counts.entries()].filter(([, n]) => n > 1).map(([name]) => name));
});

/** Lista filtrada pela busca */
const filteredCategories = computed(() => {
    const q = searchQuery.value.trim().toLocaleLowerCase('pt-BR');
    if (!q) {
        return categories.value;
    }
    return categories.value.filter((c) => c.category_name.toLocaleLowerCase('pt-BR').includes(q));
});

/** Limpa a seleção quando a gôndola não tem template */
watch(
    () => !!props.gondola.template_id,
    (hasTemplate) => {
        if (!hasTemplate) {
            searchQuery.value = '';
            selectedTemplateCategoryId.value = null;
        }
    },
);

/** Valida se a categoria selecionada ainda existe na lista */
watch(categories, (newCategories) => {
    const stillValid = newCategories.some(
        (c) => c.category_id === selectedTemplateCategoryId.value,
    );
    if (!stillValid) {
        selectedTemplateCategoryId.value = null;
    }
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

/** Categoria com accordion aberto (só uma por vez) */
const openCategoryId = ref<string | null>(null);

/** Refs dos cards de categoria para permitir scrollIntoView */
const categoryCardRefs = ref<Record<string, HTMLElement>>({});

/**
 * Registra ou remove a ref de um card de categoria.
 * Chamado pelo :ref dinâmico no v-for do template.
 */
function setCategoryCardRef(categoryId: string, el: unknown) {
    if (el instanceof HTMLElement) {
        categoryCardRefs.value[categoryId] = el;
    } else {
        delete categoryCardRefs.value[categoryId];
    }
}

/**
 * Quando a seleção vem de fora (ex.: clique numa prateleira no canvas),
 * rola o card correspondente para a vista sem abrir o accordion.
 */
watch(selectedTemplateCategoryId, (newCategoryId) => {
    if (!newCategoryId) {
        return;
    }
    nextTick(() => {
        categoryCardRefs.value[newCategoryId]?.scrollIntoView({
            block: 'nearest',
            behavior: 'smooth',
        });
    });
});

const savingCategory = ref<string | null>(null);
const applyingCategory = ref<string | null>(null);
const resettingCategory = ref<string | null>(null);

function templateDefaults(categoryId: string): Partial<GondolaSlotOverride> {
    return categories.value.find((c) => c.category_id === categoryId)?.templateDefaults ?? {};
}

function selectCategory(categoryId: string) {
    if (selectedTemplateCategoryId.value === categoryId) {
        selectedTemplateCategoryId.value = null;
    } else {
        selectedTemplateCategoryId.value = categoryId;
    }
}

function toggleAccordion(categoryId: string) {
    if (openCategoryId.value === categoryId) {
        openCategoryId.value = null;
    } else {
        openCategoryId.value = categoryId;
        selectedTemplateCategoryId.value = categoryId;

        if (!localEdits.value[categoryId]) {
            const saved = savedOverrides.value[categoryId];
            localEdits.value[categoryId] = saved
                ? { ...saved }
                : { category_id: categoryId, ...templateDefaults(categoryId) };
        }
    }
}

function onAccordionEnter(el: Element) {
    const htmlEl = el as HTMLElement;
    htmlEl.style.height = '0';
    htmlEl.style.overflow = 'hidden';
    requestAnimationFrame(() => {
        htmlEl.style.height = htmlEl.scrollHeight + 'px';
        htmlEl.style.transition = 'height 220ms ease';
    });
}

function onAccordionAfterEnter(el: Element) {
    const htmlEl = el as HTMLElement;
    htmlEl.style.height = '';
    htmlEl.style.overflow = '';
    htmlEl.style.transition = '';
}

function onAccordionLeave(el: Element) {
    const htmlEl = el as HTMLElement;
    htmlEl.style.height = htmlEl.scrollHeight + 'px';
    htmlEl.style.overflow = 'hidden';
    requestAnimationFrame(() => {
        htmlEl.style.transition = 'height 220ms ease';
        htmlEl.style.height = '0';
    });
}

function onAccordionAfterLeave(el: Element) {
    const htmlEl = el as HTMLElement;
    htmlEl.style.height = '';
    htmlEl.style.overflow = '';
    htmlEl.style.transition = '';
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

/** Sincroniza savedOverrides e localEdits quando Inertia recarrega o prop gondola */
watch(
    () => props.gondola.generation_overrides,
    (overrides) => {
        savedOverrides.value = Object.fromEntries(
            (overrides ?? [])
                .filter((o) => o.category_id !== null)
                .map((o) => [o.category_id as string, o]),
        );

        if (openCategoryId.value) {
            const saved = savedOverrides.value[openCategoryId.value];
            localEdits.value[openCategoryId.value] = saved
                ? { ...saved }
                : { category_id: openCategoryId.value, ...templateDefaults(openCategoryId.value) };
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
        onFinish: () => {
            savingCategory.value = null;
        },
    });
}

function handleApplyToTemplate(categoryId: string) {
    applyingCategory.value = categoryId;

    router.post(
        applyToTemplateUrl(props.gondola.id, categoryId),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            only: ['record'],
            onSuccess: () => toast.success('Configuração aplicada ao template.'),
            onError: (errors) => toast.error(errors.message ?? 'Erro ao aplicar ao template.'),
            onFinish: () => {
                applyingCategory.value = null;
            },
        },
    );
}

function handleReset(categoryId: string) {
    resettingCategory.value = categoryId;

    router.delete(overrideCategoryUrl(props.gondola.id, categoryId), {
        preserveState: true,
        preserveScroll: true,
        only: ['record'],
        onSuccess: () => toast.success('Override removido. Usando configuração do template.'),
        onError: () => toast.error('Erro ao resetar configuração.'),
        onFinish: () => {
            resettingCategory.value = null;
        },
    });
}
</script>

<template>
    <div v-if="categories.length > 0">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Configurações por Categoria
        </p>

        <!-- Busca -->
        <div class="relative mb-2">
            <Search class="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
            <Input
                v-model="searchQuery"
                placeholder="Buscar categoria..."
                class="h-8 pl-8 text-xs"
            />
        </div>

        <div class="space-y-2">
            <div
                v-for="cat in filteredCategories"
                :key="cat.category_id"
                :ref="(el) => setCategoryCardRef(cat.category_id, el)"
                class="rounded-lg border bg-background shadow-sm transition-colors"
                :class="selectedTemplateCategoryId === cat.category_id ? 'border-green-500/70' : 'border-border'"
            >
                <!-- Cabeçalho do accordion -->
                <button
                    type="button"
                    class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left transition-colors cursor-pointer hover:bg-muted/50"
                    :class="selectedTemplateCategoryId === cat.category_id ? 'bg-green-500/15' : ''"
                    @click="selectCategory(cat.category_id)"
                >
                    <div class="min-w-0 flex-1">
                        <!-- Nome + ID curto quando há duplicatas -->
                        <div class="flex min-w-0 items-center gap-1.5">
                            <span class="truncate text-sm font-medium">{{ cat.category_name }}</span>
                            <span
                                v-if="duplicateNames.has(cat.category_name)"
                                class="shrink-0 rounded px-1 font-mono text-[9px] font-semibold"
                                :style="{ color: idColor(cat.category_id), backgroundColor: idColor(cat.category_id) + '1a' }"
                                :title="cat.category_id"
                            >
                                #{{ cat.category_id.slice(-8) }}
                            </span>
                        </div>
                        <!-- Caminho completo da categoria (hierarquia) -->
                        <p v-if="cat.category_full_path" class="truncate text-[8px] text-muted-foreground/80" :title="cat.category_full_path">
                            {{ cat.category_full_path }}
                        </p>
                        <!-- Subcategoria (fallback quando não há full_path) -->
                        <p v-else-if="cat.subcategory" class="truncate text-[10px] text-muted-foreground/80">
                            {{ cat.subcategory }}
                        </p>
                        <!-- Módulos · Prateleiras -->
                        <p class="text-[10px] text-muted-foreground">
                            <span v-if="cat.modules.length > 0">Mód. {{ cat.modules.join(', ') }}</span>
                            <span v-if="cat.modules.length > 0 && cat.shelves.length > 0"> · </span>
                            <span v-if="cat.shelves.length > 0">Prat. {{ cat.shelves.join(', ') }}</span>
                        </p>
                    </div>
                    <div class="ml-2 flex shrink-0 items-center gap-1.5">
                        <span
                            v-if="hasOverride(cat.category_id)"
                            class="size-2 rounded-full bg-amber-500"
                            title="Override local ativo"
                        />
                        <span class="text-[10px] text-muted-foreground">{{ cat.slot_count }} slots</span>
                        <button
                            type="button"
                            class="cursor-pointer rounded-md p-1 transition-colors hover:bg-primary/10 hover:text-primary"
                            :class="openCategoryId === cat.category_id ? 'bg-primary/10 text-primary' : 'text-muted-foreground'"
                            title="Editar configurações"
                            @click.stop="toggleAccordion(cat.category_id)"
                        >
                            <Pencil class="size-3.5" />
                        </button>
                    </div>
                </button>

                <!-- Corpo do accordion -->
                <Transition
                    @enter="onAccordionEnter"
                    @after-enter="onAccordionAfterEnter"
                    @leave="onAccordionLeave"
                    @after-leave="onAccordionAfterLeave"
                >
                <div
                    v-if="openCategoryId === cat.category_id"
                    class="space-y-3 border-t border-border px-3 pb-3 pt-2.5"
                >
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

                    <!-- Exposição de marca + sabor -->
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
                            type="button"
                            title="Resetar para template"
                            @click="handleReset(cat.category_id)"
                        >
                            <RotateCcw class="mr-1 size-3" />
                            Resetar
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-7 text-xs"
                            :disabled="savingCategory === cat.category_id"
                            type="button"
                            @click="handleSave(cat.category_id)"
                        >
                            <span
                                v-if="savingCategory === cat.category_id"
                                class="mr-1 size-3 animate-spin rounded-full border border-current border-t-transparent"
                            />
                            Salvar
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-7 text-xs"
                            :disabled="!hasOverride(cat.category_id) || applyingCategory === cat.category_id"
                            type="button"
                            title="Aplicar ao template"
                            @click="handleApplyToTemplate(cat.category_id)"
                        >
                            <Send class="mr-1 size-3" />
                            Template
                        </Button>
                    </div>
                </div>
                </Transition>
            </div>
        </div>

        <!-- Nenhum resultado para a busca -->
        <p v-if="filteredCategories.length === 0" class="py-4 text-center text-xs text-muted-foreground">
            Nenhuma categoria encontrada
        </p>
    </div>

    <!-- Mensagem quando não há categorias configuradas -->
    <div v-else-if="gondola.template_id" class="rounded-lg border border-dashed border-border p-4 text-center">
        <p class="text-xs text-muted-foreground">Nenhuma categoria configurada nos slots deste template.</p>
    </div>
</template>

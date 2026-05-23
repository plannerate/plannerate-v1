<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Info, Loader2, Sparkles } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { z } from 'zod';
import AdvancedOptionsSection from '@/components/plannerate/header/partials/AdvancedOptionsSection.vue';
import FacingsSettingsSection from '@/components/plannerate/header/partials/FacingsSettingsSection.vue';
import SalesDataSection from '@/components/plannerate/header/partials/SalesDataSection.vue';
import CategorySelect from '@/components/plannerate/sidebar/products/CategorySelect.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useT } from '@/composables/useT';
import type { Gondola } from '@/types/planogram';

const props = defineProps<{
    open: boolean;
    gondola: Gondola;
    startDate?: string;
    endDate?: string;
    categoryId?: string | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { t } = useT();

/**
 * Schema Zod para validação do formulário de geração automática de planograma.
 * Inclui validações condicionais (período obrigatório quando não usa análise existente)
 * e cruzadas (max_facings ≥ min_facings).
 */
const AutoGenerateSchema = z
    .object({
        strategy: z.enum(['abc', 'sales', 'margin', 'mix']),
        use_existing_analysis: z.boolean(),
        start_date: z.string(),
        end_date: z.string(),
        min_facings: z.number().int().min(1, 'Mínimo é 1 facing').max(10, 'Máximo é 10 facings'),
        max_facings: z.number().int().min(1, 'Mínimo é 1 facing').max(20, 'Máximo é 20 facings'),
        group_by_subcategory: z.boolean(),
        include_products_without_sales: z.boolean(),
        table_type: z.enum(['sales', 'monthly_summaries']),
        category_id: z.string().nullable(),
        template_id: z.string().nullable(),
        facing_expansion: z.enum(['score', 'current_stock', 'target_stock', 'equal']).nullable(),
        use_target_stock: z.boolean(),
        space_fallback: z.enum(['reduce_c', 'reduce_facings', 'skip']).nullable(),
        max_share_per_sku: z.number().int().min(1, 'Mínimo 1%').max(100, 'Máximo 100%').nullable(),
        max_share_per_brand: z.number().int().min(1, 'Mínimo 1%').max(100, 'Máximo 100%').nullable(),
        max_share_per_subcategory: z
            .number()
            .int()
            .min(1, 'Mínimo 1%')
            .max(100, 'Máximo 100%')
            .nullable(),
    })
    .superRefine((data, ctx) => {
        if (!data.use_existing_analysis) {
            if (!data.start_date) {
                ctx.addIssue({
                    code: 'custom' as const,
                    path: ['start_date'],
                    message: 'Data inicial é obrigatória',
                });
            }

            if (!data.end_date) {
                ctx.addIssue({
                    code: 'custom' as const,
                    path: ['end_date'],
                    message: 'Data final é obrigatória',
                });
            }

            if (data.start_date && data.end_date && data.start_date > data.end_date) {
                ctx.addIssue({
                    code: 'custom' as const,
                    path: ['end_date'],
                    message: 'Data final deve ser posterior à data inicial',
                });
            }
        }

        if (data.max_facings < data.min_facings) {
            ctx.addIssue({
                code: 'custom' as const,
                path: ['max_facings'],
                message: 'Máximo deve ser ≥ ao mínimo de facings',
            });
        }
    });

const form = useForm({
    strategy: 'abc' as 'abc' | 'sales' | 'margin' | 'mix',
    use_existing_analysis: false,
    start_date: props.startDate ?? '',
    end_date: props.endDate ?? '',
    min_facings: 1,
    max_facings: 10,
    group_by_subcategory: true,
    include_products_without_sales: false,
    table_type: 'monthly_summaries' as 'sales' | 'monthly_summaries',
    category_id: (props.categoryId ?? null) as string | null,
    template_id: null as string | null,
    facing_expansion: null as string | null,
    use_target_stock: false,
    space_fallback: null as string | null,
    max_share_per_sku: null as number | null,
    max_share_per_brand: null as number | null,
    max_share_per_subcategory: null as number | null,
});

/** Controla se o usuário já tentou submeter — erros só aparecem após o primeiro submit */
const hasAttemptedSubmit = ref(false);

/** Resultado Zod reativo: recomputa sempre que qualquer campo do form muda */
const zodResult = computed(() =>
    AutoGenerateSchema.safeParse({
        strategy: form.strategy,
        use_existing_analysis: form.use_existing_analysis,
        start_date: form.start_date,
        end_date: form.end_date,
        min_facings: form.min_facings,
        max_facings: form.max_facings,
        group_by_subcategory: form.group_by_subcategory,
        include_products_without_sales: form.include_products_without_sales,
        table_type: form.table_type,
        category_id: form.category_id,
        template_id: form.template_id,
        facing_expansion: form.facing_expansion,
        use_target_stock: form.use_target_stock,
        space_fallback: form.space_fallback,
        max_share_per_sku: form.max_share_per_sku,
        max_share_per_brand: form.max_share_per_brand,
        max_share_per_subcategory: form.max_share_per_subcategory,
    }),
);

/** Mapa de erros por campo — visível apenas após a primeira tentativa de submit */
const zodErrors = computed<Record<string, string>>(() => {
    if (!hasAttemptedSubmit.value || zodResult.value.success) {
        return {};
    }

    const { fieldErrors } = z.flattenError(zodResult.value.error);

    return Object.fromEntries(
        Object.entries(fieldErrors)
            .filter(([, msgs]) => msgs?.length)
            .map(([field, msgs]) => [field, msgs![0]]),
    );
});

const isFormValid = computed(() => zodResult.value.success);

watch(
    () => [props.startDate, props.endDate, props.categoryId] as const,
    ([newStart, newEnd, newCategoryId]) => {
        if (newStart) {
            form.start_date = newStart;
        }

        if (newEnd) {
            form.end_date = newEnd;
        }

        form.category_id = newCategoryId ?? null;
    },
);

function handleClose(): void {
    emit('update:open', false);
    form.reset();
    form.category_id = props.categoryId ?? null;
    hasAttemptedSubmit.value = false;
}

function handleGenerate(): void {
    hasAttemptedSubmit.value = true;

    if (!isFormValid.value) {
        return;
    }

    form.post(`/api/gondolas/${props.gondola.id}/auto-generate`, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            emit('update:open', false);
            form.reset();
            hasAttemptedSubmit.value = false;
        },
        onError: (errors) => {
            alert(
                t('plannerate.header.auto_generate.error_prefix') +
                (Object.values(errors)[0] || t('plannerate.header.auto_generate.unknown_error')),
            );
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="handleClose">
        <DialogContent class="flex max-h-[90vh] max-w-full flex-col md:max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Sparkles class="size-5 text-primary" />
                    {{ t('plannerate.header.auto_generate.title') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('plannerate.header.auto_generate.description') }}
                </DialogDescription>
            </DialogHeader>

            <div class="flex-1 space-y-6 overflow-x-hidden overflow-y-auto py-4">
                <!-- Pontuação de posicionamento -->
                <div
                    class="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-950">
                    <Info class="mt-0.5 size-4 shrink-0 text-blue-600 dark:text-blue-400" />
                    <div class="space-y-1 text-sm">
                        <p class="font-medium text-blue-900 dark:text-blue-100">Pontuação de posicionamento</p>
                        <p class="text-blue-700 dark:text-blue-300">
                            Os produtos são posicionados por um score composto:
                            <span class="font-medium">Giro 40%</span> ·
                            <span class="font-medium">Margem 30%</span> ·
                            <span class="font-medium">Estratégico 20%</span> ·
                            <span class="font-medium">DOH 10%</span>
                        </p>
                        <p class="text-xs text-blue-600 dark:text-blue-400">Os pesos podem ser ajustados nas
                            configurações do tenant.</p>
                    </div>
                </div>

                <div class="space-y-4 rounded-lg border border-purple-200 p-4">
                    <CategorySelect v-model="form.category_id" :disabled="false" :required="false"
                        :root-category-id="categoryId ?? undefined" />
                    <div class="pt-2 text-xs text-muted-foreground">
                        {{ t('plannerate.header.auto_generate.category_scope_hint') }}
                    </div>
                </div>

                <div class="border-t" />
                <SalesDataSection :form="form" :errors="zodErrors" />

                <div class="border-t pt-4" />
                <FacingsSettingsSection :form="form" :errors="zodErrors" />

                <div class="border-t pt-4" />
                <AdvancedOptionsSection :form="form" />

                <!-- Configurações dos slots (defaults globais) -->
                <div class="border-t pt-4" />
                <div class="space-y-4">
                    <div>
                        <Label class="text-base font-semibold">{{
                            t('plannerate.header.auto_generate.slot_defaults_title') }}</Label>
                        <p class="mt-0.5 text-sm text-muted-foreground">{{
                            t('plannerate.header.auto_generate.slot_defaults_description') }}</p>
                    </div>

                    <!-- Expansão e fallback -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="facing-expansion">{{ t('plannerate.header.auto_generate.facing_expansion_label')
                                }}</Label>
                            <select id="facing-expansion" v-model="form.facing_expansion"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option :value="null">{{ t('plannerate.header.auto_generate.facing_expansion_none') }}
                                </option>
                                <option value="score">{{ t('plannerate.header.auto_generate.facing_expansion_score') }}
                                </option>
                                <option value="current_stock">{{
                                    t('plannerate.header.auto_generate.facing_expansion_current_stock') }}</option>
                                <option value="target_stock">{{
                                    t('plannerate.header.auto_generate.facing_expansion_target_stock') }}</option>
                                <option value="equal">{{ t('plannerate.header.auto_generate.facing_expansion_equal') }}
                                </option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <Label for="space-fallback">{{ t('plannerate.header.auto_generate.space_fallback_label')
                                }}</Label>
                            <select id="space-fallback" v-model="form.space_fallback"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option :value="null">—</option>
                                <option value="reduce_c">{{ t('plannerate.header.auto_generate.space_fallback_reduce_c')
                                    }}</option>
                                <option value="reduce_facings">{{
                                    t('plannerate.header.auto_generate.space_fallback_reduce_facings') }}</option>
                                <option value="skip">{{ t('plannerate.header.auto_generate.space_fallback_skip') }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Usar estoque alvo -->
                    <div class="flex items-center justify-between">
                        <div class="space-y-0.5">
                            <Label for="use-target-stock">{{ t('plannerate.header.auto_generate.use_target_stock_label')
                                }}</Label>
                        </div>
                        <Switch id="use-target-stock" v-model="form.use_target_stock" />
                    </div>

                    <!-- Limites de participação -->
                    <div class="space-y-2">
                        <Label class="text-sm font-semibold">{{ t('plannerate.header.auto_generate.share_limits_title')
                            }}</Label>
                        <p class="text-xs text-muted-foreground">{{
                            t('plannerate.header.auto_generate.share_limits_description') }}</p>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="space-y-1">
                                <Label for="max-share-sku" class="text-xs">{{
                                    t('plannerate.header.auto_generate.max_share_per_sku_label') }}</Label>
                                <Input id="max-share-sku" :value="form.max_share_per_sku" type="number" min="1"
                                    max="100" :placeholder="t('plannerate.header.auto_generate.max_share_per_sku_hint')"
                                    @input="(e: Event) => form.max_share_per_sku = (e.target as HTMLInputElement).value ? Number((e.target as HTMLInputElement).value) : null" />
                                <p v-if="zodErrors.max_share_per_sku" class="text-xs text-red-500">
                                    {{ zodErrors.max_share_per_sku }}
                                </p>
                            </div>
                            <div class="space-y-1">
                                <Label for="max-share-brand" class="text-xs">{{
                                    t('plannerate.header.auto_generate.max_share_per_brand_label') }}</Label>
                                <Input id="max-share-brand" :value="form.max_share_per_brand" type="number" min="1"
                                    max="100"
                                    :placeholder="t('plannerate.header.auto_generate.max_share_per_brand_hint')"
                                    @input="(e: Event) => form.max_share_per_brand = (e.target as HTMLInputElement).value ? Number((e.target as HTMLInputElement).value) : null" />
                                <p v-if="zodErrors.max_share_per_brand" class="text-xs text-red-500">
                                    {{ zodErrors.max_share_per_brand }}
                                </p>
                            </div>
                            <div class="space-y-1">
                                <Label for="max-share-subcat" class="text-xs">{{
                                    t('plannerate.header.auto_generate.max_share_per_subcategory_label') }}</Label>
                                <Input id="max-share-subcat" type="number" min="1" max="100"
                                    :placeholder="t('plannerate.header.auto_generate.max_share_per_subcategory_hint')"
                                    @input="(e: Event) => form.max_share_per_subcategory = (e.target as HTMLInputElement).value ? Number((e.target as HTMLInputElement).value) : null" />
                                <p v-if="zodErrors.max_share_per_subcategory" class="text-xs text-red-500">
                                    {{ zodErrors.max_share_per_subcategory }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" :disabled="form.processing" @click="handleClose">
                    {{ t('plannerate.common.cancel') }}
                </Button>
                <Button type="button" class="gap-2" :disabled="!isFormValid || form.processing" @click="handleGenerate">
                    <Loader2 v-if="form.processing" class="animate-spin" />
                    <Sparkles v-else />
                    {{ form.processing ? t('plannerate.header.auto_generate.generating') :
                        t('plannerate.header.auto_generate.generate') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Info, Loader2, Sparkles } from 'lucide-vue-next';
import { computed, watch } from 'vue';
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
    category_id: props.categoryId ?? null as string | null,
    template_id: null as string | null,
    facing_expansion: null as string | null,
    use_target_stock: false,
    space_fallback: null as string | null,
    max_share_per_sku: null as number | null,
    max_share_per_brand: null as number | null,
    max_share_per_subcategory: null as number | null,
});

const isFormValid = computed(() => {
    if (!form.use_existing_analysis) {
        return !!(form.start_date && form.end_date && form.start_date <= form.end_date);
    }

    return true;
});

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
}

function handleGenerate(): void {
    if (!isFormValid.value) {
        return;
    }

    form.post(`/api/gondolas/${props.gondola.id}/auto-generate`, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            emit('update:open', false);
            form.reset();
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
                <div class="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-950">
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
                        <p class="text-xs text-blue-600 dark:text-blue-400">Os pesos podem ser ajustados nas configurações do tenant.</p>
                    </div>
                </div>

                <div class="space-y-4 rounded-lg border border-purple-200 p-4">
                    <CategorySelect
                        v-model="form.category_id"
                        :disabled="false"
                        :required="false"
                        :root-category-id="categoryId ?? undefined"
                    />
                    <div class="pt-2 text-xs text-muted-foreground">
                        {{ t('plannerate.header.auto_generate.category_scope_hint') }}
                    </div>
                </div>

                <div class="border-t" />
                <SalesDataSection :form="form" />

                <div class="border-t pt-4" />
                <FacingsSettingsSection :form="form" />

                <div class="border-t pt-4" />
                <AdvancedOptionsSection :form="form" />

                <!-- Configurações dos slots (defaults globais) -->
                <div class="border-t pt-4" />
                <div class="space-y-4">
                    <div>
                        <Label class="text-base font-semibold">{{ t('plannerate.header.auto_generate.slot_defaults_title') }}</Label>
                        <p class="mt-0.5 text-sm text-muted-foreground">{{ t('plannerate.header.auto_generate.slot_defaults_description') }}</p>
                    </div>

                    <!-- Expansão e fallback -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="facing-expansion">{{ t('plannerate.header.auto_generate.facing_expansion_label') }}</Label>
                            <select
                                id="facing-expansion"
                                v-model="form.facing_expansion"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option :value="null">{{ t('plannerate.header.auto_generate.facing_expansion_none') }}</option>
                                <option value="score">{{ t('plannerate.header.auto_generate.facing_expansion_score') }}</option>
                                <option value="current_stock">{{ t('plannerate.header.auto_generate.facing_expansion_current_stock') }}</option>
                                <option value="target_stock">{{ t('plannerate.header.auto_generate.facing_expansion_target_stock') }}</option>
                                <option value="equal">{{ t('plannerate.header.auto_generate.facing_expansion_equal') }}</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <Label for="space-fallback">{{ t('plannerate.header.auto_generate.space_fallback_label') }}</Label>
                            <select
                                id="space-fallback"
                                v-model="form.space_fallback"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option :value="null">—</option>
                                <option value="reduce_c">{{ t('plannerate.header.auto_generate.space_fallback_reduce_c') }}</option>
                                <option value="reduce_facings">{{ t('plannerate.header.auto_generate.space_fallback_reduce_facings') }}</option>
                                <option value="skip">{{ t('plannerate.header.auto_generate.space_fallback_skip') }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Usar estoque alvo -->
                    <div class="flex items-center justify-between">
                        <div class="space-y-0.5">
                            <Label for="use-target-stock">{{ t('plannerate.header.auto_generate.use_target_stock_label') }}</Label>
                        </div>
                        <Switch id="use-target-stock" v-model="form.use_target_stock" />
                    </div>

                    <!-- Limites de participação -->
                    <div class="space-y-2">
                        <Label class="text-sm font-semibold">{{ t('plannerate.header.auto_generate.share_limits_title') }}</Label>
                        <p class="text-xs text-muted-foreground">{{ t('plannerate.header.auto_generate.share_limits_description') }}</p>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="space-y-1">
                                <Label for="max-share-sku" class="text-xs">{{ t('plannerate.header.auto_generate.max_share_per_sku_label') }}</Label>
                                <Input
                                    id="max-share-sku"
                                    v-model.number="form.max_share_per_sku"
                                    type="number"
                                    min="1"
                                    max="100"
                                    :placeholder="t('plannerate.header.auto_generate.max_share_per_sku_hint')"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label for="max-share-brand" class="text-xs">{{ t('plannerate.header.auto_generate.max_share_per_brand_label') }}</Label>
                                <Input
                                    id="max-share-brand"
                                    v-model.number="form.max_share_per_brand"
                                    type="number"
                                    min="1"
                                    max="100"
                                    :placeholder="t('plannerate.header.auto_generate.max_share_per_brand_hint')"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label for="max-share-subcat" class="text-xs">{{ t('plannerate.header.auto_generate.max_share_per_subcategory_label') }}</Label>
                                <Input
                                    id="max-share-subcat"
                                    v-model.number="form.max_share_per_subcategory"
                                    type="number"
                                    min="1"
                                    max="100"
                                    :placeholder="t('plannerate.header.auto_generate.max_share_per_subcategory_hint')"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" :disabled="form.processing" @click="handleClose">
                    {{ t('plannerate.common.cancel') }}
                </Button>
                <Button
                    type="button"
                    class="gap-2"
                    :disabled="!isFormValid || form.processing"
                    @click="handleGenerate"
                >
                    <Loader2 v-if="form.processing" class="animate-spin" />
                    <Sparkles v-else />
                    {{ form.processing ? t('plannerate.header.auto_generate.generating') : t('plannerate.header.auto_generate.generate') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

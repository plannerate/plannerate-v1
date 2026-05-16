<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Info, Loader2, Sparkles } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import AdvancedOptionsSection from '@/components/plannerate/header/partials/AdvancedOptionsSection.vue';
import FacingsSettingsSection from '@/components/plannerate/header/partials/FacingsSettingsSection.vue';
import SalesDataSection from '@/components/plannerate/header/partials/SalesDataSection.vue';
import StrategySelectionSection from '@/components/plannerate/header/partials/StrategySelectionSection.vue';
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
import { useT } from '@/composables/useT';

interface StrategyOption {
    value: string;
    label: string;
    description: string;
}

const props = defineProps<{
    open: boolean;
    gondolaId: string;
    startDate?: string;
    endDate?: string;
    categoryId?: string | null;
    strategyOptions: StrategyOption[];
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
    category_id: props.categoryId ?? null,
});

watch(
    () => [props.startDate, props.endDate, props.categoryId] as const,
    ([newStart, newEnd, newCategoryId]) => {
        if (newStart) form.start_date = newStart;
        if (newEnd) form.end_date = newEnd;
        form.category_id = newCategoryId ?? null;
    },
);

const isFormValid = computed(() => {
    if (!form.use_existing_analysis) {
        return !!(form.start_date && form.end_date && form.start_date <= form.end_date);
    }
    return true;
});

function handleClose() {
    emit('update:open', false);
    form.reset();
}

function handleGenerate() {
    if (!isFormValid.value) return;

    form.post(`/api/gondolas/${props.gondolaId}/auto-generate`, {
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

                <StrategySelectionSection
                    :form="form"
                    :strategy-options="strategyOptions"
                    title="Critério de Seleção de Produtos"
                />

                <div class="border-t" />

                <div class="space-y-4 rounded-lg border border-purple-200 p-4">
                    <CategorySelect
                        v-model="form.category_id"
                        :disabled="false"
                        :required="false"
                    />
                    <div class="pt-2 text-xs text-muted-foreground">
                        {{ t('plannerate.header.auto_generate.category_hint') }}
                    </div>
                </div>

                <div class="border-t" />
                <SalesDataSection :form="form" />

                <div class="border-t pt-4" />
                <FacingsSettingsSection :form="form" />

                <div class="border-t pt-4" />
                <AdvancedOptionsSection :form="form" />
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

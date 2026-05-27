<script lang="ts">
export interface GenerationFormState {
    strategy: 'abc' | 'sales' | 'margin' | 'mix';
    use_existing_analysis: boolean;
    start_date: string;
    end_date: string;
    min_facings: number;
    max_facings: number;
    group_by_subcategory: boolean;
    include_products_without_sales: boolean;
    table_type: 'sales' | 'monthly_summaries';
    category_id: string | null;
    /** Comportamento de expansão de frentes no espaço livre */
    facing_expansion: string | null;
    /** Comportamento quando falta espaço para todos os produtos */
    space_fallback: string | null;
    /** Usar estoque alvo para expandir frentes */
    use_target_stock: boolean;
}

/**
 * Valida o passo de geração (modo automático).
 * Período obrigatório quando não usa análise existente; máximo de frentes ≥ mínimo.
 */
export const validate = (data: GenerationFormState): boolean => {
    if (!data.use_existing_analysis) {
        if (!data.start_date || !data.end_date) {
            return false;
        }

        if (data.start_date > data.end_date) {
            return false;
        }
    }

    return data.max_facings >= data.min_facings;
};
</script>

<script setup lang="ts">
import { Sparkles } from 'lucide-vue-next';
import AdvancedOptionsSection from '@/components/plannerate/header/partials/AdvancedOptionsSection.vue';
import FacingsSettingsSection from '@/components/plannerate/header/partials/FacingsSettingsSection.vue';
import SalesDataSection from '@/components/plannerate/header/partials/SalesDataSection.vue';
import CategorySelect from '@/components/plannerate/sidebar/products/CategorySelect.vue';
import { useT } from '@/composables/useT';

interface Props {
    /** O useForm do stepper — os partials mutam os campos de geração diretamente */
    form: GenerationFormState & Record<string, unknown>;
    errors?: Record<string, string>;
    /** Categoria-base do planograma (trava a cascata de seleção) */
    rootCategoryId?: string | null;
}

const props = defineProps<Props>();
const { t } = useT();

function setCategory(value: string | null): void {
    props.form.category_id = value;
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center gap-2">
            <div class="rounded-full bg-primary/10 p-2">
                <Sparkles class="h-5 w-5 text-primary" />
            </div>
            <h3 class="text-lg font-medium">
                {{ t('plannerate.form.gondola_create.steps.generation.title') }}
            </h3>
        </div>

        <div class="space-y-4 rounded-lg border border-purple-200 p-4 dark:border-purple-900">
            <CategorySelect
                :model-value="props.form.category_id"
                :disabled="false"
                :required="false"
                :root-category-id="rootCategoryId ?? undefined"
                @update:model-value="setCategory"
            />
            <p class="pt-2 text-xs text-muted-foreground">
                {{ t('plannerate.header.auto_generate.category_scope_hint') }}
            </p>
        </div>

        <div class="border-t" />
        <SalesDataSection :form="props.form" :errors="errors" />

        <div class="border-t pt-4" />
        <FacingsSettingsSection :form="props.form" :errors="errors" :show-expansion-options="true" />

        <div class="border-t pt-4" />
        <AdvancedOptionsSection :form="props.form" />
    </div>
</template>

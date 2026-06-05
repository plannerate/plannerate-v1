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
    /** Remove produtos classificados como C antes da geração */
    exclude_class_c: boolean;
    table_type: 'sales' | 'monthly_summaries';
    category_id: string | null;
    /** Comportamento de expansão de frentes no espaço livre */
    facing_expansion: string | null;
    /** Comportamento quando falta espaço para todos os produtos */
    space_fallback: string | null;
    /** Usar estoque alvo para expandir frentes */
    use_target_stock: boolean;
    /** Prioridade de zona quente (ex: 'maior_margem') */
    hot_zone_priority: string | null;
    /** Prioridade de zona fria (ex: 'complementar_fria') */
    cold_zone_priority: string | null;
    /** Sentido de leitura (null = esquerda→direita) */
    flow_direction: string | null;
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
import { Label } from '@/components/ui/label';
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

        <!-- Zonas e sentido de leitura -->
        <div class="border-t pt-4" />
        <div class="space-y-2">
            <Label class="text-base font-semibold">{{ t('plannerate.header.auto_generate.zone_flow_title') }}</Label>
            <div class="grid grid-cols-3 gap-3">
                <div class="space-y-1">
                    <Label for="step-hot-zone" class="text-xs">{{ t('plannerate.header.auto_generate.hot_zone_priority_label') }}</Label>
                    <select id="step-hot-zone" v-model="props.form.hot_zone_priority"
                        class="flex h-8 w-full rounded-md border border-input bg-transparent px-2 py-1 text-xs shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                        <option value="none">{{ t('plannerate.header.auto_generate.zone_priority_none') }}</option>
                        <option value="maior_margem">{{ t('plannerate.header.auto_generate.zone_priority_maior_margem') }}</option>
                        <option value="maior_giro">{{ t('plannerate.header.auto_generate.zone_priority_maior_giro') }}</option>
                        <option value="maior_valor_vendido">{{ t('plannerate.header.auto_generate.zone_priority_maior_valor_vendido') }}</option>
                        <option value="curva_a">{{ t('plannerate.header.auto_generate.zone_priority_curva_a') }}</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <Label for="step-cold-zone" class="text-xs">{{ t('plannerate.header.auto_generate.cold_zone_priority_label') }}</Label>
                    <select id="step-cold-zone" v-model="props.form.cold_zone_priority"
                        class="flex h-8 w-full rounded-md border border-input bg-transparent px-2 py-1 text-xs shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                        <option value="none">{{ t('plannerate.header.auto_generate.zone_priority_none') }}</option>
                        <option value="menor_margem">{{ t('plannerate.header.auto_generate.zone_priority_menor_margem') }}</option>
                        <option value="complementar_fria">{{ t('plannerate.header.auto_generate.zone_priority_complementar_fria') }}</option>
                        <option value="maior_volume">{{ t('plannerate.header.auto_generate.zone_priority_maior_volume') }}</option>
                        <option value="menor_prioridade">{{ t('plannerate.header.auto_generate.zone_priority_menor_prioridade') }}</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <Label for="step-flow-direction" class="text-xs">{{ t('plannerate.header.auto_generate.flow_direction_label') }}</Label>
                    <select id="step-flow-direction" v-model="props.form.flow_direction"
                        class="flex h-8 w-full rounded-md border border-input bg-transparent px-2 py-1 text-xs shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                        <option :value="null">{{ t('plannerate.header.auto_generate.flow_direction_left_to_right') }}</option>
                        <option value="right_to_left">{{ t('plannerate.header.auto_generate.flow_direction_right_to_left') }}</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</template>

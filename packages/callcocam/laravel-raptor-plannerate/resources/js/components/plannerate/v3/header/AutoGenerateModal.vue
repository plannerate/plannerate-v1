<script setup lang="ts">
import AiSettingsSection from '@/components/plannerate/v3/header/partials/AiSettingsSection.vue';
import AdvancedOptionsSection from '@/components/plannerate/v3/header/partials/AdvancedOptionsSection.vue';
import CategorySelect from '@/components/plannerate/v3/sidebar/products/CategorySelect.vue';
import FacingsSettingsSection from '@/components/plannerate/v3/header/partials/FacingsSettingsSection.vue';
import GenerationModeSettings from '@/components/plannerate/v3/header/partials/GenerationModeSettings.vue';
import SalesDataSection from '@/components/plannerate/v3/header/partials/SalesDataSection.vue';
import StrategySelectionSection from '@/components/plannerate/v3/header/partials/StrategySelectionSection.vue';
import { Button } from '~/components/ui/button';
import ActionIconBox from '~/components/ui/ActionIconBox.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { Loader2, Sparkles } from 'lucide-vue-next';
import { computed, watch } from 'vue';

// Props
const props = defineProps<{
    open: boolean;
    gondolaId: string;
    startDate?: string;
    endDate?: string;
    categoryId?: string | null;
    aiModelOptions: OptionItem[];
    strategyOptions: OptionItem[];
    permissions: { 
        can_autogenate_gondola: boolean;
        can_autogenate_gondola_ia: boolean;
    };
}>();

// Emits
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

// Types
interface OptionItem {
    value: string;
    label: string;
    description: string;
}

interface AutoGenerateConfig {
    strategy: 'abc' | 'sales' | 'margin' | 'mix';
    use_existing_analysis: boolean;
    start_date?: string;
    end_date?: string;
    min_facings: number;
    max_facings: number;
    group_by_subcategory: boolean;
    include_products_without_sales: boolean;
    table_type: 'sales' | 'monthly_summaries';
    // Por section: gera módulo a módulo (regras ou Laravel AI)
    generate_by_sections: boolean;
    // IA specific fields (ia-generate usa Prism; generate-by-sections com use_ai usa Laravel AI)
    use_ai: boolean;
    model?: string;
    apply_visual_grouping?: boolean;
    intelligent_ordering?: boolean;
    load_balancing?: boolean;
    additional_instructions?: string;
    // Categoria selecionada
    category_id?: string | null;
}

// Formulário Inertia
const form = useForm<AutoGenerateConfig>({
    strategy: 'abc',
    use_existing_analysis: false, // Forçar recálculo por padrão
    start_date: props.startDate,
    end_date: props.endDate,
    min_facings: 1,
    max_facings: 10,
    group_by_subcategory: true,
    include_products_without_sales: false, // Incluir produtos sem vendas
    table_type: 'monthly_summaries',
    generate_by_sections: true, // Gerar por section por padrão
    use_ai: false,
    model: 'gpt-4o-mini',
    apply_visual_grouping: true,
    intelligent_ordering: true,
    load_balancing: true,
    additional_instructions: '',
    category_id: props.categoryId || null,
});

// Watch props para atualizar datas e categoria
watch(
    () => [props.startDate, props.endDate, props.categoryId],
    ([newStart, newEnd, newCategoryId]) => {
        if (newStart) form.start_date = newStart;
        if (newEnd) form.end_date = newEnd;
        form.category_id = newCategoryId || null;
    },
);

// Computed
const isFormValid = computed(() => {
    if (!form.use_existing_analysis) {
        return (
            form.start_date && form.end_date && form.start_date <= form.end_date && (form.generate_by_sections || form.use_ai)
        );
    }
    return true;
});

// Métodos
function handleClose() {
    emit('update:open', false);
    form.reset();
}

function handleGenerate() {
    if (!isFormValid.value) return;

    const endpoint = form.generate_by_sections
        ? 'generate-by-sections'
        : form.use_ai
          ? 'ia-generate'
          : 'auto-generate';

    form.post(
        `/api/tenant/plannerate/gondolas/${props.gondolaId}/${endpoint}`,
        {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                // Fechar modal
                emit('update:open', false);

                // Resetar formulário
                form.reset(); 
            },
            onError: (errors) => {
                alert(
                    'Erro ao gerar planograma: ' +
                        (Object.values(errors)[0] || 'Erro desconhecido'),
                );
            },
        },
    );
}

</script>

<template>
    <Dialog :open="open" @update:open="handleClose">
        <DialogContent
            class="flex max-h-[90vh] max-w-full flex-col md:max-w-2xl"
        >
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Sparkles class="size-5 text-primary" />
                    Gerar Planograma Automaticamente
                </DialogTitle>
                <DialogDescription>
                    Configure as opções para gerar o planograma automaticamente
                    baseado em vendas, análise ABC e regras de merchandising.
                </DialogDescription>
            </DialogHeader>

            <div
                class="flex-1 space-y-6 overflow-x-hidden overflow-y-auto py-4"
            >
                <GenerationModeSettings :form="form" :permissions="permissions" />

                <AiSettingsSection :form="form" :ai-model-options="props.aiModelOptions" v-if="permissions.can_autogenate_gondola_ia" />

                <div class="border-t" />

                <!-- Seleção de Categoria -->
                <div class="space-y-4 rounded-lg border border-purple-200 p-4">
                    <Label
                        class="text-base font-semibold text-blue-900 dark:text-blue-100"
                    >
                        📦 Categoria (opcional)
                    </Label>
                    <CategorySelect
                        v-model="form.category_id"
                        :disabled="false"
                        :required="false"
                    />
                    <div class="pt-2 text-xs text-muted-foreground">
                        Deixe em branco para gerar incluindo todos os produtos,
                        ou selecione uma categoria específica
                    </div>
                </div>

                <div class="border-t" />
                <StrategySelectionSection :form="form" :strategy-options="props.strategyOptions" />

                <div class="border-t pt-4" />
                <SalesDataSection :form="form" />

                <div class="border-t pt-4" />
                <FacingsSettingsSection :form="form" />

                <div class="border-t pt-4" />
                <AdvancedOptionsSection :form="form" />
            </div>

            <DialogFooter>
                <Button
                    variant="outline"
                    @click="handleClose"
                    :disabled="form.processing"
                >
                    Cancelar
                </Button>
                <Button
                    type="button"
                    class="gap-2"
                    :disabled="!isFormValid || form.processing"
                    @click="handleGenerate"
                >
                    <ActionIconBox v-if="form.processing" variant="default">
                        <Loader2 class="animate-spin" />
                    </ActionIconBox>
                    <ActionIconBox v-else variant="default">
                        <Sparkles />
                    </ActionIconBox>
                    {{ form.processing ? 'Gerando...' : 'Gerar Planograma' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

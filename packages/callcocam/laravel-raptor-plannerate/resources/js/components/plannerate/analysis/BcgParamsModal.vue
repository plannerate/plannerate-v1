<script setup lang="ts">
import { Grid2x2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';
import AnalysisPeriodSelector from './AnalysisPeriodSelector.vue';
import type { BcgAxis, BcgDisplayBy, BcgThresholdMethod } from './bcg/types';

/**
 * Parâmetros da Análise BCG.
 *
 * Estes controles (eixos e nível de classificação) viviam no PaperParamsModal, onde
 * o backend os DESCARTAVA — o usuário escolhia "Margem × Quantidade" e recebia
 * silenciosamente "share × crescimento". Aqui eles são de fato honrados.
 *
 * Note o que NÃO existe aqui: período anterior e limiar de crescimento. A BCG compara
 * duas métricas no MESMO período; quem tem eixo de crescimento é a Análise de Papel.
 */

/** Níveis da hierarquia onde a linha de corte pode ser calculada (espelha BcgAnalysisService::HIERARCHY_LEVELS). */
const CLASSIFY_LEVELS = [
    'segmento_varejista',
    'departamento',
    'subdepartamento',
    'categoria',
    'subcategoria',
] as const;

type ClassifyLevel = (typeof CLASSIFY_LEVELS)[number];

const LEVEL_LABELS: Record<ClassifyLevel, string> = {
    segmento_varejista: 'Segmento Varejista',
    departamento: 'Departamento',
    subdepartamento: 'Subdepartamento',
    categoria: 'Categoria',
    subcategoria: 'Subcategoria',
};

const AXIS_OPTIONS: BcgAxis[] = ['valor', 'quantidade', 'margem'];

/** Profundidade de cada nível (espelha BcgAnalysisService::HIERARCHY_LEVELS). */
const LEVEL_INDEX: Record<ClassifyLevel, number> = {
    segmento_varejista: 0,
    departamento: 1,
    subdepartamento: 2,
    categoria: 3,
    subcategoria: 4,
};

interface FormData {
    table_type: 'sales' | 'monthly_summaries';
    date_from: string;
    date_to: string;
    start_month: string;
    end_month: string;
    x_axis: BcgAxis;
    y_axis: BcgAxis;
    classify_by: ClassifyLevel;
    display_by: BcgDisplayBy;
    threshold_method: BcgThresholdMethod;
}

interface Props {
    open: boolean;
    initialData?: Partial<FormData> | null;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'submit', data: FormData): void;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
    initialData: null,
});

const emit = defineEmits<Emits>();
const { t } = useT();

const buildForm = (data?: Partial<FormData> | null): FormData => ({
    table_type: data?.table_type || 'sales',
    date_from: data?.date_from || '',
    date_to: data?.date_to || '',
    start_month: data?.start_month || '',
    end_month: data?.end_month || '',
    // Preset canônico da planilha VBA: X = quantidade, Y = margem
    x_axis: data?.x_axis || 'quantidade',
    y_axis: data?.y_axis || 'margem',
    classify_by: data?.classify_by || 'categoria',
    display_by: data?.display_by || 'produto',
    threshold_method: data?.threshold_method || 'median',
});

const form = ref<FormData>(buildForm(props.initialData));

watch(
    () => props.initialData,
    (newData) => {
        form.value = buildForm(newData);
    },
    { deep: true, immediate: true },
);

const axisLabel = (axis: BcgAxis): string => t(`plannerate.analysis.bcg_params.axis_${axis}`);
const displayLabel = (mode: BcgDisplayBy): string => t(`plannerate.analysis.bcg_params.display_${mode}`);

/**
 * Opções de "Exibir por": produto (sempre) + os níveis da hierarquia ESTRITAMENTE
 * abaixo do "Classificar por". Exibir num nível igual ou acima do corte deixaria cada
 * grupo sozinho no seu grupo de comparação — o backend valida a mesma regra.
 */
const availableDisplayOptions = computed<BcgDisplayBy[]>(() => {
    const classifyIndex = LEVEL_INDEX[form.value.classify_by];
    const deeperLevels = CLASSIFY_LEVELS.filter((level) => LEVEL_INDEX[level] > classifyIndex);

    return ['produto', ...deeperLevels];
});

// Mudar o "Classificar por" pode invalidar o "Exibir por" atual → cai para 'produto'.
watch(
    () => form.value.classify_by,
    () => {
        if (!availableDisplayOptions.value.includes(form.value.display_by)) {
            form.value.display_by = 'produto';
        }
    },
);

/**
 * Eixos iguais achatam a matriz numa diagonal — só sobrariam os quadrantes alto/alto
 * e baixo/baixo. O backend rejeita, e aqui bloqueamos antes de enviar.
 */
const hasSameAxis = computed(() => form.value.x_axis === form.value.y_axis);

const handleSubmit = () => {
    if (hasSameAxis.value) {
return;
}

    emit('submit', form.value);
    emit('update:open', false);
};

const handleOpenChange = (value: boolean) => {
    emit('update:open', value);
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="z-[1000] max-h-[90vh] max-w-2xl overflow-y-auto">
            <DialogHeader class="pb-3">
                <DialogTitle class="text-base">{{ t('plannerate.analysis.bcg_params.title') }}</DialogTitle>
                <DialogDescription class="text-xs">
                    {{ t('plannerate.analysis.bcg_params.description') }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="handleSubmit">
                <!-- Métricas dos eixos -->
                <div class="space-y-3">
                    <p class="text-xs font-semibold text-foreground">{{ t('plannerate.analysis.bcg_params.axis_title') }}</p>

                    <div class="space-y-1.5">
                        <Label class="text-[10px] text-muted-foreground">{{ t('plannerate.analysis.bcg_params.x_axis') }}</Label>
                        <div class="flex flex-wrap gap-4">
                            <label v-for="option in AXIS_OPTIONS" :key="`x-${option}`" class="flex cursor-pointer items-center gap-2">
                                <input v-model="form.x_axis" type="radio" :value="option" class="rounded" />
                                <span class="text-xs">{{ axisLabel(option) }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-[10px] text-muted-foreground">{{ t('plannerate.analysis.bcg_params.y_axis') }}</Label>
                        <div class="flex flex-wrap gap-4">
                            <label v-for="option in AXIS_OPTIONS" :key="`y-${option}`" class="flex cursor-pointer items-center gap-2">
                                <input v-model="form.y_axis" type="radio" :value="option" class="rounded" />
                                <span class="text-xs">{{ axisLabel(option) }}</span>
                            </label>
                        </div>
                    </div>

                    <p v-if="hasSameAxis" class="rounded-md border border-red-300 bg-red-50 px-2 py-1.5 text-[10px] text-red-700 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300">
                        {{ t('plannerate.analysis.bcg_params.same_axis_error') }}
                    </p>
                </div>

                <!-- Nível de comparação: onde a linha de corte é calculada -->
                <div class="space-y-1.5 border-t pt-3">
                    <Label class="text-xs">{{ t('plannerate.analysis.bcg_params.classify_by') }}</Label>
                    <select
                        v-model="form.classify_by"
                        class="flex h-8 w-full rounded-md border border-input bg-background px-3 py-1 text-xs shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <option v-for="level in CLASSIFY_LEVELS" :key="level" :value="level">
                            {{ LEVEL_LABELS[level] }}
                        </option>
                    </select>
                    <p class="text-[10px] text-muted-foreground">
                        {{ t('plannerate.analysis.bcg_params.classify_by_hint') }}
                    </p>
                </div>

                <!-- Exibir por: produto ou um nível abaixo do corte (restringido conforme o "Classificar por") -->
                <div class="space-y-1.5 border-t pt-3">
                    <Label class="text-xs">{{ t('plannerate.analysis.bcg_params.display_by') }}</Label>
                    <div class="flex flex-wrap gap-4">
                        <label v-for="mode in availableDisplayOptions" :key="`display-${mode}`" class="flex cursor-pointer items-center gap-2">
                            <input v-model="form.display_by" type="radio" :value="mode" class="rounded" />
                            <span class="text-xs">{{ displayLabel(mode) }}</span>
                        </label>
                    </div>
                    <p class="text-[10px] text-muted-foreground">
                        {{ t('plannerate.analysis.bcg_params.display_by_hint') }}
                    </p>
                </div>

                <!-- Método de corte -->
                <div class="space-y-1.5 border-t pt-3">
                    <p class="text-xs font-semibold text-foreground">{{ t('plannerate.analysis.bcg_params.threshold_title') }}</p>
                    <Label class="text-[10px] text-muted-foreground">{{ t('plannerate.analysis.bcg_params.threshold_method') }}</Label>
                    <div class="flex flex-wrap gap-4">
                        <label class="flex cursor-pointer items-center gap-2">
                            <input v-model="form.threshold_method" type="radio" value="median" class="rounded" />
                            <span class="text-xs">{{ t('plannerate.analysis.bcg_params.threshold_median') }}</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2">
                            <input v-model="form.threshold_method" type="radio" value="mean" class="rounded" />
                            <span class="text-xs">{{ t('plannerate.analysis.bcg_params.threshold_mean') }}</span>
                        </label>
                    </div>
                    <p class="text-[10px] text-muted-foreground">
                        {{ t('plannerate.analysis.bcg_params.threshold_hint') }}
                    </p>
                </div>

                <!-- Período (único: a BCG não tem eixo de crescimento) -->
                <div class="space-y-1 border-t pt-3">
                    <p class="text-xs font-semibold text-foreground">{{ t('plannerate.analysis.bcg_params.period_title') }}</p>
                    <p class="text-[10px] text-muted-foreground">{{ t('plannerate.analysis.bcg_params.period_hint') }}</p>
                    <AnalysisPeriodSelector
                        :table-type="form.table_type"
                        :date-from="form.date_from"
                        :date-to="form.date_to"
                        :start-month="form.start_month"
                        :end-month="form.end_month"
                        @update:table-type="form.table_type = $event"
                        @update:date-from="form.date_from = $event"
                        @update:date-to="form.date_to = $event"
                        @update:start-month="form.start_month = $event"
                        @update:end-month="form.end_month = $event"
                    />
                </div>

                <Button type="submit" class="w-full" :disabled="hasSameAxis">
                    <Grid2x2 class="mr-2 size-4" />
                    {{ t('plannerate.analysis.bcg_params.run_analysis') }}
                </Button>
            </form>
        </DialogContent>
    </Dialog>
</template>

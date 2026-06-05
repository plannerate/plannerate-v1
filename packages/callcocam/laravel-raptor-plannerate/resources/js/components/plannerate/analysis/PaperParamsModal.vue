<script setup lang="ts">
import { TrendingUp } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';
import AnalysisPeriodSelector from './AnalysisPeriodSelector.vue';

// ─── Tipos ────────────────────────────────────────────────────────────────────

type HierarchyLevel =
    | 'segmento_varejista'
    | 'departamento'
    | 'subdepartamento'
    | 'categoria'
    | 'subcategoria'
    | 'produto';

interface BCGCombination {
    classifyBy: HierarchyLevel;
    displayBy: HierarchyLevel;
    label: string;
}

interface FormData {
    table_type: 'sales' | 'monthly_summaries';
    date_from: string;
    date_to: string;
    start_month: string;
    end_month: string;
    prev_date_from: string;
    prev_date_to: string;
    prev_start_month: string;
    prev_end_month: string;
    growth_threshold: number;
    x_axis: string;
    y_axis: string;
    classify_by: HierarchyLevel;
    display_by: HierarchyLevel;
}

interface Props {
    open: boolean;
    initialData?: Partial<FormData> | null;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'submit', data: FormData): void;
}

// ─── Constantes de hierarquia (idênticas ao old) ───────────────────────────────

const LEVEL_LABELS: Record<HierarchyLevel, string> = {
    segmento_varejista: 'Segmento Varejista',
    departamento: 'Departamento',
    subdepartamento: 'Subdepartamento',
    categoria: 'Categoria',
    subcategoria: 'Subcategoria',
    produto: 'Produto',
};

const ALL_HIERARCHY_LEVELS = Object.keys(LEVEL_LABELS) as HierarchyLevel[];

const VALID_BCG_COMBINATIONS: BCGCombination[] = [
    { classifyBy: 'segmento_varejista', displayBy: 'departamento', label: 'Classificar por Segmento → Exibir por Departamento' },
    { classifyBy: 'segmento_varejista', displayBy: 'subdepartamento', label: 'Classificar por Segmento → Exibir por Subdepartamento' },
    { classifyBy: 'segmento_varejista', displayBy: 'categoria', label: 'Classificar por Segmento → Exibir por Categoria' },
    { classifyBy: 'segmento_varejista', displayBy: 'subcategoria', label: 'Classificar por Segmento → Exibir por Subcategoria' },
    { classifyBy: 'segmento_varejista', displayBy: 'produto', label: 'Classificar por Segmento → Exibir por Produto' },
    { classifyBy: 'departamento', displayBy: 'subdepartamento', label: 'Classificar por Departamento → Exibir por Subdepartamento' },
    { classifyBy: 'departamento', displayBy: 'categoria', label: 'Classificar por Departamento → Exibir por Categoria' },
    { classifyBy: 'departamento', displayBy: 'produto', label: 'Classificar por Departamento → Exibir por Produto' },
    { classifyBy: 'subdepartamento', displayBy: 'categoria', label: 'Classificar por Subdepartamento → Exibir por Categoria' },
    { classifyBy: 'subdepartamento', displayBy: 'produto', label: 'Classificar por Subdepartamento → Exibir por Produto' },
    { classifyBy: 'categoria', displayBy: 'subcategoria', label: 'Classificar por Categoria → Exibir por Subcategoria' },
    { classifyBy: 'categoria', displayBy: 'produto', label: 'Classificar por Categoria → Exibir por Produto' },
    { classifyBy: 'subcategoria', displayBy: 'produto', label: 'Classificar por Subcategoria → Exibir por Produto' },
];

const AXIS_OPTIONS = ['VALOR DE VENDA', 'VENDA EM QUANTIDADE', 'MARGEM DE CONTRIBUIÇÃO'];

// ─── Setup ────────────────────────────────────────────────────────────────────

const props = withDefaults(defineProps<Props>(), {
    open: false,
    initialData: null,
});

const emit = defineEmits<Emits>();
const { t } = useT();

// ─── Estado ───────────────────────────────────────────────────────────────────

const buildForm = (data?: Partial<FormData> | null): FormData => ({
    table_type: data?.table_type || 'sales',
    date_from: data?.date_from || '',
    date_to: data?.date_to || '',
    start_month: data?.start_month || '',
    end_month: data?.end_month || '',
    prev_date_from: data?.prev_date_from || '',
    prev_date_to: data?.prev_date_to || '',
    prev_start_month: data?.prev_start_month || '',
    prev_end_month: data?.prev_end_month || '',
    growth_threshold: data?.growth_threshold ?? 0,
    x_axis: data?.x_axis || 'VALOR DE VENDA',
    y_axis: data?.y_axis || 'MARGEM DE CONTRIBUIÇÃO',
    classify_by: data?.classify_by || 'categoria',
    display_by: data?.display_by || 'produto',
});

const form = ref<FormData>(buildForm(props.initialData));

const selectedRuleIndex = ref<string>(() => {
    const idx = VALID_BCG_COMBINATIONS.findIndex(
        (r) => r.classifyBy === form.value.classify_by && r.displayBy === form.value.display_by,
    );
    return idx >= 0 ? idx.toString() : '11';
});

// ─── Computeds ───────────────────────────────────────────────────────────────

const availableDisplayOptions = computed<HierarchyLevel[]>(() =>
    VALID_BCG_COMBINATIONS.filter((r) => r.classifyBy === form.value.classify_by).map((r) => r.displayBy),
);

const isCurrentCombinationValid = computed(() =>
    VALID_BCG_COMBINATIONS.some(
        (r) => r.classifyBy === form.value.classify_by && r.displayBy === form.value.display_by,
    ),
);

const currentRuleLabel = computed(() => {
    const rule = VALID_BCG_COMBINATIONS.find(
        (r) => r.classifyBy === form.value.classify_by && r.displayBy === form.value.display_by,
    );
    return rule?.label || t('plannerate.analysis.paper_params.invalid_combination');
});

// ─── Watchers ────────────────────────────────────────────────────────────────

watch(
    () => props.initialData,
    (newData) => {
        form.value = buildForm(newData);
        syncRuleIndex();
    },
    { deep: true, immediate: true },
);

watch(
    () => form.value.classify_by,
    () => {
        const valid = availableDisplayOptions.value;
        if (valid.length > 0 && !valid.includes(form.value.display_by)) {
            form.value.display_by = valid[0];
        }
        syncRuleIndex();
    },
);

watch(() => form.value.display_by, syncRuleIndex);

// ─── Métodos ─────────────────────────────────────────────────────────────────

function syncRuleIndex() {
    const idx = VALID_BCG_COMBINATIONS.findIndex(
        (r) => r.classifyBy === form.value.classify_by && r.displayBy === form.value.display_by,
    );
    if (idx >= 0) selectedRuleIndex.value = idx.toString();
}

function onRuleChange(event: Event) {
    const idx = parseInt((event.target as HTMLSelectElement).value);
    const rule = VALID_BCG_COMBINATIONS[idx];
    if (rule) {
        form.value.classify_by = rule.classifyBy;
        form.value.display_by = rule.displayBy;
    }
}

const handleSubmit = () => {
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
                <DialogTitle class="text-base">{{ t('plannerate.analysis.paper_params.title') }}</DialogTitle>
                <DialogDescription class="text-xs">
                    {{ t('plannerate.analysis.paper_params.description') }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4">

                <!-- Configuração de Hierarquia -->
                <div class="space-y-3">
                    <p class="text-xs font-semibold text-foreground">{{ t('plannerate.analysis.paper_params.hierarchy_title') }}</p>

                    <!-- Atalho: regra pré-definida via native select -->
                    <div class="space-y-1.5">
                        <Label class="text-[10px] text-muted-foreground">{{ t('plannerate.analysis.paper_params.analysis_rule') }}</Label>
                        <select
                            :value="selectedRuleIndex"
                            @change="onRuleChange"
                            class="flex h-8 w-full rounded-md border border-input bg-background px-3 py-1 text-xs shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option
                                v-for="(rule, index) in VALID_BCG_COMBINATIONS"
                                :key="index"
                                :value="index.toString()"
                            >
                                {{ rule.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Seleção manual: Classificar por / Exibir por -->
                    <div class="grid grid-cols-2 gap-3 border-t pt-3">
                        <div class="space-y-1.5">
                            <Label class="text-[10px] text-muted-foreground">{{ t('plannerate.analysis.paper_params.classify_by') }}</Label>
                            <select
                                v-model="form.classify_by"
                                class="flex h-8 w-full rounded-md border border-input bg-background px-3 py-1 text-xs shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option v-for="level in ALL_HIERARCHY_LEVELS" :key="level" :value="level">
                                    {{ LEVEL_LABELS[level] }}
                                </option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <Label class="text-[10px] text-muted-foreground">{{ t('plannerate.analysis.paper_params.display_by') }}</Label>
                            <select
                                v-model="form.display_by"
                                class="flex h-8 w-full rounded-md border border-input bg-background px-3 py-1 text-xs shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option v-for="level in availableDisplayOptions" :key="level" :value="level">
                                    {{ LEVEL_LABELS[level] }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Indicador de validade -->
                    <div class="flex items-center gap-2 text-[10px]">
                        <div
                            class="size-2.5 rounded-full"
                            :class="isCurrentCombinationValid ? 'bg-green-500' : 'bg-red-500'"
                        />
                        <span :class="isCurrentCombinationValid ? 'text-green-700' : 'text-red-600'">
                            {{ isCurrentCombinationValid
                                ? t('plannerate.analysis.paper_params.valid_combination')
                                : t('plannerate.analysis.paper_params.invalid_combination') }}
                        </span>
                    </div>

                    <!-- Preview -->
                    <div class="rounded-md bg-muted/50 px-3 py-2 text-[10px] text-muted-foreground space-y-0.5">
                        <p class="font-medium text-foreground text-xs mb-1">{{ t('plannerate.analysis.paper_params.analysis_preview') }}</p>
                        <p><strong>{{ t('plannerate.analysis.paper_params.grouping') }}:</strong> {{ LEVEL_LABELS[form.classify_by] }}</p>
                        <p><strong>{{ t('plannerate.analysis.paper_params.detail') }}:</strong> {{ LEVEL_LABELS[form.display_by] }}</p>
                        <p><strong>{{ t('plannerate.analysis.paper_params.x_axis') }}:</strong> {{ form.x_axis }}</p>
                        <p><strong>{{ t('plannerate.analysis.paper_params.y_axis') }}:</strong> {{ form.y_axis }}</p>
                    </div>
                </div>

                <!-- Métricas dos Eixos -->
                <div class="space-y-3 border-t pt-3">
                    <p class="text-xs font-semibold text-foreground">{{ t('plannerate.analysis.paper_params.axis_title') }}</p>

                    <!-- Eixo X: radio buttons -->
                    <div class="space-y-1.5">
                        <Label class="text-[10px] text-muted-foreground">{{ t('plannerate.analysis.paper_params.x_axis') }}</Label>
                        <div class="flex flex-wrap gap-4">
                            <label
                                v-for="option in AXIS_OPTIONS"
                                :key="`x-${option}`"
                                class="flex cursor-pointer items-center gap-2"
                            >
                                <input
                                    v-model="form.x_axis"
                                    type="radio"
                                    :value="option"
                                    class="rounded"
                                />
                                <span class="text-xs">{{ option }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Eixo Y: radio buttons -->
                    <div class="space-y-1.5">
                        <Label class="text-[10px] text-muted-foreground">{{ t('plannerate.analysis.paper_params.y_axis') }}</Label>
                        <div class="flex flex-wrap gap-4">
                            <label
                                v-for="option in AXIS_OPTIONS"
                                :key="`y-${option}`"
                                class="flex cursor-pointer items-center gap-2"
                            >
                                <input
                                    v-model="form.y_axis"
                                    type="radio"
                                    :value="option"
                                    class="rounded"
                                />
                                <span class="text-xs">{{ option }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Período Atual -->
                <div class="space-y-1 border-t pt-3">
                    <p class="text-xs font-semibold text-foreground">{{ t('plannerate.analysis.paper_params.current_period') }}</p>
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

                <!-- Período Anterior -->
                <div class="space-y-1 border-t pt-3">
                    <p class="text-xs font-semibold text-foreground">{{ t('plannerate.analysis.paper_params.previous_period') }}</p>
                    <p class="text-[10px] text-muted-foreground">{{ t('plannerate.analysis.paper_params.previous_period_hint') }}</p>
                    <div v-if="form.table_type === 'sales'" class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label class="text-[10px] text-muted-foreground">{{ t('plannerate.analysis.period.start_date') }}</Label>
                            <Input v-model="form.prev_date_from" type="date" class="h-8 text-xs" />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-[10px] text-muted-foreground">{{ t('plannerate.analysis.period.end_date') }}</Label>
                            <Input v-model="form.prev_date_to" type="date" class="h-8 text-xs" />
                        </div>
                    </div>
                    <div v-else class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label class="text-[10px] text-muted-foreground">{{ t('plannerate.analysis.period.start_month') }}</Label>
                            <Input v-model="form.prev_start_month" type="month" class="h-8 text-xs" />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-[10px] text-muted-foreground">{{ t('plannerate.analysis.period.end_month') }}</Label>
                            <Input v-model="form.prev_end_month" type="month" class="h-8 text-xs" />
                        </div>
                    </div>
                </div>

                <!-- Limiar de Crescimento -->
                <div class="space-y-1.5 border-t pt-3">
                    <Label class="text-xs">{{ t('plannerate.analysis.paper_params.growth_threshold') }}</Label>
                    <Input
                        v-model.number="form.growth_threshold"
                        type="number"
                        step="1"
                        class="h-8 text-xs"
                    />
                    <p class="text-[10px] text-muted-foreground">
                        {{ t('plannerate.analysis.paper_params.growth_threshold_hint') }}
                    </p>
                </div>

                <Button type="submit" class="w-full" :disabled="!isCurrentCombinationValid">
                    <TrendingUp class="mr-2 size-4" />
                    {{ t('plannerate.analysis.paper_params.run_analysis') }}
                </Button>
            </form>
        </DialogContent>
    </Dialog>
</template>

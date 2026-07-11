<script setup lang="ts">
import { TrendingUp } from 'lucide-vue-next';
import { ref, watch } from 'vue';
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

/**
 * Parâmetros da Análise de Papel (share × crescimento, dois períodos).
 *
 * Os controles de eixos configuráveis e de hierarquia que existiam aqui foram movidos
 * para o BcgParamsModal: eram enviados ao servidor e DESCARTADOS, então prometiam ao
 * usuário uma análise que ele não recebia. A Análise de Papel é fixa em share ×
 * crescimento — não há eixo a escolher.
 */

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
    /** Limiar fixo de crescimento — null = mediana automática por categoria */
    growth_threshold: number | null;
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
    prev_date_from: data?.prev_date_from || '',
    prev_date_to: data?.prev_date_to || '',
    prev_start_month: data?.prev_start_month || '',
    prev_end_month: data?.prev_end_month || '',
    growth_threshold: data?.growth_threshold ?? null,
});

const form = ref<FormData>(buildForm(props.initialData));

watch(
    () => props.initialData,
    (newData) => {
        form.value = buildForm(newData);
    },
    { deep: true, immediate: true },
);

const handleSubmit = () => {
    // Input numérico vazio vira '' — normaliza para null (mediana automática)
    if (typeof form.value.growth_threshold !== 'number' || Number.isNaN(form.value.growth_threshold)) {
        form.value.growth_threshold = null;
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
                <DialogTitle class="text-base">{{ t('plannerate.analysis.paper_params.title') }}</DialogTitle>
                <DialogDescription class="text-xs">
                    {{ t('plannerate.analysis.paper_params.description') }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="handleSubmit">
                <!-- Período Atual -->
                <div class="space-y-1">
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
                        :placeholder="t('plannerate.analysis.paper_params.growth_threshold_placeholder')"
                    />
                    <p class="text-[10px] text-muted-foreground">
                        {{ t('plannerate.analysis.paper_params.growth_threshold_hint') }}
                    </p>
                </div>

                <Button type="submit" class="w-full">
                    <TrendingUp class="mr-2 size-4" />
                    {{ t('plannerate.analysis.paper_params.run_analysis') }}
                </Button>
            </form>
        </DialogContent>
    </Dialog>
</template>

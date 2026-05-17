<script setup lang="ts">
import { computed } from 'vue';
import MonthRangeFilter from '@/components/filters/MonthRangeFilter.vue';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';

interface Props {
    tableType: 'sales' | 'monthly_summaries';
    dateFrom?: string;
    dateTo?: string;
    startMonth?: string;
    endMonth?: string;
}

interface Emits {
    (e: 'update:tableType', value: 'sales' | 'monthly_summaries'): void;
    (e: 'update:dateFrom', value: string): void;
    (e: 'update:dateTo', value: string): void;
    (e: 'update:startMonth', value: string): void;
    (e: 'update:endMonth', value: string): void;
}

const props = withDefaults(defineProps<Props>(), {
    tableType: 'sales',
    dateFrom: '',
    dateTo: '',
    startMonth: '',
    endMonth: '',
});

const emit = defineEmits<Emits>();
const { t } = useT();

function toMonth(value?: string): string {
    if (!value) {
        return '';
    }

    return value.slice(0, 7);
}

function monthStart(month: string): string {
    return month ? `${month}-01` : '';
}

function monthEnd(month: string): string {
    if (!month) {
        return '';
    }

    const [year, monthValue] = month.split('-').map(Number);
    const lastDay = String(new Date(year, monthValue, 0).getDate()).padStart(2, '0');

    return `${month}-${lastDay}`;
}

const periodLabel = computed(() =>
    props.tableType === 'monthly_summaries'
        ? t('plannerate.analysis.period.monthly_period')
        : t('plannerate.analysis.period.sales_period')
);

const startPickerValue = computed(() => {
    if (props.startMonth) {
        return monthStart(props.startMonth);
    }

    return props.dateFrom || '';
});

const endPickerValue = computed(() => {
    if (props.endMonth) {
        return monthEnd(props.endMonth);
    }

    return props.dateTo || '';
});

function updateStart(value: string): void {
    emit('update:dateFrom', value);
    emit('update:startMonth', toMonth(value));
}

function updateEnd(value: string): void {
    emit('update:dateTo', value);
    emit('update:endMonth', toMonth(value));
}
</script>

<template>
    <div class="space-y-4">
        <!-- Tipo de Tabela -->
        <div class="space-y-1.5">
            <Label class="text-xs">{{ t('plannerate.analysis.period.table_type') }}</Label>
            <div class="flex gap-4">
                <label class="flex items-center gap-2">
                    <input
                        :checked="tableType === 'sales'"
                        type="radio"
                        value="sales"
                        class="rounded"
                        @change="emit('update:tableType', 'sales')"
                    />
                    <span>{{ t('plannerate.analysis.period.sales') }}</span>
                </label>
                <label class="flex items-center gap-2">
                    <input
                        :checked="tableType === 'monthly_summaries'"
                        type="radio"
                        value="monthly_summaries"
                        class="rounded"
                        @change="emit('update:tableType', 'monthly_summaries')"
                    />
                    <span>{{ t('plannerate.analysis.period.monthly_summary') }}</span>
                </label>
            </div>
        </div>

        <div class="space-y-2">
            <MonthRangeFilter
                :label="periodLabel"
                start-name="period_start"
                end-name="period_end"
                :start-value="startPickerValue"
                :end-value="endPickerValue"
                :placeholder="t('plannerate.analysis.period.month_placeholder')"
                @update:start-value="updateStart"
                @update:end-value="updateEnd"
            />
        </div>
    </div>
</template>

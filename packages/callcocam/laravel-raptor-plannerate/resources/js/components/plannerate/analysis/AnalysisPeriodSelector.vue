<script setup lang="ts">
import DayRangeFilter from '@/components/filters/DayRangeFilter.vue';
import MonthRangeFilter from '@/components/filters/MonthRangeFilter.vue';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';

/**
 * Seletor de período para análises do planograma.
 *
 * - Modo 'sales': exibe calendário de dia a dia (data inicial → data final).
 * - Modo 'monthly_summaries': exibe seletor de mês/ano (mês inicial → mês final).
 */

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

// ─── Helpers ───────────────────────────────────────────────────────────────

/** Extrai o componente YYYY-MM de uma string YYYY-MM-DD. */
function toMonth(value?: string): string {
    return value ? value.slice(0, 7) : '';
}

/** Retorna o primeiro dia do mês: YYYY-MM-DD. */
function monthStart(month: string): string {
    return month ? `${month}-01` : '';
}

/** Retorna o último dia do mês: YYYY-MM-DD. */
function monthEnd(month: string): string {
    if (!month) {
return '';
}

    const [year, monthValue] = month.split('-').map(Number);
    const lastDay = String(new Date(year, monthValue, 0).getDate()).padStart(2, '0');

    return `${month}-${lastDay}`;
}

// ─── Handlers — modo Sales (dia a dia) ─────────────────────────────────────

/** Atualiza a data inicial no modo Sales. */
function updateSalesStart(value: string): void {
    emit('update:dateFrom', value);
}

/** Atualiza a data final no modo Sales. */
function updateSalesEnd(value: string): void {
    emit('update:dateTo', value);
}

// ─── Handlers — modo Monthly Summaries ─────────────────────────────────────

/** Valor exibido no picker mensal: prefere startMonth, fallback para dateFrom. */
function monthlyStartValue(): string {
    if (props.startMonth) {
return monthStart(props.startMonth);
}

    return props.dateFrom || '';
}

/** Valor exibido no picker mensal: prefere endMonth, fallback para dateTo. */
function monthlyEndValue(): string {
    if (props.endMonth) {
return monthEnd(props.endMonth);
}

    return props.dateTo || '';
}

/** Atualiza start no modo Monthly: emite dateFrom e startMonth. */
function updateMonthlyStart(value: string): void {
    emit('update:dateFrom', value);
    emit('update:startMonth', toMonth(value));
}

/** Atualiza end no modo Monthly: emite dateTo e endMonth. */
function updateMonthlyEnd(value: string): void {
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
                <label class="flex cursor-pointer items-center gap-2">
                    <input
                        :checked="tableType === 'sales'"
                        type="radio"
                        value="sales"
                        class="rounded"
                        @change="emit('update:tableType', 'sales')"
                    />
                    <span class="text-sm">{{ t('plannerate.analysis.period.sales') }}</span>
                </label>
                <label class="flex cursor-pointer items-center gap-2">
                    <input
                        :checked="tableType === 'monthly_summaries'"
                        type="radio"
                        value="monthly_summaries"
                        class="rounded"
                        @change="emit('update:tableType', 'monthly_summaries')"
                    />
                    <span class="text-sm">{{ t('plannerate.analysis.period.monthly_summary') }}</span>
                </label>
            </div>
        </div>

        <!-- Seletor de período -->
        <div class="space-y-2">
            <!-- Modo Sales: calendário dia a dia -->
            <DayRangeFilter
                v-if="tableType === 'sales'"
                :label="t('plannerate.analysis.period.sales_period')"
                start-name="date_from"
                end-name="date_to"
                :start-value="dateFrom"
                :end-value="dateTo"
                :placeholder="t('plannerate.analysis.period.date_placeholder')"
                @update:start-value="updateSalesStart"
                @update:end-value="updateSalesEnd"
            />

            <!-- Modo Monthly Summaries: seletor mês/ano -->
            <MonthRangeFilter
                v-else
                :label="t('plannerate.analysis.period.monthly_period')"
                start-name="period_start"
                end-name="period_end"
                :start-value="monthlyStartValue()"
                :end-value="monthlyEndValue()"
                :placeholder="t('plannerate.analysis.period.month_placeholder')"
                @update:start-value="updateMonthlyStart"
                @update:end-value="updateMonthlyEnd"
            />
        </div>
    </div>
</template>

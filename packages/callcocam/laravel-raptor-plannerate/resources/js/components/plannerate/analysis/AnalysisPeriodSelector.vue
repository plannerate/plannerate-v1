<script setup lang="ts">
import { Calendar, Filter } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
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

withDefaults(defineProps<Props>(), {
    tableType: 'sales',
    dateFrom: '',
    dateTo: '',
    startMonth: '',
    endMonth: '',
});

const emit = defineEmits<Emits>();
const { t } = useT();
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

        <!-- Filtros de Período - Sales -->
        <div v-if="tableType === 'sales'" class="space-y-2">
            <div class="space-y-1.5">
                <Label class="flex items-center gap-1.5 text-xs">
                    <Filter class="size-3.5" />
                    {{ t('plannerate.analysis.period.sales_period') }}
                </Label>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label class="text-[10px] text-muted-foreground flex items-center gap-1">
                            <Calendar class="size-3" />
                            {{ t('plannerate.analysis.period.start_date') }}
                        </Label>
                        <Input
                            :model-value="dateFrom"
                            type="date"
                            :placeholder="t('plannerate.analysis.period.date_placeholder')"
                            class="h-8 text-xs"
                            @update:model-value="emit('update:dateFrom', $event)"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-[10px] text-muted-foreground flex items-center gap-1">
                            <Calendar class="size-3" />
                            {{ t('plannerate.analysis.period.end_date') }}
                        </Label>
                        <Input
                            :model-value="dateTo"
                            type="date"
                            :placeholder="t('plannerate.analysis.period.date_placeholder')"
                            class="h-8 text-xs"
                            @update:model-value="emit('update:dateTo', $event)"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros de Período - Monthly Summaries -->
        <div v-if="tableType === 'monthly_summaries'" class="space-y-2">
            <div class="space-y-1.5">
                <Label class="flex items-center gap-1.5 text-xs">
                    <Filter class="size-3.5" />
                    {{ t('plannerate.analysis.period.monthly_period') }}
                </Label>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label class="text-[10px] text-muted-foreground flex items-center gap-1">
                            <Calendar class="size-3" />
                            {{ t('plannerate.analysis.period.start_month') }}
                        </Label>
                        <Input
                            :model-value="startMonth"
                            type="month"
                            :placeholder="t('plannerate.analysis.period.month_placeholder')"
                            class="h-8 text-xs"
                            @update:model-value="emit('update:startMonth', $event)"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-[10px] text-muted-foreground flex items-center gap-1">
                            <Calendar class="size-3" />
                            {{ t('plannerate.analysis.period.end_month') }}
                        </Label>
                        <Input
                            :model-value="endMonth"
                            type="month"
                            :placeholder="t('plannerate.analysis.period.month_placeholder')"
                            class="h-8 text-xs"
                            @update:model-value="emit('update:endMonth', $event)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

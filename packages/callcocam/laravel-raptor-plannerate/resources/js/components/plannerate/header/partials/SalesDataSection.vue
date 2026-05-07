<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Switch } from '@/components/ui/switch';
import { useT } from '@/composables/useT';

interface SalesDataFormState {
    use_existing_analysis: boolean;
    table_type: 'sales' | 'monthly_summaries';
    start_date?: string;
    end_date?: string;
}

defineProps<{
    form: SalesDataFormState;
}>();

const { t } = useT();
</script>

<template>
    <div class="space-y-3">
        <Label class="text-base font-semibold">{{ t('plannerate.header.sales_data.title') }}</Label>

        <div class="flex items-center justify-between">
            <div class="space-y-0.5">
                <Label for="use-existing">{{ t('plannerate.header.sales_data.use_existing') }}</Label>
                <div class="text-sm text-muted-foreground">
                    {{ t('plannerate.header.sales_data.use_existing_hint') }}
                </div>
            </div>
            <Switch id="use-existing" v-model="form.use_existing_analysis" />
        </div>

        <div v-if="!form.use_existing_analysis" class="space-y-3 pt-2">
            <div class="space-y-2">
                <Label>{{ t('plannerate.header.sales_data.data_type') }}</Label>
                <RadioGroup v-model="form.table_type">
                    <div class="flex items-center space-y-0 space-x-3">
                        <RadioGroupItem id="monthly" value="monthly_summaries" />
                        <Label for="monthly" class="cursor-pointer font-normal">
                            {{ t('plannerate.header.sales_data.monthly_sales') }}
                        </Label>
                    </div>
                    <div class="flex items-center space-y-0 space-x-3">
                        <RadioGroupItem id="daily" value="sales" />
                        <Label for="daily" class="cursor-pointer font-normal">
                            {{ t('plannerate.header.sales_data.daily_sales') }}
                        </Label>
                    </div>
                </RadioGroup>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                    <Label for="start-date">{{ t('plannerate.header.sales_data.start_date') }}</Label>
                    <Input id="start-date" v-model="form.start_date" type="date" />
                </div>
                <div class="space-y-2">
                    <Label for="end-date">{{ t('plannerate.header.sales_data.end_date') }}</Label>
                    <Input id="end-date" v-model="form.end_date" type="date" />
                </div>
            </div>
        </div>
    </div>
</template>

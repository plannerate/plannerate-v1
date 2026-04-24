<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Switch } from '@/components/ui/switch';

interface SalesDataFormState {
    use_existing_analysis: boolean;
    table_type: 'sales' | 'monthly_summaries';
    start_date?: string;
    end_date?: string;
}

defineProps<{
    form: SalesDataFormState;
}>();
</script>

<template>
    <div class="space-y-3">
        <Label class="text-base font-semibold">Dados de Vendas</Label>

        <div class="flex items-center justify-between">
            <div class="space-y-0.5">
                <Label for="use-existing">Usar análise ABC existente</Label>
                <div class="text-sm text-muted-foreground">
                    Usa a análise ABC já calculada previamente
                </div>
            </div>
            <Switch id="use-existing" v-model="form.use_existing_analysis" />
        </div>

        <div v-if="!form.use_existing_analysis" class="space-y-3 pt-2">
            <div class="space-y-2">
                <Label>Tipo de Dados</Label>
                <RadioGroup v-model="form.table_type">
                    <div class="flex items-center space-y-0 space-x-3">
                        <RadioGroupItem id="monthly" value="monthly_summaries" />
                        <Label for="monthly" class="cursor-pointer font-normal">
                            Vendas Mensais (agregadas, mais rápido)
                        </Label>
                    </div>
                    <div class="flex items-center space-y-0 space-x-3">
                        <RadioGroupItem id="daily" value="sales" />
                        <Label for="daily" class="cursor-pointer font-normal">
                            Vendas Diárias (detalhadas, mais lento)
                        </Label>
                    </div>
                </RadioGroup>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                    <Label for="start-date">Data Início</Label>
                    <Input id="start-date" v-model="form.start_date" type="date" />
                </div>
                <div class="space-y-2">
                    <Label for="end-date">Data Fim</Label>
                    <Input id="end-date" v-model="form.end_date" type="date" />
                </div>
            </div>
        </div>
    </div>
</template>

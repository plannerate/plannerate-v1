<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */

import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';

interface StrategyOption {
    value: string;
    label: string;
    description: string;
}

interface StrategyFormState {
    strategy: string;
}

defineProps<{
    form: StrategyFormState;
    strategyOptions: StrategyOption[];
}>();
</script>

<template>
    <div class="space-y-3">
        <Label class="text-base font-semibold">Estratégia de Otimização</Label>
        <RadioGroup v-model="form.strategy">
            <div
                v-for="option in strategyOptions"
                :key="option.value"
                class="flex items-start space-y-0 space-x-3"
            >
                <RadioGroupItem :id="`strategy-${option.value}`" :value="option.value" />
                <Label :for="`strategy-${option.value}`" class="flex-1 cursor-pointer font-normal">
                    <div class="font-semibold">{{ option.label }}</div>
                    <div class="text-sm text-muted-foreground">{{ option.description }}</div>
                </Label>
            </div>
        </RadioGroup>
    </div>
</template>

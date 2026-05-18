<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import {
    Stepper,
    StepperDescription,
    StepperIndicator,
    StepperItem,
    StepperSeparator,
    StepperTitle,
    StepperTrigger,
} from '@/components/ui/stepper';
import type { WizardStep } from './types';

const props = defineProps<{
    currentStep: 1 | 2 | 3;
    steps: WizardStep[];
}>();

const emit = defineEmits<{
    navigate: [step: 1 | 2 | 3];
}>();

function canNavigate(step: number): boolean {
    return step <= props.currentStep;
}
</script>

<template>
    <Stepper :model-value="currentStep" class="flex w-full items-start gap-2">
        <template v-for="(step, index) in steps" :key="step.step">
            <StepperItem
                class="relative flex flex-1 flex-col items-center gap-1"
                :step="step.step"
            >
                <StepperTrigger
                    as="div"
                    class="flex cursor-pointer flex-col items-center gap-1"
                    :class="{ 'cursor-default opacity-60': !canNavigate(step.step) }"
                    @click="canNavigate(step.step) ? emit('navigate', step.step) : undefined"
                >
                    <StepperIndicator>
                        <Check v-if="step.step < currentStep" class="size-4" />
                        <span v-else>{{ step.step }}</span>
                    </StepperIndicator>
                    <div class="text-center">
                        <StepperTitle class="text-sm font-medium">{{ step.label }}</StepperTitle>
                        <StepperDescription v-if="step.description" class="text-xs text-muted-foreground">
                            {{ step.description }}
                        </StepperDescription>
                    </div>
                </StepperTrigger>
                <StepperSeparator
                    v-if="index < steps.length - 1"
                    class="absolute top-4 left-[calc(50%+1.5rem)] right-[calc(-50%+1.5rem)] h-px bg-border"
                />
            </StepperItem>
        </template>
    </Stepper>
</template>

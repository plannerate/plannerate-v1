<script setup lang="ts">
import { Check } from 'lucide-vue-next';
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

function isCompleted(step: number): boolean {
    return step < props.currentStep;
}

function isCurrent(step: number): boolean {
    return step === props.currentStep;
}
</script>

<template>
    <div class="grid w-full grid-cols-1 gap-2 sm:grid-cols-3">
        <button
            v-for="step in steps"
            :key="step.step"
            type="button"
            class="group flex min-h-20 items-start gap-3 rounded-lg border bg-card p-3 text-left transition"
            :class="{
                'border-primary/70 bg-primary/5 shadow-sm': isCurrent(
                    step.step,
                ),
                'border-border hover:border-primary/40 hover:bg-muted/30':
                    isCompleted(step.step),
                'cursor-not-allowed border-border/70 bg-muted/20 opacity-70':
                    !canNavigate(step.step),
            }"
            :disabled="!canNavigate(step.step)"
            @click="emit('navigate', step.step)"
        >
            <span
                class="mt-0.5 inline-flex size-8 shrink-0 items-center justify-center rounded-full border text-sm font-semibold transition"
                :class="{
                    'border-primary bg-primary text-primary-foreground':
                        isCurrent(step.step),
                    'border-primary/20 bg-primary/10 text-primary': isCompleted(
                        step.step,
                    ),
                    'border-border bg-background text-muted-foreground':
                        !canNavigate(step.step),
                }"
            >
                <Check v-if="isCompleted(step.step)" class="size-4" />
                <span v-else>{{ step.step }}</span>
            </span>

            <span class="min-w-0 flex-1">
                <span
                    class="block text-[11px] leading-none font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Etapa {{ step.step }}
                </span>
                <span class="mt-1 block truncate text-sm font-semibold">
                    {{ step.label }}
                </span>
                <span
                    v-if="step.description"
                    class="mt-1 block truncate text-xs text-muted-foreground"
                >
                    {{ step.description }}
                </span>
            </span>
        </button>
    </div>
</template>

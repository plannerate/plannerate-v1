<script setup lang="ts">
import { useVModel } from '@vueuse/core';
import type { HTMLAttributes } from 'vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type StatusOption = {
    value: string;
    label: string;
};

const props = withDefaults(
    defineProps<{
        id: string;
        name: string;
        label: string;
        options: StatusOption[];
        required?: boolean;
        error?: string;
        hint?: string;
        modelValue?: string;
        defaultValue?: string;
        disabled?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    {
        required: false,
        error: '',
        hint: '',
        modelValue: undefined,
        defaultValue: undefined,
        disabled: false,
        class: undefined,
    },
);

const emits = defineEmits<{
    (e: 'update:modelValue', payload: string): void;
}>();

const modelValue = useVModel(props, 'modelValue', emits, {
    passive: true,
    defaultValue: props.defaultValue ?? props.options[0]?.value ?? '',
});
</script>

<template>
    <div :class="cn('flex flex-col gap-y-2', props.class)">
        <Label :for="id">
            {{ label }}
            <span v-if="required" class="text-destructive">*</span>
        </Label>

        <div :id="id" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <label
                v-for="option in options"
                :key="option.value"
                class="flex cursor-pointer items-center justify-center rounded-lg border border-border bg-background px-3 py-2 text-sm font-medium transition-colors hover:bg-muted/40 has-checked:border-primary/50 has-checked:bg-primary/10 has-checked:text-primary"
            >
                <input
                    v-model="modelValue"
                    :name="name"
                    :value="option.value"
                    :required="required"
                    :disabled="disabled"
                    type="radio"
                    class="sr-only"
                />
                <span>{{ option.label }}</span>
            </label>
        </div>

        <p v-if="hint" class="text-sm text-muted-foreground">{{ hint }}</p>
        <InputError :message="error" />
    </div>
</template>

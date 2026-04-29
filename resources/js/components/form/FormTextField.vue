<script setup lang="ts">
import { useVModel } from '@vueuse/core';
import type { HTMLAttributes } from 'vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        id: string;
        name: string;
        label: string;
        type?: string;
        required?: boolean;
        error?: string;
        hint?: string;
        modelValue?: string | number;
        defaultValue?: string | number;
        placeholder?: string;
        disabled?: boolean;
        class?: HTMLAttributes['class'];
        min?: string | number;
        max?: string | number;
        step?: string | number;
    }>(),
    {
        type: 'text',
        required: false,
        error: '',
        hint: '',
        modelValue: undefined,
        defaultValue: undefined,
        placeholder: '',
        disabled: false,
        class: undefined,
        min: undefined,
        max: undefined,
        step: undefined,
    },
);

const emits = defineEmits<{
    (e: 'update:modelValue', payload: string | number): void;
}>();

const modelValue = useVModel(props, 'modelValue', emits, {
    passive: true,
    defaultValue: props.defaultValue,
});
</script>

<template>
    <div :class="cn('flex flex-col gap-y-1', props.class)">
        <Label :for="id">
            {{ label }}
            <slot name="label-extra">
                <span v-if="required" class="text-destructive">*</span>
            </slot>
        </Label>

        <input
            :id="id"
            v-model="modelValue"
            :name="name"
            :type="type"
            :required="required"
            :placeholder="placeholder"
            :disabled="disabled"
            :min="min"
            :max="max"
            :step="step"
            class="h-9 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50"
        />

        <slot name="help">
            <p v-if="hint" class="text-sm text-muted-foreground">{{ hint }}</p>
        </slot>

        <InputError :message="error" />

        <slot name="after" />
    </div>
</template>

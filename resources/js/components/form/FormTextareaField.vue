<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { useVModel } from '@vueuse/core';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        id: string;
        name: string;
        label: string;
        required?: boolean;
        error?: string;
        hint?: string;
        modelValue?: string;
        defaultValue?: string;
        placeholder?: string;
        disabled?: boolean;
        rows?: number;
        class?: HTMLAttributes['class'];
    }>(),
    {
        required: false,
        error: '',
        hint: '',
        modelValue: undefined,
        defaultValue: undefined,
        placeholder: '',
        disabled: false,
        rows: 3,
        class: undefined,
    },
);

const emits = defineEmits<{
    (e: 'update:modelValue', payload: string): void;
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

        <textarea
            :id="id"
            v-model="modelValue"
            :name="name"
            :rows="rows"
            :required="required"
            :placeholder="placeholder"
            :disabled="disabled"
            class="min-h-[80px] w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50"
        />

        <slot name="help">
            <p v-if="hint" class="text-sm text-muted-foreground">{{ hint }}</p>
        </slot>

        <InputError :message="error" />

        <slot name="after" />
    </div>
</template>

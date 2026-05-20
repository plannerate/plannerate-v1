<script setup lang="ts">
import { useVModel } from '@vueuse/core';
import type { HTMLAttributes } from 'vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        id: string;
        name: string;
        label: string;
        required?: boolean;
        error?: string;
        hint?: string;
        modelValue?: boolean;
        defaultValue?: boolean;
        disabled?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    {
        required: false,
        error: '',
        hint: '',
        modelValue: undefined,
        defaultValue: false,
        disabled: false,
        class: undefined,
    },
);

const emits = defineEmits<{
    (e: 'update:modelValue', payload: boolean): void;
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
            <span v-if="required" class="text-destructive">*</span>
        </Label>

        <div
            class="flex h-9 items-center gap-3 rounded-lg border border-input bg-background px-3"
        >
            <Switch
                :id="id"
                v-model:model-value="modelValue"
                :disabled="disabled"
            />
            <span class="text-sm text-muted-foreground">
                <slot>{{ modelValue ? 'Sim' : 'Não' }}</slot>
            </span>
        </div>

        <input type="hidden" :name="name" :value="modelValue ? '1' : '0'" />

        <p v-if="hint" class="text-sm text-muted-foreground">{{ hint }}</p>
        <InputError :message="error" />
    </div>
</template>

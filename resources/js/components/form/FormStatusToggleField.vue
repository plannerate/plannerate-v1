<script setup lang="ts">
import { useVModel } from '@vueuse/core';
import { computed } from 'vue';
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
        modelValue?: string;
        defaultValue?: string;
        disabled?: boolean;
        draftValue?: string;
        publishedValue?: string;
        checkedLabel?: string;
        uncheckedLabel?: string;
        class?: HTMLAttributes['class'];
    }>(),
    {
        required: false,
        error: '',
        hint: '',
        modelValue: undefined,
        defaultValue: undefined,
        disabled: false,
        draftValue: 'draft',
        publishedValue: 'published',
        checkedLabel: 'Sim',
        uncheckedLabel: 'Não',
        class: undefined,
    },
);

const emits = defineEmits<{
    (e: 'update:modelValue', payload: string): void;
}>();

const modelValue = useVModel(props, 'modelValue', emits, {
    passive: true,
    defaultValue: props.defaultValue ?? props.draftValue,
});

const checked = computed({
    get: () => modelValue.value === props.publishedValue,
    set: (value: boolean) => {
        modelValue.value = value ? props.publishedValue : props.draftValue;
    },
});
</script>

<template>
    <div :class="cn('flex flex-col gap-y-2', props.class)">
        <Label :for="id">
            {{ label }}
            <span v-if="required" class="text-destructive">*</span>
        </Label>

        <div
            class="flex h-9 items-center gap-2 rounded-lg border border-input bg-background px-3"
        >
            <Switch :id="id" v-model:checked="checked" :disabled="disabled" />
            <span class="text-sm text-muted-foreground">
                {{ checked ? checkedLabel : uncheckedLabel }}
            </span>
        </div>

        <input
            type="hidden"
            :name="name"
            :value="modelValue"
            :required="required"
        />

        <p v-if="hint" class="text-sm text-muted-foreground">{{ hint }}</p>
        <InputError :message="error" />
    </div>
</template>

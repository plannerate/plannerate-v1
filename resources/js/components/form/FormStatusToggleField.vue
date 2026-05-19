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
    <div :class="cn('flex w-full', props.class)">
        <Label :for="id" class="flex w-full flex-col items-start gap-y-2 text-left">
            <span class="inline-flex items-center gap-1">
                <span>{{ label }}</span>
                <span v-if="required" class="text-destructive">*</span>
            </span>

            <div class="flex h-10 items-center gap-2 rounded-lg border border-input bg-background px-3 w-full">
                <button
                    type="button"
                    class="mr-auto text-left text-sm text-muted-foreground disabled:cursor-not-allowed"
                    :disabled="disabled"
                    @click="checked = !checked"
                >
                    {{ checked ? checkedLabel : uncheckedLabel }}
                </button>
                <Switch :id="id" v-model:model-value="checked" :disabled="disabled" />
            </div>
        </Label>
        <input type="hidden" :name="name" :value="modelValue" :required="required" />

        <p v-if="hint" class="text-sm text-muted-foreground">{{ hint }}</p>
        <InputError :message="error" />
    </div>
</template>

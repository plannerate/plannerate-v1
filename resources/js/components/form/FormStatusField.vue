<script setup lang="ts">
import { useVModel } from '@vueuse/core';
import { Check } from 'lucide-vue-next';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
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

const canUseToggle = computed(() => props.options.length === 2);

const uncheckedOption = computed(() => props.options[0]);

const checkedOption = computed(() => props.options[1]);

const toggleChecked = computed({
    get: () => modelValue.value === checkedOption.value?.value,
    set: (value: boolean) => {
        modelValue.value = value ? checkedOption.value?.value ?? '' : uncheckedOption.value?.value ?? '';
    },
});
</script>

<template>
    <div :class="cn('flex flex-col gap-y-2', props.class)">
        <Label :for="id" class="flex w-full flex-col items-start gap-y-2 text-left">
            <span class="inline-flex items-center gap-1">
                <span>{{ label }}</span>
                <span v-if="required" class="text-destructive">*</span>
            </span>
        </Label>

        <div
            v-if="canUseToggle"
            class="flex h-10 items-center gap-2 rounded-2xl border border-input bg-background px-3 w-full"
        >
            <button
                type="button"
                class="mr-auto text-left text-sm text-muted-foreground disabled:cursor-not-allowed"
                :disabled="disabled"
                @click="toggleChecked = !toggleChecked"
            >
                {{ toggleChecked ? checkedOption?.label : uncheckedOption?.label }}
            </button>
            <Switch :id="id" v-model:model-value="toggleChecked" :disabled="disabled" />
        </div>

        <div v-else :id="id" class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
            <label
                v-for="option in options"
                :key="option.value"
                :class="[
                    'flex min-h-10 items-center justify-start rounded-2xl border border-border bg-background px-3 py-2 text-left text-sm font-medium transition-colors',
                    'hover:bg-muted/40 has-checked:border-primary/50 has-checked:bg-primary/10 has-checked:text-primary has-focus-visible:ring-2 has-focus-visible:ring-ring has-focus-visible:ring-offset-2',
                    disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
                ]"
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
                <span
                    class="mr-2 inline-flex size-4 items-center justify-center rounded-full border"
                    :class="modelValue === option.value ? 'border-primary bg-primary text-primary-foreground' : 'border-muted-foreground/40 text-transparent'"
                >
                    <Check class="size-3" />
                </span>
                <span>{{ option.label }}</span>
            </label>
        </div>

        <input v-if="canUseToggle" type="hidden" :name="name" :value="modelValue" :required="required" />

        <p v-if="hint" class="text-sm text-muted-foreground">{{ hint }}</p>
        <InputError :message="error" />
    </div>
</template>

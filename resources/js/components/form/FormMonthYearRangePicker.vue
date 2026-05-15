<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import InputError from '@/components/InputError.vue';
import MonthRangeFilter from '@/components/filters/MonthRangeFilter.vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        startName: string;
        endName: string;
        label: string;
        startValue?: string | null;
        endValue?: string | null;
        startError?: string;
        endError?: string;
        placeholder?: string;
        class?: HTMLAttributes['class'];
    }>(),
    {
        startValue: null,
        endValue: null,
        startError: '',
        endError: '',
        placeholder: 'Selecionar mês/ano',
        class: undefined,
    },
);
</script>

<template>
    <div :class="cn('flex flex-col gap-y-1', props.class)">
        <MonthRangeFilter
            :label="props.label"
            :start-name="props.startName"
            :end-name="props.endName"
            :start-value="props.startValue"
            :end-value="props.endValue"
            :placeholder="props.placeholder"
        />
        <InputError v-if="props.startError" :message="props.startError" />
        <InputError
            v-if="props.endError && props.endError !== props.startError"
            :message="props.endError"
        />
    </div>
</template>

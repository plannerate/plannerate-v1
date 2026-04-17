<script setup lang="ts">
import { computed } from 'vue'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { X } from 'lucide-vue-next'

interface Option {
    id: string
    name: string
    [key: string]: any
}

interface Props {
    modelValue?: string | null
    placeholder?: string
    options?: Option[]
    disabled?: boolean
    label?: string
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    placeholder: 'Selecionar...',
    options: () => [],
    disabled: false,
    label: undefined,
})

const emit = defineEmits<{
    (e: 'update:modelValue', value: string | null): void
}>()

const hasValue = computed(() => !!props.modelValue)

const clearValue = () => {
    emit('update:modelValue', null)
}
</script>

<template>
    <div class="space-y-2">
        <label v-if="label" class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
            {{ label }}
        </label>
        <div class="relative">
            <Select :model-value="modelValue || ''" @update:model-value="(val: any) => emit('update:modelValue', val || null)" :disabled="disabled">
                <SelectTrigger class="pr-10">
                    <SelectValue :placeholder="placeholder" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="option in options" :key="option.id" :value="option.id">
                        {{ option.name }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <!-- Clear button -->
            <button
                v-if="hasValue"
                type="button"
                @click="clearValue"
                :disabled="disabled"
                class="absolute right-3 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                title="Limpar seleção"
            >
                <X class="h-4 w-4" />
            </button>
        </div>
    </div>
</template>

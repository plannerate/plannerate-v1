<template>
    <div class="relative">
        <Search
            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <input
            type="text"
            :value="modelValue"
            @input="
                emit(
                    'update:modelValue',
                    ($event.target as HTMLInputElement).value,
                )
            "
            placeholder="Buscar produtos..."
            :disabled="disabled"
            class="w-full rounded-md border border-input bg-background py-2 pr-3 pl-9 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
        />
    </div>
</template>

<script setup lang="ts">
import { Search } from 'lucide-vue-next';

interface Props {
    modelValue: string;
    disabled?: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

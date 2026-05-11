<script setup lang="ts">
import { useT } from '@/composables/useT';

const props = defineProps<{
    modelValue: string[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

const options = ['string', 'alnum', 'decimal', 'integer', 'date', 'document', 'boolean'];
const { t } = useT();

function toggleTransform(transform: string): void {
    const selected = new Set(props.modelValue);

    if (selected.has(transform)) {
        selected.delete(transform);
    } else {
        selected.add(transform);
    }

    emit('update:modelValue', Array.from(selected));
}
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <button
            v-for="option in options"
            :key="option"
            type="button"
            class="h-7 rounded-md border px-2 text-xs transition"
            :class="
                props.modelValue.includes(option)
                    ? 'border-primary/60 bg-primary/10 text-primary'
                    : 'border-border bg-background text-muted-foreground hover:bg-muted hover:text-foreground'
            "
            @click="toggleTransform(option)"
        >
            {{ t(`app.landlord.integration_apis.transforms.${option}`) }}
        </button>
    </div>
</template>

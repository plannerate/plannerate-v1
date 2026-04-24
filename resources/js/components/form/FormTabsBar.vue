<script setup lang="ts">
type Tab = {
    key: string;
    label: string;
};

defineProps<{
    tabs: Tab[];
    modelValue: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

function setTab(key: string): void {
    emit('update:modelValue', key);
}
</script>

<template>
    <div class="mb-6 flex flex-wrap gap-2 rounded-lg border border-border p-2">
        <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            class="rounded-md px-3 py-2 text-sm font-medium transition"
            :class="modelValue === tab.key ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
            @click="setTab(tab.key)"
        >
            {{ tab.label }}
        </button>
    </div>
</template>

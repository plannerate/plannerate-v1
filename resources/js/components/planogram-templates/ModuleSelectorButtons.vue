<script setup lang="ts">
const props = defineProps<{
    currentModule: number;
    maxModules?: number;
}>();

const emit = defineEmits<{
    select: [moduleCount: number];
}>();
</script>

<template>
    <button
        v-for="n in props.maxModules ?? 20"
        :key="n"
        type="button"
        class="rounded-md border px-3 py-1.5 text-sm font-medium transition"
        :class="
            props.currentModule === n
                ? 'border-primary bg-primary text-primary-foreground'
                : 'border-border bg-background text-foreground hover:border-primary/60 hover:bg-muted/30'
        "
        @click="emit('select', n)"
    >
        {{ n }} módulo{{ n > 1 ? 's' : '' }}
    </button>
</template>

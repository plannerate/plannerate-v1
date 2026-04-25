<script setup lang="ts">
defineProps<{
    label: string;
    description?: string | null;
}>();
</script>

<template>
    <div class="flex flex-col gap-0.5">
        <span class="text-sm font-medium">{{ label }}</span>
        <span v-if="description" class="text-xs text-muted-foreground">{{ description }}</span>
    </div>
</template>

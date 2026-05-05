<script setup lang="ts">
withDefaults(
    defineProps<{
        src?: string | null;
        alt?: string;
    }>(),
    {
        alt: '',
    },
);
</script>

<template>
    <img
        v-if="src"
        :src="src"
        :alt="alt"
        class="h-16 w-16 rounded-md border border-border object-contain"
        loading="lazy"
    />
    <div v-else class="h-10 w-10 rounded-md border border-dashed border-border bg-muted/30" />
</template>

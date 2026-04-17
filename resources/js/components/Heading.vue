<script setup lang="ts">
interface Props {
    title: string;
    description?: string;
    size?: 'default' | 'sm';
}

withDefaults(defineProps<Props>(), {
    size: 'default',
});
</script>

<template>
    <div v-if="size === 'default'" class="mb-8 space-y-0.5">
        <h2 class="text-xl font-semibold tracking-tight">{{ title }}</h2>
        <p v-if="description" class="text-sm text-muted-foreground">
            {{ description }}
        </p>
    </div>
    <header v-else>
        <h3 class="mb-0.5 text-base font-medium">{{ title }}</h3>
        <p v-if="description" class="text-sm text-muted-foreground">
            {{ description }}
        </p>
    </header>
</template>

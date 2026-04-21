<script setup lang="ts">
withDefaults(
    defineProps<{
        alt?: string;
    }>(),
    {
        alt: 'Plannerate',
    },
);
</script>

<template>
    <span class="inline-flex items-center" v-bind="$attrs">
        <img
            src="/img/marcadark.png"
            :alt="alt"
            class="h-full w-auto max-w-none object-contain dark:hidden"
        />
        <img
            src="/img/marcadark.png"
            :alt="alt"
            class="hidden h-full w-auto max-w-none object-contain dark:block"
        />
    </span>
</template>

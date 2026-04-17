<script setup lang="ts">
withDefaults(
    defineProps<{
        variant?: 'auto' | 'light' | 'dark';
    }>(),
    {
        variant: 'auto',
    },
);

defineOptions({
    inheritAttrs: false,
});
</script>

<template>
    <template v-if="variant === 'auto'">
        <img src="/img/marca.png" alt="App Logo Icon" class="block dark:hidden" v-bind="$attrs" />
        <img src="/img/marcadark.png" alt="App Logo Icon" class="hidden dark:block" v-bind="$attrs" />
    </template>

    <img v-else-if="variant === 'dark'" src="/img/marcadark.png" alt="App Logo Icon" v-bind="$attrs" />

    <img v-else src="/img/marca.png" alt="App Logo Icon" v-bind="$attrs" />
</template>

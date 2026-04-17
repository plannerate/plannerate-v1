<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

withDefaults(
    defineProps<{
        variant?: 'auto' | 'light' | 'dark';
    }>(),
    {
        variant: 'auto',
    },
);
</script>

<template>
    <div class="flex items-center">
        <AppLogoIcon :variant="variant" class="h-10 w-auto max-w-[220px] object-contain object-left" />
    </div>
</template>

<script setup lang="ts">
import HomeLayout from '@/layouts/auth/HomeLayout.vue';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <HomeLayout :title="title" :description="description">
        <slot />
    </HomeLayout>
</template>

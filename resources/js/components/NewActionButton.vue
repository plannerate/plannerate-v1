<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

defineProps<{
    href: string;
}>();
</script>

<template>
    <Button variant="gradient" size="pill-sm" as-child>
        <Link :href="href">
            <slot />
        </Link>
    </Button>
</template>

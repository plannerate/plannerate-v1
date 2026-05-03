<script setup lang="ts">
import WayfinderLink from '@/components/WayfinderLink.vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    href: string;
}>();
</script>

<template>
    <Button variant="gradient" size="pill-sm" as-child>
        <WayfinderLink :href="props.href">
            <slot />
        </WayfinderLink>
    </Button>
</template>

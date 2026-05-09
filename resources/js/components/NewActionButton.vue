<script setup lang="ts">
import { Button } from '@/components/ui/button';
import WayfinderLink from '@/components/WayfinderLink.vue';

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

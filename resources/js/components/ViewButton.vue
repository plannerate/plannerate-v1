<script setup lang="ts">
import { Eye } from 'lucide-vue-next';
import WayfinderLink from '@/components/WayfinderLink.vue';
import { Button } from '@/components/ui/button';

defineProps<{
    href: string;
}>();
</script>

<template>
    <Button variant="ghost" size="sm" as-child>
        <WayfinderLink :href="href" class="inline-flex items-center gap-1.5">
            <Eye class="size-3.5" />
            <slot>Ver</slot>
        </WayfinderLink>
    </Button>
</template>

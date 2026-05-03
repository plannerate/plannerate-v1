<script setup lang="ts">
import { Pencil } from 'lucide-vue-next';
import WayfinderLink from '@/components/WayfinderLink.vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    href: string;
}>();
</script>

<template>
    <Button variant="outline" size="sm" as-child>
        <WayfinderLink :href="props.href" class="inline-flex items-center gap-1.5">
            <Pencil class="size-3.5" />
            <slot>Editar</slot>
        </WayfinderLink>
    </Button>
</template>

<script setup lang="ts">
import { Pencil } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import WayfinderLink from '@/components/WayfinderLink.vue';

const props = defineProps<{
    href: string;
}>();
</script>

<template>
    <Button variant="outline" size="sm" as-child>
        <WayfinderLink :href="props.href" class="inline-flex items-center gap-1.5">
            <Pencil class="size-3.5" />
            <span class="hidden sm:inline"><slot>Editar</slot></span>
        </WayfinderLink>
    </Button>
</template>

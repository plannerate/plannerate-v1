<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Pencil } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';

const props = defineProps<{
    href: string;
}>();
</script>

<template>
    <Button variant="outline" size="sm" as-child>
        <Link :href="tenantWayfinderPath(props.href)" class="inline-flex items-center gap-1.5">
            <Pencil class="size-3.5" />
            <slot>Editar</slot>
        </Link>
    </Button>
</template>

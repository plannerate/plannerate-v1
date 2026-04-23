<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Eye } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

defineProps<{
    href: string;
}>();
</script>

<template>
    <Button variant="ghost" size="sm" as-child>
        <Link :href="href" class="inline-flex items-center gap-1.5">
            <Eye class="size-3.5" />
            <slot>Ver</slot>
        </Link>
    </Button>
</template>

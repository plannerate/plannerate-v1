<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';

const props = defineProps<{
    href: string;
}>();
</script>

<template>
    <Button variant="gradient" size="pill-sm" as-child>
        <Link :href="tenantWayfinderPath(props.href)">
            <slot />
        </Link>
    </Button>
</template>

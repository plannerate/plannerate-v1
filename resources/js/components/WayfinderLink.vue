<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps<{
    href: string;
}>();
</script>

<template>
    <Link :href="tenantWayfinderPath(props.href)" v-bind="$attrs">
        <slot />
    </Link>
</template>

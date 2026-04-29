<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { BadgeVariants } from '@/components/ui/badge';

const props = defineProps<{
    status: string;
}>();

function statusVariant(status: string): BadgeVariants['variant'] {
    if (status === 'published') {
return 'default';
}

    if (status === 'importer') {
return 'secondary';
}

    return 'outline';
}
</script>

<template>
    <Badge :variant="statusVariant(status)" class="capitalize">{{ status }}</Badge>
</template>

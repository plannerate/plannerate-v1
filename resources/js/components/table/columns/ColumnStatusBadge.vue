<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { BadgeVariants } from '@/components/ui/badge';
import { useT } from '@/composables/useT';

const { t } = useT();

defineProps<{
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

function statusLabel(status: string): string {
    if (status === 'published') {
        return t('app.statuses.published');
    }

    if (status === 'importer') {
        return t('app.statuses.importer');
    }

    return t('app.statuses.unknown');
}
</script>

<template>
    <Badge :variant="statusVariant(status)" class="capitalize">{{ statusLabel(status) }}</Badge>
</template>

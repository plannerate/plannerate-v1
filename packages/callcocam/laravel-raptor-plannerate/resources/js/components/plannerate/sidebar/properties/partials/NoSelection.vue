<template>
    <div
        class="flex w-full flex-col items-center justify-center gap-3 py-12 text-center"
    >
        <div class="rounded-full bg-muted p-4">
            <MousePointerClick class="size-8 text-muted-foreground" />
        </div>
        <div class="space-y-1">
            <p class="text-sm font-medium text-foreground">
                {{ t('plannerate.sidebar.no_selection.title') }}
            </p>
            <p class="text-xs text-muted-foreground">
                {{ t('plannerate.sidebar.no_selection.description') }}
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { MousePointerClick } from 'lucide-vue-next';
import { useT } from '@/composables/useT';

const { t } = useT();
</script>

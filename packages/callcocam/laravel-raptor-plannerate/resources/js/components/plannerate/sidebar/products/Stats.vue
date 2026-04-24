<template>
    <div class="flex items-center gap-3">
        <div
            class="flex items-center gap-2 rounded-md bg-primary/10 px-3 py-1.5"
        >
            <Package class="size-3.5 text-primary" />
            <span class="text-xs font-medium text-primary">{{ total }}</span>
        </div>
        <div
            v-if="usedCount > 0"
            class="flex items-center gap-2 rounded-md bg-muted px-3 py-1.5"
        >
            <CheckCircle2 class="size-3.5 text-muted-foreground" />
            <span class="text-xs font-medium text-muted-foreground">{{
                usedCount
            }}</span>
        </div>
    </div>
</template>

<script setup lang="ts">
import { CheckCircle2, Package } from 'lucide-vue-next';

interface Props {
    total: number;
    usedCount: number;
}

defineProps<Props>();
</script>

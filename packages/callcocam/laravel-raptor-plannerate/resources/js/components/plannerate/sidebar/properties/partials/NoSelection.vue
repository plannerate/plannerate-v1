<template>
    <div
        class="flex w-full flex-col items-center justify-center gap-3 py-12 text-center"
    >
        <div class="rounded-full bg-muted p-4">
            <MousePointerClick class="size-8 text-muted-foreground" />
        </div>
        <div class="space-y-1">
            <p class="text-sm font-medium text-foreground">
                Nenhum item selecionado
            </p>
            <p class="text-xs text-muted-foreground">
                Selecione um produto ou elemento do planograma
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { MousePointerClick } from 'lucide-vue-next';
</script>

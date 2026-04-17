<script setup lang="ts">
import type { CategoryNode } from '@/composables/useMercadologicoTree';

defineProps<{
    selectedCount: number;
    selected: CategoryNode | null;
}>();
</script>

<template>
    <div v-if="selectedCount > 1" class="detail-header border-b border-border px-4 py-4">
        <div class="detail-title text-sm font-bold text-foreground">
            {{ selectedCount }} categorias selecionadas
        </div>
        <div class="detail-path font-mono text-[10px] text-muted-foreground">
            Segure Ctrl (ou Cmd) e clique para selecionar várias
        </div>
    </div>
    <div v-else-if="selected" class="detail-header border-b border-border px-4 py-4">
        <div class="detail-title text-sm font-bold text-foreground">
            {{ selected.name }}
        </div>
        <div class="detail-path font-mono text-[10px] text-muted-foreground">
            {{ selected.slug ?? '—' }}
        </div>
    </div>
</template>

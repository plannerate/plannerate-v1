<script setup lang="ts">
import type { CategoryNode } from '@/composables/useMercadologicoTree';
import type { HierarchyLevelNames } from '@/composables/useMercadologicoTree';
import {
    LEVEL_COLORS,
    LEVEL_NAMES,
    countChildren,
    totalDescendants,
} from '@/composables/useMercadologicoTree';

const props = defineProps<{
    selected: CategoryNode;
    levelNames?: HierarchyLevelNames | null;
}>();

function levelColor(depth: number): string {
    return LEVEL_COLORS[(depth - 1) % LEVEL_COLORS.length];
}

function levelName(depth: number): string {
    return props.levelNames?.[depth] ?? LEVEL_NAMES[depth] ?? `Nível ${depth}`;
}
</script>

<template>
    <div class="detail-section border-b border-border px-4 py-3">
        <div class="detail-section-title mb-2 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
            Informações
        </div>
        <div class="field-row flex items-center justify-between gap-2 py-1.5">
            <span class="field-label text-[11px] text-muted-foreground">Nível</span>
            <span
                class="level-badge inline-flex items-center gap-1.5 rounded px-2 py-1 text-[11px] font-semibold"
                :style="{
                    background: `${levelColor(selected.depth ?? 1)}22`,
                    color: levelColor(selected.depth ?? 1),
                    border: `1px solid ${levelColor(selected.depth ?? 1)}44`,
                }"
            >
                <span
                    class="h-1.5 w-1.5 rounded-full"
                    :style="{ background: levelColor(selected.depth ?? 1) }"
                />
                {{ levelName(selected.depth ?? 1) }}
            </span>
        </div>
        <div class="field-row flex items-center justify-between py-1.5">
            <span class="field-label text-[11px] text-muted-foreground">Filhos diretos</span>
            <span class="field-value font-mono text-[11px] text-foreground">
                {{ countChildren(selected) }}
            </span>
        </div>
        <div class="field-row flex items-center justify-between py-1.5">
            <span class="field-label text-[11px] text-muted-foreground">Total descendentes</span>
            <span class="field-value font-mono text-[11px] text-foreground">
                {{ totalDescendants(selected) }}
            </span>
        </div>
    </div>
</template>

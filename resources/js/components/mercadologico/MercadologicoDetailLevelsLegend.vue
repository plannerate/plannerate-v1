<script setup lang="ts">
import { computed } from 'vue';
import type { CategoryNode } from '@/composables/useMercadologicoTree';
import type { HierarchyLevelNames } from '@/composables/useMercadologicoTree';
import {
    LEVEL_COLORS,
    LEVEL_NAMES,
    flattenByDepth,
} from '@/composables/useMercadologicoTree';

const props = defineProps<{
    categories: CategoryNode[];
    levelNames?: HierarchyLevelNames | null;
}>();

const byDepth = computed(() => {
    const map = flattenByDepth(props.categories);
    const entries: { depth: number; count: number }[] = [];
    for (let d = 1; d <= 8; d++) {
        entries.push({ depth: d, count: (map.get(d) ?? []).length });
    }
    return entries.filter((e) => e.count > 0);
});

function levelColor(depth: number): string {
    return LEVEL_COLORS[(depth - 1) % LEVEL_COLORS.length];
}

function levelName(depth: number): string {
    return props.levelNames?.[depth] ?? LEVEL_NAMES[depth] ?? `Nível ${depth}`;
}
</script>

<template>
    <div class="detail-section flex-1 border-b border-border px-4 py-3">
        <div class="detail-section-title mb-2 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
            Níveis
        </div>
        <div class="levels-legend flex flex-col gap-1">
            <div
                v-for="item in byDepth"
                :key="item.depth"
                class="legend-item flex items-center gap-2 py-1.5"
            >
                <div
                    class="legend-dot h-2 w-2 shrink-0 rounded-full"
                    :style="{ background: levelColor(item.depth) }"
                />
                <span class="legend-text flex-1 text-[11px] text-muted-foreground">
                    {{ levelName(item.depth) }}
                </span>
                <span class="legend-num font-mono text-[10px] text-muted-foreground">
                    {{ item.count }}
                </span>
            </div>
        </div>
    </div>
</template>

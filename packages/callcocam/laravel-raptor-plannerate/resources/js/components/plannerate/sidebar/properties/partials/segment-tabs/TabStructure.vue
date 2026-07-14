<template>
    <div class="space-y-4">
        <!-- Cabeçalho da aba -->
        <div>
            <h3 class="text-xl font-bold leading-tight text-foreground">
                {{ t('plannerate.sidebar.segment_details.headers.structure_title') }}
            </h3>
            <p class="text-sm text-muted-foreground">
                {{ t('plannerate.sidebar.segment_details.headers.structure_subtitle') }}
            </p>
        </div>

        <!-- Card: Classificação Principal -->
        <SegmentCard
            :icon="Network"
            color="blue"
            :title="t('plannerate.sidebar.segment_details.cards.main_classification')"
        >
            <div class="divide-y divide-border/60">
                <div
                    v-for="level in mainLevels"
                    :key="level.key"
                    class="flex items-center justify-between gap-2 py-2.5 text-sm"
                >
                    <span class="text-muted-foreground">{{ level.label }}</span>
                    <span
                        class="text-right font-semibold"
                        :class="level.value ? 'text-foreground' : 'text-muted-foreground/60'"
                    >
                        {{ level.value || '—' }}
                    </span>
                </div>
            </div>
        </SegmentCard>

        <!-- Card: Níveis Complementares -->
        <SegmentCard
            :icon="Layers"
            color="purple"
            :title="t('plannerate.sidebar.segment_details.cards.complementary_levels')"
        >
            <div class="divide-y divide-border/60">
                <div
                    v-for="level in complementaryLevels"
                    :key="level.key"
                    class="flex items-center justify-between gap-2 py-2.5 text-sm"
                >
                    <span class="text-muted-foreground">{{ level.label }}</span>
                    <span
                        class="text-right font-semibold"
                        :class="level.value ? 'text-foreground' : 'text-muted-foreground/60'"
                    >
                        {{ level.value || '—' }}
                    </span>
                </div>
            </div>
        </SegmentCard>

        <!-- Card: Caminho Completo -->
        <SegmentCard
            :icon="Route"
            color="emerald"
            :title="t('plannerate.sidebar.segment_details.cards.full_path')"
        >
            <div class="rounded-lg bg-emerald-50/70 px-3 py-2.5 dark:bg-emerald-950/20">
                <p class="text-sm font-bold text-emerald-700 dark:text-emerald-400">
                    {{ fullPathLabel }}
                </p>
            </div>
        </SegmentCard>
    </div>
</template>

<script setup lang="ts">
import { Layers, Network, Route } from 'lucide-vue-next';
import { computed } from 'vue';
import { useT } from '@/composables/useT';
import type { Product } from '@/types/planogram';
import SegmentCard from './SegmentCard.vue';

interface Props {
    /** Produto do segmento */
    product?: Product | null;
}

const props = defineProps<Props>();
const { t } = useT();

/**
 * Nome da categoria folha do produto (carregada via relação eager).
 */
const categoryName = computed(() => {
    const cat = props.product?.category;

    if (!cat) {
return null;
}

    if (typeof cat === 'string') {
return cat;
}

    return cat.name ?? null;
});

/**
 * Hierarquia mercadológica derivada do category_full_path (se disponível)
 * ou mostrando apenas a categoria folha carregada pela relação.
 */
const hierarchyLevels = computed(() => {
    const levels = [
        { key: 'retail_segment', label: t('plannerate.sidebar.segment_details.structure.retail_segment'), value: '' },
        { key: 'department', label: t('plannerate.sidebar.segment_details.structure.department'), value: '' },
        { key: 'subdepartment', label: t('plannerate.sidebar.segment_details.structure.subdepartment'), value: '' },
        { key: 'category', label: t('plannerate.sidebar.segment_details.structure.category'), value: '' },
        { key: 'subcategory', label: t('plannerate.sidebar.segment_details.structure.subcategory'), value: '' },
        { key: 'segment', label: t('plannerate.sidebar.segment_details.structure.segment'), value: '' },
        { key: 'subsegment', label: t('plannerate.sidebar.segment_details.structure.subsegment'), value: '' },
    ];

    const fullPath = props.product?.category_full_path;

    if (fullPath) {
        const parts = fullPath.split(' > ').map((s: string) => s.trim());
        parts.forEach((part: string, index: number) => {
            if (index < levels.length) {
                levels[index].value = part;
            }
        });
    } else if (categoryName.value) {
        levels[3].value = categoryName.value;
    }

    return levels;
});

/** Níveis principais: do segmento varejista até a subcategoria */
const mainLevels = computed(() => hierarchyLevels.value.slice(0, 5));

/** Níveis complementares: segmento e subsegmento */
const complementaryLevels = computed(() => hierarchyLevels.value.slice(5));

/**
 * Rótulo do caminho completo: usa o category_full_path ou a categoria folha.
 */
const fullPathLabel = computed(() => {
    if (props.product?.category_full_path) {
        return props.product.category_full_path;
    }

    return categoryName.value || t('plannerate.sidebar.segment_details.structure.no_category');
});
</script>

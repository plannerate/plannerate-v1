<template>
    <div class="space-y-3">
        <!-- Cabeçalho da aba -->
        <div>
            <h3 class="text-xl font-bold leading-tight text-foreground">
                {{ t('plannerate.sidebar.segment_details.headers.position_title') }}
            </h3>
            <p class="text-sm text-muted-foreground">
                {{ t('plannerate.sidebar.segment_details.headers.position_subtitle') }}
            </p>
        </div>

        <!-- Card: Ocupação na Prateleira (configuração física do produto na prateleira) -->
        <SegmentCard
            dense
            :icon="Rows3"
            color="blue"
            :title="t('plannerate.sidebar.segment_details.cards.occupation_shelf')"
        >
            <div class="grid grid-cols-4 gap-1.5">
                <div class="rounded-md border border-border px-2 py-1.5">
                    <p class="text-[10px] leading-none text-muted-foreground">
                        {{ t('plannerate.sidebar.segment_details.position.facings') }}
                    </p>
                    <p class="mt-1 text-lg font-bold leading-none tabular-nums text-foreground">{{ facings }}</p>
                </div>
                <div class="rounded-md border border-border px-2 py-1.5">
                    <p class="text-[10px] leading-none text-muted-foreground">
                        {{ t('plannerate.sidebar.segment_details.position.stacking') }}
                    </p>
                    <p class="mt-1 text-lg font-bold leading-none tabular-nums text-foreground">{{ stacking }}</p>
                </div>
                <div class="rounded-md border border-border px-2 py-1.5">
                    <p class="text-[10px] leading-none text-muted-foreground">
                        {{ t('plannerate.sidebar.segment_details.position.depth_units') }}
                    </p>
                    <p class="mt-1 text-lg font-bold leading-none tabular-nums text-foreground">{{ itemsInDepth }}</p>
                </div>
                <div class="rounded-md border border-border px-2 py-1.5">
                    <p class="text-[10px] leading-none text-muted-foreground">
                        {{ t('plannerate.sidebar.segment_details.position.total_units') }}
                    </p>
                    <p class="mt-1 text-lg font-bold leading-none tabular-nums text-foreground">{{ totalUnits }}</p>
                </div>
            </div>
        </SegmentCard>

        <!-- Card: Ocupação no Planograma (soma do produto em toda a gôndola) -->
        <SegmentCard
            dense
            :icon="LayoutGrid"
            color="emerald"
            :title="t('plannerate.sidebar.segment_details.cards.occupation')"
        >
            <div class="grid grid-cols-3 gap-1.5">
                <div class="rounded-md border border-border px-2 py-1.5">
                    <p class="text-[10px] leading-none text-muted-foreground">
                        {{ t('plannerate.sidebar.segment_details.position.segments') }}
                    </p>
                    <p class="mt-1 text-lg font-bold leading-none tabular-nums text-foreground">{{ planogramOccupation.segments }}</p>
                </div>
                <div class="rounded-md border border-border px-2 py-1.5">
                    <p class="text-[10px] leading-none text-muted-foreground">
                        {{ t('plannerate.sidebar.segment_details.position.facings') }}
                    </p>
                    <p class="mt-1 text-lg font-bold leading-none tabular-nums text-foreground">{{ planogramOccupation.facings }}</p>
                </div>
                <div class="rounded-md border border-border px-2 py-1.5">
                    <p class="text-[10px] leading-none text-muted-foreground">
                        {{ t('plannerate.sidebar.segment_details.position.total_units') }}
                    </p>
                    <p class="mt-1 text-lg font-bold leading-none tabular-nums text-emerald-600 dark:text-emerald-400">
                        {{ planogramOccupation.units }}
                    </p>
                </div>
            </div>
        </SegmentCard>

        <!-- Card: Dimensões do Produto -->
        <SegmentCard
            :icon="Ruler"
            color="blue"
            :title="t('plannerate.sidebar.segment_details.cards.product_dimensions')"
        >
            <div class="grid grid-cols-3 gap-2">
                <div class="space-y-1">
                    <Label class="text-xs text-muted-foreground">
                        {{ t('plannerate.sidebar.segment_details.position.dim_height') }}
                    </Label>
                    <Input
                        type="number"
                        step="0.01"
                        :model-value="Number(product?.height ?? 0)"
                        @update:model-value="$emit('update:dimension', 'height', Number($event))"
                    />
                </div>
                <div class="space-y-1">
                    <Label class="text-xs text-muted-foreground">
                        {{ t('plannerate.sidebar.segment_details.position.dim_width') }}
                    </Label>
                    <Input
                        type="number"
                        step="0.01"
                        :model-value="Number(product?.width ?? 0)"
                        @update:model-value="$emit('update:dimension', 'width', Number($event))"
                    />
                </div>
                <div class="space-y-1">
                    <Label class="text-xs text-muted-foreground">
                        {{ t('plannerate.sidebar.segment_details.position.dim_depth') }}
                    </Label>
                    <Input
                        type="number"
                        step="0.01"
                        :model-value="Number(product?.depth ?? 0)"
                        @update:model-value="$emit('update:dimension', 'depth', Number($event))"
                    />
                </div>
            </div>

            <!-- Orientação (campo sem suporte ainda) -->
            <div class="flex items-center justify-between rounded-lg border border-border px-3 py-2.5">
                <span class="text-sm text-muted-foreground">
                    {{ t('plannerate.sidebar.segment_details.position.orientation') }}
                </span>
                <span class="text-sm font-medium text-muted-foreground">—</span>
            </div>

            <p class="flex items-start gap-1.5 text-xs text-muted-foreground">
                <Info class="mt-0.5 size-3.5 shrink-0" />
                {{ t('plannerate.sidebar.segment_details.position.dimensions_note') }}
            </p>
        </SegmentCard>
    </div>
</template>

<script setup lang="ts">
import { Info, LayoutGrid, Rows3, Ruler } from 'lucide-vue-next';
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useProductOccupation } from '@/composables/plannerate/analysis/useProductOccupation';
import { useT } from '@/composables/useT';
import type { Product, Segment, Shelf } from '@/types/planogram';
import SegmentCard from './SegmentCard.vue';

interface Props {
    /** Produto do segmento */
    product?: Product | null;
    /** Segmento selecionado (contém layer com quantity) */
    segment?: Segment | any;
    /** Prateleira do segmento (para profundidade) */
    shelf?: Shelf | null;
}

const props = defineProps<Props>();
const { t } = useT();

defineEmits<{
    /** Atualiza campo da layer (ex.: 'quantity') */
    'update:layer-field': [field: string, value: any];
    /** Atualiza dimensão do produto (height, width, depth) */
    'update:dimension': [dimension: 'height' | 'width' | 'depth', value: number];
}>();

/** Frentes lado a lado (layer.quantity) */
const facings = computed(() => props.segment?.layer?.quantity ?? 1);

/** Empilhamento vertical (segment.quantity) */
const stacking = computed(() => props.segment?.quantity ?? 1);

/**
 * Quantos produtos cabem na profundidade da prateleira:
 * profundidade da prateleira ÷ profundidade do produto (arredondado p/ baixo).
 */
const itemsInDepth = computed(() => {
    const depth = Number(props.product?.depth ?? 0);
    const shelfDepth = Number(props.shelf?.shelf_depth ?? 0);

    if (!shelfDepth || !depth) {
        return 1;
    }

    return Math.max(1, Math.floor(shelfDepth / depth));
});

/** Total de unidades = frentes × empilhamento × profundidade */
const totalUnits = computed(
    () => facings.value * stacking.value * itemsInDepth.value,
);

const { getPlanogramOccupation } = useProductOccupation();

/** Ocupação do produto somada em toda a gôndola (planograma). */
const planogramOccupation = computed(() =>
    getPlanogramOccupation(props.product?.id),
);
</script>

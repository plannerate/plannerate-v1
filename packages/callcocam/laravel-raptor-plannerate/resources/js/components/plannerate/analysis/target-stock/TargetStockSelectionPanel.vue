<template>
    <Card class="h-fit">
        <CardHeader class="pb-1.5">
            <CardTitle class="text-sm">Detalhes do Produto</CardTitle>
            <CardDescription class="text-xs">
                {{ selected ? 'Visualização e ajuste direto no editor' : 'Selecione um item na tabela' }}
            </CardDescription>
        </CardHeader>
        <CardContent class="max-h-[64vh] overflow-y-auto pt-0">
            <div v-if="selected" class="space-y-2">
                <div class="rounded-lg border border-border bg-accent/40 p-2">
                    <div class="flex gap-2">
                        <div
                            v-if="productImageUrl"
                            class="size-14 shrink-0 overflow-hidden rounded-md border border-border bg-background"
                        >
                            <img
                                :src="productImageUrl"
                                :alt="selected.product_name"
                                class="h-full w-full object-contain"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-2 text-xs font-semibold text-foreground">
                                {{ selected.product_name }}
                            </p>
                            <p class="mt-0.5 font-mono text-[11px] text-muted-foreground">
                                {{ selected.ean }}
                            </p>
                            <div class="mt-1.5 flex items-center gap-1">
                                <Badge variant="outline" class="text-[10px]">
                                    Classe {{ selected.classificacao }}
                                </Badge>
                                <Badge
                                    :variant="selected.permite_frentes === 'Sim' ? 'default' : 'secondary'"
                                    class="text-[10px]"
                                >
                                    Frentes: {{ selected.permite_frentes }}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-border bg-background p-2">
                    <p class="text-[11px] font-semibold text-muted-foreground">Capacidade no segmento</p>
                    <div class="mt-1.5 grid grid-cols-2 gap-x-2.5 gap-y-1 text-xs">
                        <div class="text-muted-foreground">Frentes</div>
                        <div class="text-right font-semibold text-foreground">{{ segmentQuantity }}</div>
                        <div class="text-muted-foreground">Altura</div>
                        <div class="text-right font-semibold text-foreground">{{ layerQuantity }}</div>
                        <div class="text-muted-foreground">Profundidade</div>
                        <div class="text-right font-semibold text-foreground">{{ itemsInDepth }} un.</div>
                        <div class="border-t border-border pt-1 text-foreground font-semibold">Total</div>
                        <div class="border-t border-border pt-1 text-right text-foreground font-semibold">{{ segmentCapacity }} un.</div>
                    </div>
                </div>

                <div class="rounded-lg border border-border bg-background p-2">
                    <p class="text-[11px] font-semibold text-muted-foreground">Estoque</p>
                    <div class="mt-1.5 grid grid-cols-3 gap-1 text-center">
                        <div class="rounded-md border border-border bg-accent/40 p-1">
                            <div class="text-[10px] text-muted-foreground">Alvo</div>
                            <div class="text-xs font-semibold text-foreground">{{ selected.estoque_alvo }}</div>
                        </div>
                        <div class="rounded-md border border-border bg-accent/40 p-1">
                            <div class="text-[10px] text-muted-foreground">Mínimo</div>
                            <div class="text-xs font-semibold text-foreground">{{ selected.estoque_minimo }}</div>
                        </div>
                        <div class="rounded-md border border-border bg-accent/40 p-1">
                            <div class="text-[10px] text-muted-foreground">Atual</div>
                            <div class="text-xs font-semibold text-foreground">{{ selected.estoque_atual }}</div>
                        </div>
                    </div>
                    <div class="mt-1.5 text-[11px] text-muted-foreground">
                        Faixa de tolerância: {{ toleranceMin }} - {{ toleranceMax }}
                    </div>
                </div>

                <div
                    class="rounded-lg border p-2 text-center"
                    :class="{
                        'border-red-500/30 bg-red-500/10 dark:bg-red-500/20': stockStatus === 'increase',
                        'border-yellow-500/30 bg-yellow-500/10 dark:bg-yellow-500/20': stockStatus === 'decrease',
                        'border-green-500/30 bg-green-500/10 dark:bg-green-500/20': stockStatus === 'ok',
                        'border-border bg-accent/30': stockStatus === 'unknown',
                    }"
                >
                    <p class="text-xs font-semibold" :class="statusTextClass">
                        {{ recommendationText }}
                    </p>
                </div>
                <!-- Product Sales Summary -->
                <ProductSalesSummary :product-id="selected.product_id" />

                <TargetStockQuickActions
                    :has-placement="hasPlacement"
                    :current-fronts="currentFronts"
                    :matched-segments-count="matchedSegmentsCount"
                    @increase="$emit('increase-fronts')"
                    @decrease="$emit('decrease-fronts')"
                />
            </div>

            <div v-else class="rounded-lg border border-dashed border-border bg-accent/20 p-3 text-center text-xs text-muted-foreground">
                Clique em um produto da tabela para ver detalhes e ajustar frentes.
            </div>
        </CardContent>
    </Card>
</template>

<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { computed } from 'vue';
import type { TargetStockResult } from './types';
import TargetStockQuickActions from './TargetStockQuickActions.vue';
import ProductSalesSummary from '../../sidebar/properties/partials/ProductSalesSummary.vue';

interface Props {
    selected: TargetStockResult | null;
    hasPlacement: boolean;
    matchedSegmentsCount: number;
    currentFronts: number | null;
    segmentQuantity: number;
    layerQuantity: number;
    itemsInDepth: number;
    segmentCapacity: number;
    productImageUrl: string | null;
    stockStatus: 'increase' | 'decrease' | 'ok' | 'unknown';
    toleranceMin: number;
    toleranceMax: number;
}

const props = defineProps<Props>();

defineEmits<{
    'increase-fronts': [];
    'decrease-fronts': [];
}>();

const recommendationText = computed(() => {
    if (props.stockStatus === 'increase') {
        return 'Aumente o espaço na gôndola';
    }

    if (props.stockStatus === 'decrease') {
        return 'Diminua o espaço na gôndola';
    }

    if (props.stockStatus === 'ok') {
        return 'Espaço adequado';
    }

    return 'Sem dados suficientes para recomendação';
});

const statusTextClass = computed(() => {
    if (props.stockStatus === 'increase') {
        return 'text-red-600 dark:text-red-400';
    }

    if (props.stockStatus === 'decrease') {
        return 'text-yellow-600 dark:text-yellow-400';
    }

    if (props.stockStatus === 'ok') {
        return 'text-green-600 dark:text-green-400';
    }

    return 'text-foreground';
});
</script>

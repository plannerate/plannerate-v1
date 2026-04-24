<template>
    <div
        v-if="stockInfo && stockStatus !== 'unknown' && isVisible"
        class="absolute inset-0 z-[80] flex items-center justify-center rounded-sm"
        :class="{
            'border-2 border-red-500 bg-red-500/20 dark:bg-red-500/30':
                stockStatus === 'increase',
            'border-2 border-yellow-500 bg-yellow-500/20 dark:bg-yellow-500/30':
                stockStatus === 'decrease',
            'border-2 border-green-500 bg-green-500/20 dark:bg-green-500/30':
                stockStatus === 'ok',
        }"
        @click.stop="($event) => $emit('click', $event)"
    >
        <TooltipProvider :delay-duration="200">
            <Tooltip>
                <TooltipTrigger as-child>
                    <div
                        class="absolute top-1/2 left-1/2 z-[100] -translate-x-1/2 -translate-y-1/2 cursor-pointer rounded-full bg-white/95 shadow-sm backdrop-blur-sm transition-transform hover:scale-105"
                        :style="{ padding: `${iconPadding}px` }"
                        :class="{
                            'border border-red-500/70':
                                stockStatus === 'increase',
                            'border border-yellow-500/70':
                                stockStatus === 'decrease',
                            'border border-green-500/70': stockStatus === 'ok',
                        }"
                    >
                        <TrendingUp
                            v-if="stockStatus === 'increase'"
                            :style="{ width: `${iconSize}px`, height: `${iconSize}px` }"
                            class="text-red-500"
                        />
                        <TrendingDown
                            v-if="stockStatus === 'decrease'"
                            :style="{ width: `${iconSize}px`, height: `${iconSize}px` }"
                            class="text-yellow-500"
                        />
                        <CheckCircle
                            v-if="stockStatus === 'ok'"
                            :style="{ width: `${iconSize}px`, height: `${iconSize}px` }"
                            class="text-green-500"
                        />
                    </div>
                </TooltipTrigger>
                <TooltipContent
                    side="right"
                    align="start"
                    :side-offset="8"
                    :collision-padding="16"
                    :avoid-collisions="true"
                    class="z-[9999] max-h-[68vh] w-[min(23rem,calc(100vw-1rem))] overflow-hidden border border-border bg-background p-0 shadow-2xl"
                >
                    <div class="max-h-[68vh] space-y-3 overflow-y-auto p-3.5">
                        <!-- Cabecalho -->
                        <div class="border-b border-border pb-2">
                            <div class="min-w-0 flex-1">
                                <p
                                    class="line-clamp-2 text-sm leading-tight font-semibold text-foreground"
                                >
                                    {{ stockInfo.product_name || 'Produto' }}
                                </p>
                                <p
                                    v-if="stockInfo.ean"
                                    class="mt-0.5 font-mono text-xs text-muted-foreground"
                                >
                                    EAN: {{ stockInfo.ean }}
                                </p>
                            </div>
                        </div>

                        <!-- Capacidade no segmento -->
                        <div
                            class="rounded-lg border border-border bg-accent/50 p-2"
                        >
                            <div class="flex gap-2.5">
                                <div
                                    v-if="productImageUrl"
                                    class="size-24 shrink-0 self-center overflow-hidden"
                                >
                                    <img
                                        :src="productImageUrl"
                                        :alt="
                                            stockInfo.product_name || 'Produto'
                                        "
                                        class="h-full w-full object-contain"
                                    />
                                </div>
                                <div class="min-w-0 flex-1 space-y-2">
                                    <p
                                        class="mb-2.5 text-xs font-semibold text-foreground"
                                    >
                                        Capacidade deste segmento
                                    </p>
                                    <div
                                        class="flex items-center justify-between text-xs"
                                    >
                                        <span class="text-muted-foreground"
                                            >Frentes:</span
                                        >
                                        <span
                                            class="font-semibold text-foreground"
                                            >{{ segmentQuantity || 0 }}</span
                                        >
                                    </div>
                                    <div
                                        class="flex items-center justify-between text-xs"
                                    >
                                        <span class="text-muted-foreground"
                                            >Altura:</span
                                        >
                                        <span
                                            class="font-semibold text-foreground"
                                            >{{ layerQuantity || 0 }}</span
                                        >
                                    </div>
                                    <div
                                        class="flex items-center justify-between text-xs"
                                    >
                                        <span class="text-muted-foreground"
                                            >Profundidade:</span
                                        >
                                        <span
                                            class="font-semibold text-foreground"
                                            >{{ itemsInDepth || 0 }} un.</span
                                        >
                                    </div>
                                    <div
                                        class="mt-2 flex items-center justify-between border-t border-border pt-2"
                                    >
                                        <span
                                            class="text-xs font-semibold text-foreground"
                                            >Total:</span
                                        >
                                        <span
                                            class="text-base font-bold text-foreground"
                                            >{{
                                                segmentCapacity || 0
                                            }}
                                            un.</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Estoque -->
                        <div class="space-y-2.5">
                            <div
                                class="space-y-2 rounded-lg border border-border bg-accent/50 p-2.5"
                            >
                                <p
                                    class="text-[11px] font-semibold text-muted-foreground"
                                >
                                    Estoque
                                </p>
                                <div class="grid grid-cols-3 gap-2">
                                    <div
                                        class="rounded-md border border-border bg-background p-2 text-center"
                                    >
                                        <p
                                            class="text-[10px] font-medium text-muted-foreground"
                                        >
                                            Alvo
                                        </p>
                                        <p
                                            class="text-sm font-bold text-foreground"
                                        >
                                            {{ stockInfo.estoque_alvo || 0 }}
                                        </p>
                                        <p
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            un.
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-md border border-border bg-background p-2 text-center"
                                    >
                                        <p
                                            class="text-[10px] font-medium text-muted-foreground"
                                        >
                                            Mínimo
                                        </p>
                                        <p
                                            class="text-sm font-bold text-foreground"
                                        >
                                            {{ stockInfo.estoque_minimo || 0 }}
                                        </p>
                                        <p
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            un.
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-md border border-border bg-background p-2 text-center"
                                    >
                                        <p
                                            class="text-[10px] font-medium text-muted-foreground"
                                        >
                                            Atual
                                        </p>
                                        <p
                                            class="text-sm font-bold text-foreground"
                                        >
                                            {{ stockInfo.estoque_atual || 0 }}
                                        </p>
                                        <p
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            un.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2.5">
                                <div
                                    class="rounded-lg border border-border bg-accent p-2.5 text-center"
                                >
                                    <p
                                        class="mb-1 text-[11px] font-medium text-muted-foreground"
                                    >
                                        Demanda Média
                                    </p>
                                    <p
                                        class="text-base font-bold text-foreground"
                                    >
                                        {{
                                            formatNumber(
                                                stockInfo.demanda_media,
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        unidades
                                    </p>
                                </div>
                                <div
                                    class="rounded-lg border border-border bg-accent p-2.5 text-center"
                                >
                                    <p
                                        class="mb-1 text-[11px] font-medium text-muted-foreground"
                                    >
                                        Faixa de Tolerância
                                    </p>
                                    <p
                                        class="text-sm font-bold text-foreground"
                                    >
                                        {{
                                            Math.round(
                                                stockInfo.estoque_alvo -
                                                    toleranceMargin,
                                            )
                                        }}
                                        -
                                        {{
                                            Math.round(
                                                stockInfo.estoque_alvo +
                                                    toleranceMargin,
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        unidades
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Recomendação -->
                        <div class="space-y-2.5 border-t border-border pt-2">
                            <div
                                class="rounded-lg border p-3 text-center"
                                :class="{
                                    'border-red-500/30 bg-red-500/10 dark:bg-red-500/20':
                                        stockStatus === 'increase',
                                    'border-yellow-500/30 bg-yellow-500/10 dark:bg-yellow-500/20':
                                        stockStatus === 'decrease',
                                    'border-green-500/30 bg-green-500/10 dark:bg-green-500/20':
                                        stockStatus === 'ok',
                                }"
                            >
                                <p
                                    class="flex items-center justify-center gap-1.5 text-xs font-semibold"
                                    :class="{
                                        'text-red-600 dark:text-red-400':
                                            stockStatus === 'increase',
                                        'text-yellow-600 dark:text-yellow-400':
                                            stockStatus === 'decrease',
                                        'text-green-600 dark:text-green-400':
                                            stockStatus === 'ok',
                                    }"
                                >
                                    <TrendingUp
                                        v-if="stockStatus === 'increase'"
                                        class="size-4"
                                    />
                                    <TrendingDown
                                        v-if="stockStatus === 'decrease'"
                                        class="size-4"
                                    />
                                    <CheckCircle
                                        v-if="stockStatus === 'ok'"
                                        class="size-4"
                                    />
                                    <span v-if="stockStatus === 'increase'"
                                        >Aumente o espaço na gôndola</span
                                    >
                                    <span v-if="stockStatus === 'decrease'"
                                        >Diminua o espaço na gôndola</span
                                    >
                                    <span v-if="stockStatus === 'ok'"
                                        >Espaço adequado</span
                                    >
                                </p>
                            </div>
                        </div>
                    </div>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    </div>
</template>

<script setup lang="ts">
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '../../../../components/ui/tooltip';
import { useTargetStockAnalysis } from '../../../../composables/plannerate/v3/useTargetStockAnalysis';
import type { Segment } from '../../../../types/planogram';
import { CheckCircle, TrendingDown, TrendingUp } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    segment: Segment;
    shelfDepth?: number;
    scale?: number;
}

const props = defineProps<Props>();

defineEmits(['click']);

const {
    getTargetStockData,
    calculateSegmentCapacity,
    getStockStatus,
    calculateToleranceMargin,
    DEFAULT_TOLERANCE,
    isVisible,
} = useTargetStockAnalysis();

// Busca dados de target stock pelo EAN do produto
const stockInfo = computed(() => {
    const ean = props.segment?.layer?.product?.ean;
    if (!ean) return null;
    return getTargetStockData(ean);
});

// Imagem do produto para o cabecalho do tooltip
const productImageUrl = computed(() => {
    return props.segment?.layer?.product?.image_url ?? null;
});

// Quantidade de frentes (segment quantity)
const segmentQuantity = computed(() => props.segment?.quantity ?? 0);

// Quantidade de produtos por frente (layer quantity)
const layerQuantity = computed(() => props.segment?.layer?.quantity ?? 0);

// Profundidade do produto (agora está diretamente no produto)
const productDepth = computed(() => props.segment?.layer?.product?.depth ?? 0);

// Quantos produtos cabem na profundidade
const itemsInDepth = computed(() => {
    if (!productDepth.value || !props.shelfDepth || productDepth.value === 0) {
        return 0;
    }
    return Math.floor(props.shelfDepth / productDepth.value);
});

// Capacidade total deste segment
const segmentCapacity = computed(() => {
    return calculateSegmentCapacity(
        segmentQuantity.value,
        layerQuantity.value,
        productDepth.value,
        props.shelfDepth ?? 0,
    );
});

// Margem de tolerância
const toleranceMargin = computed(() => {
    if (!stockInfo.value) return 0;
    return calculateToleranceMargin(
        stockInfo.value.estoque_alvo,
        DEFAULT_TOLERANCE,
    );
});

// Status do estoque (increase, decrease, ok, unknown)
const stockStatus = computed(() => {
    if (!stockInfo.value) return 'unknown';

    return getStockStatus(
        segmentCapacity.value,
        stockInfo.value.estoque_alvo,
        DEFAULT_TOLERANCE,
    );
});

// Tamanho do ícone proporcional ao scale da gôndola
const iconSize = computed(() => Math.max(6, Math.min(20, (props.scale ?? 3) * 4)));
const iconPadding = computed(() => Math.max(2, Math.min(8, (props.scale ?? 3) * 2)));

// Formata número com 2 casas decimais
const formatNumber = (value: number | undefined) => {
    if (value === undefined || value === null) return '0.00';
    return value.toFixed(2);
};
</script>

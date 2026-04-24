<template>
    <div
        v-if="stockInfo && stockStatus !== 'unknown' && isVisible"
        class="absolute inset-0 z-[80] flex items-center justify-center rounded-sm cursor-pointer"
        :class="{
            'border-2 border-red-600 bg-red-300/75': stockStatus === 'increase',
            'border-2 border-yellow-600 bg-yellow-300/75': stockStatus === 'decrease',
            'border-2 border-green-600 bg-green-300/75': stockStatus === 'ok',
        }"
        @click.stop="handleClick"
    >
        <TooltipProvider :delay-duration="200">
            <Tooltip>
                <TooltipTrigger as-child>
                    <div
                        class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-white shadow-xl p-1.5 cursor-pointer hover:scale-110 transition-transform z-[100]"
                        :class="{
                            'border-2 border-red-600': stockStatus === 'increase',
                            'border-2 border-yellow-600': stockStatus === 'decrease',
                            'border-2 border-green-600': stockStatus === 'ok',
                        }"
                    >
                        <TrendingUp v-if="stockStatus === 'increase'" class="size-3 text-red-600" />
                        <TrendingDown v-if="stockStatus === 'decrease'" class="size-3 text-yellow-600" />
                        <CheckCircle v-if="stockStatus === 'ok'" class="size-3 text-green-600" />
                    </div>
                </TooltipTrigger>
                <TooltipContent side="top" :side-offset="10" class="max-w-sm z-[9999] p-0">
                    <div class="p-4 space-y-3">
                        <!-- Header -->
                        <div>
                            <h4 class="font-semibold text-sm">{{ stockInfo.product_name }}</h4>
                            <p class="text-xs text-muted-foreground">Análise de Estoque Alvo</p>
                        </div>
                        
                        <!-- Capacidade Atual -->
                        <div class="rounded-lg bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-900 p-3">
                            <div class="flex items-center gap-1 mb-2">
                                <div class="size-1.5 rounded-full bg-blue-600"></div>
                                <p class="text-xs font-semibold text-blue-900 dark:text-blue-300">Capacidade Atual</p>
                            </div>
                            <div class="grid grid-cols-3 gap-3 text-xs mb-2">
                                <div>
                                    <p class="text-muted-foreground">Frentes</p>
                                    <p class="font-semibold text-foreground">{{ segmentQuantity }}</p>
                                </div>
                                <div>
                                    <p class="text-muted-foreground">Altura</p>
                                    <p class="font-semibold text-foreground">{{ layerQuantity }}</p>
                                </div>
                                <div>
                                    <p class="text-muted-foreground">Profund.</p>
                                    <p class="font-semibold text-foreground">{{ itemsInDepth }}</p>
                                </div>
                            </div>
                            <p class="text-sm font-bold text-blue-700 dark:text-blue-400">
                                Total: {{ segmentCapacity }} unidades
                            </p>
                        </div>

                        <!-- Grid de Métricas -->
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <p class="text-muted-foreground mb-0.5">Estoque Alvo</p>
                                <p class="font-semibold text-foreground">{{ stockInfo.estoque_alvo }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground mb-0.5">Est. Mínimo</p>
                                <p class="font-semibold text-foreground">{{ stockInfo.estoque_minimo }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground mb-0.5">Est. Atual</p>
                                <p class="font-semibold text-foreground">{{ stockInfo.estoque_atual }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground mb-0.5">Demanda/dia</p>
                                <p class="font-semibold text-foreground">{{ stockInfo.demanda_media.toFixed(1) }}</p>
                            </div>
                        </div>

                        <!-- Recomendação -->
                        <div 
                            class="rounded-lg p-3 flex items-start gap-2"
                            :class="{
                                'bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900': stockStatus === 'increase',
                                'bg-yellow-50 dark:bg-yellow-950/30 border border-yellow-200 dark:border-yellow-900': stockStatus === 'decrease',
                                'bg-green-50 dark:bg-green-950/30 border border-green-200 dark:border-green-900': stockStatus === 'ok',
                            }"
                        >
                            <div 
                                class="size-5 rounded-full flex items-center justify-center shrink-0 mt-0.5"
                                :class="{
                                    'bg-red-100 dark:bg-red-900/50': stockStatus === 'increase',
                                    'bg-yellow-100 dark:bg-yellow-900/50': stockStatus === 'decrease',
                                    'bg-green-100 dark:bg-green-900/50': stockStatus === 'ok',
                                }"
                            >
                                <svg 
                                    v-if="stockStatus === 'increase'" 
                                    class="size-3 text-red-600 dark:text-red-400" 
                                    fill="currentColor" 
                                    viewBox="0 0 20 20"
                                >
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <svg 
                                    v-else-if="stockStatus === 'decrease'" 
                                    class="size-3 text-yellow-600 dark:text-yellow-400" 
                                    fill="currentColor" 
                                    viewBox="0 0 20 20"
                                >
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <svg 
                                    v-else 
                                    class="size-3 text-green-600 dark:text-green-400" 
                                    fill="currentColor" 
                                    viewBox="0 0 20 20"
                                >
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-xs text-muted-foreground mb-1">
                                    Faixa: {{ Math.round(stockInfo.estoque_alvo - toleranceMargin) }}-{{ Math.round(stockInfo.estoque_alvo + toleranceMargin) }} un
                                </p>
                                <p 
                                    class="text-sm font-semibold"
                                    :class="{
                                        'text-red-700 dark:text-red-400': stockStatus === 'increase',
                                        'text-yellow-700 dark:text-yellow-400': stockStatus === 'decrease',
                                        'text-green-700 dark:text-green-400': stockStatus === 'ok',
                                    }"
                                >
                                    <span v-if="stockStatus === 'increase'">Aumentar espaço</span>
                                    <span v-else-if="stockStatus === 'decrease'">Reduzir espaço</span>
                                    <span v-else>Espaço adequado</span>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Footer -->
                        <p class="text-xs text-center text-muted-foreground pt-1 border-t">
                            Clique para ver detalhes completos
                        </p>
                    </div>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { TrendingUp, TrendingDown, CheckCircle } from 'lucide-vue-next';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTargetStockAnalysis } from '@/composables/plannerate/v3/useTargetStockAnalysis';
import type { Segment } from '@/types/planogram';

interface Props {
    segment: Segment;
    shelfDepth?: number;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'click', ean: string): void;
}>();

const { getTargetStockData, isVisible } = useTargetStockAnalysis();

const handleClick = () => {
    const ean = props.segment.layer?.product?.ean;
    if (ean) {
        emit('click', ean);
    }
};

// Busca informação de estoque alvo pelo EAN do produto
const stockInfo = computed(() => {
    const ean = props.segment.layer?.product?.ean;
    return getTargetStockData(ean);
});

// Cálculo da capacidade do segment
const segmentQuantity = computed(() => props.segment.quantity || 1);
const layerQuantity = computed(() => props.segment.layer?.quantity || 1);
const shelfDepth = computed(() => props.shelfDepth || 40); // Profundidade padrão 40cm
// Profundidade do produto (agora está diretamente no produto)
const productDepth = computed(() => props.segment.layer?.product?.depth || 10);
const itemsInDepth = computed(() => Math.floor(shelfDepth.value / productDepth.value));
const segmentCapacity = computed(() => segmentQuantity.value * layerQuantity.value * itemsInDepth.value);

// Margem de tolerância (20%)
const toleranceMargin = computed(() => {
    if (!stockInfo.value) return 0;
    return stockInfo.value.estoque_alvo * 0.2;
});

// Status do estoque baseado na capacidade vs estoque alvo
const stockStatus = computed<'increase' | 'decrease' | 'ok' | 'unknown'>(() => {
    if (!stockInfo.value) return 'unknown';
    
    const capacity = segmentCapacity.value;
    const target = stockInfo.value.estoque_alvo;
    const lowerBound = target - toleranceMargin.value;
    const upperBound = target + toleranceMargin.value;
    
    if (capacity < lowerBound) {
        return 'increase'; // Precisa aumentar espaço
    } else if (capacity > upperBound) {
        return 'decrease'; // Precisa diminuir espaço
    } else {
        return 'ok'; // Está dentro da faixa de tolerância
    }
});
</script>

<template>
    <div class="space-y-4">
        <!-- Preço de venda cadastrado no produto -->
        <div v-if="product?.price != null" class="rounded-md border bg-muted/40 px-3 py-2">
            <div class="flex items-center justify-between">
                <span class="text-xs text-muted-foreground">
                    {{ t('plannerate.sidebar.segment_details.performance.sale_price') }}
                </span>
                <span class="text-sm font-bold text-foreground">
                    {{ formatCurrency(product.price) }}
                </span>
            </div>
        </div>

        <!-- Resumo de vendas (carrega via composable) -->
        <ProductSalesSummary :product-id="productId" />
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useT } from '@/composables/useT';
import type { Product } from '@/types/planogram';
import ProductSalesSummary from '../ProductSalesSummary.vue';

interface Props {
    /** Produto do segmento */
    product?: Product | null;
}

const props = defineProps<Props>();
const { t } = useT();

/** ID do produto para buscar dados de vendas */
const productId = computed(() => props.product?.id ?? null);

/** Formata valor em BRL */
function formatCurrency(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value);
}
</script>

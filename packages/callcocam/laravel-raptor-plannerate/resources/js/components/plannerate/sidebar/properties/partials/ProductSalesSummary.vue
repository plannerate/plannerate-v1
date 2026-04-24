<template>
    <div class="w-full space-y-4">
        <!-- Header -->
        <div>
            <h4 class="text-sm font-medium text-foreground">
                Resumo de Vendas
            </h4>
            <p class="mt-1 text-xs text-muted-foreground">
                Análise de desempenho do produto
            </p>
        </div>

        <Separator />

        <!-- Loading State -->
        <div v-if="isLoading" class="flex items-center justify-center py-8">
            <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
        </div>

        <!-- Error State -->
        <div
            v-else-if="error"
            class="rounded-lg border border-destructive/50 bg-destructive/10 p-4"
        >
            <p class="text-sm text-destructive">{{ error }}</p>
        </div>

        <!-- No Data State -->
        <div
            v-else-if="!salesData || salesData.summary.total_sales === 0"
            class="rounded-lg border bg-muted/50 p-6"
        >
            <div class="text-center">
                <TrendingDown class="mx-auto h-10 w-10 text-muted-foreground" />
                <p class="mt-2 text-sm font-medium text-foreground">
                    Sem dados de vendas
                </p>
                <p class="text-xs text-muted-foreground">
                    Este produto ainda não possui vendas registradas
                </p>
            </div>
        </div>

        <!-- Sales Data -->
        <div v-else class="space-y-4">
            <!-- KPIs Grid -->
            <div class="grid grid-cols-2 gap-2">
                <!-- Total Vendas -->
                <div class="rounded-lg border bg-muted/50 p-3">
                    <p class="text-xs font-medium text-muted-foreground">
                        Total Vendas
                    </p>
                    <p class="text-xl font-bold text-foreground">
                        {{ salesData.summary.total_sales }}
                    </p>
                </div>

                <!-- Quantidade -->
                <div class="rounded-lg border bg-muted/50 p-3">
                    <p class="text-xs font-medium text-muted-foreground">
                        Quantidade
                    </p>
                    <p class="text-xl font-bold text-foreground">
                        {{ salesData.summary.total_quantity }}
                    </p>
                </div>

                <!-- Faturamento -->
                <div class="rounded-lg border bg-muted/50 p-3">
                    <p class="text-xs font-medium text-muted-foreground">
                        Faturamento
                    </p>
                    <p
                        class="text-lg font-bold text-green-600 dark:text-green-500"
                    >
                        {{ formatCurrency(salesData.summary.total_revenue) }}
                    </p>
                </div>

                <!-- Margem Média -->
                <div class="rounded-lg border bg-muted/50 p-3">
                    <p class="text-xs font-medium text-muted-foreground">
                        Margem Média
                    </p>
                    <p class="text-lg font-bold text-foreground">
                        {{ formatCurrency(salesData.summary.avg_margin) }}
                    </p>
                </div>
            </div>

            <Separator />

            <!-- Preços -->
            <div class="space-y-2">
                <h5 class="text-xs font-semibold text-foreground">
                    Preços Médios
                </h5>
                <div class="grid grid-cols-2 gap-2">
                    <div
                        class="flex items-center justify-between rounded-md bg-muted/30 px-3 py-2"
                    >
                        <span class="text-xs text-muted-foreground">Venda</span>
                        <span class="text-sm font-medium text-foreground">
                            {{ formatCurrency(salesData.summary.avg_price) }}
                        </span>
                    </div>
                    <div
                        class="flex items-center justify-between rounded-md bg-muted/30 px-3 py-2"
                    >
                        <span class="text-xs text-muted-foreground">Custo</span>
                        <span class="text-sm font-medium text-foreground">
                            {{ formatCurrency(salesData.summary.avg_cost) }}
                        </span>
                    </div>
                </div>
            </div>

            <Separator />

            <!-- Período -->
            <div class="space-y-2">
                <h5 class="text-xs font-semibold text-foreground">
                    Período de Vendas
                </h5>
                <div class="rounded-md bg-muted/30 px-3 py-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-muted-foreground"
                            >Primeira Venda</span
                        >
                        <span class="font-medium text-foreground">
                            {{ formatDate(salesData.summary.first_sale_date) }}
                        </span>
                    </div>
                    <div class="mt-1 flex items-center justify-between text-xs">
                        <span class="text-muted-foreground">Última Venda</span>
                        <span class="font-medium text-foreground">
                            {{ formatDate(salesData.summary.last_sale_date) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Top Stores -->
            <div v-if="salesData.top_stores.length > 0" class="space-y-2">
                <Separator />
                <h5 class="text-xs font-semibold text-foreground">
                    Top 5 Lojas
                </h5>
                <div class="space-y-1">
                    <div
                        v-for="(store, index) in salesData.top_stores"
                        :key="store.store_id"
                        class="rounded-md bg-muted/30 px-3 py-2"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Badge
                                    variant="outline"
                                    class="h-5 w-5 justify-center p-0 text-xs"
                                >
                                    {{ index + 1 }}
                                </Badge>
                                <span
                                    class="text-xs font-medium text-foreground"
                                    >{{ store.store_name }}</span
                                >
                            </div>
                            <span
                                class="text-xs font-semibold text-green-600 dark:text-green-500"
                            >
                                {{ formatCurrency(store.revenue) }}
                            </span>
                        </div>
                        <div
                            class="mt-1 flex items-center gap-3 text-xs text-muted-foreground"
                        >
                            <span>{{ store.sales_count }} vendas</span>
                            <span>{{ store.quantity }} unidades</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { useProductSales } from '@/composables/plannerate/useProductSales';
import { Loader2, TrendingDown } from 'lucide-vue-next';
import { watch } from 'vue';

interface Props {
    productId: string | null;
}

const props = defineProps<Props>();

const { salesData, isLoading, error, loadSales, clearSales } =
    useProductSales();

// Watch productId changes
watch(
    () => props.productId,
    (newProductId) => {
        if (newProductId) {
            loadSales(newProductId);
        } else {
            clearSales();
        }
    },
    { immediate: true },
);

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value);
}

function formatDate(date: string | null): string {
    if (!date) return '-';

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(date));
}
</script>

<template>
    <div class="w-full space-y-4">
        <!-- Header -->
        <div>
            <h4 class="text-sm font-medium text-foreground">
                {{ t('plannerate.sidebar.product_sales_summary.title') }}
            </h4>
            <p class="mt-1 text-xs text-muted-foreground">
                {{ t('plannerate.sidebar.product_sales_summary.subtitle') }}
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
                    {{ t('plannerate.sidebar.product_sales_summary.no_data_title') }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ t('plannerate.sidebar.product_sales_summary.no_data_description') }}
                </p>
            </div>
        </div>

        <!-- Sales Data -->
        <div v-else class="space-y-4">
            <!-- Grupo 1: Resumo de Vendas -->
            <div class="space-y-2 rounded-lg border p-3">
                <div class="flex items-center gap-2">
                    <BarChart3 class="h-4 w-4 text-blue-600 dark:text-blue-500" />
                    <h5 class="text-sm font-semibold text-foreground">
                        {{ t('plannerate.sidebar.product_sales_summary.sales_group') }}
                    </h5>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <!-- Total Vendas -->
                    <div class="rounded-md bg-muted/40 p-2.5">
                        <p class="text-xs text-muted-foreground">
                            {{ t('plannerate.sidebar.product_sales_summary.total_sales') }}
                        </p>
                        <p class="text-xl font-bold text-foreground">
                            {{ salesData.summary.total_sales }}
                        </p>
                    </div>
                    <!-- Quantidade Vendida -->
                    <div class="rounded-md bg-muted/40 p-2.5">
                        <p class="text-xs text-muted-foreground">
                            {{ t('plannerate.sidebar.product_sales_summary.quantity') }}
                        </p>
                        <p class="text-xl font-bold text-foreground">
                            {{ salesData.summary.total_quantity }}
                        </p>
                    </div>
                    <!-- Valor de Venda -->
                    <div class="rounded-md bg-muted/40 p-2.5">
                        <p class="text-xs text-muted-foreground">
                            {{ t('plannerate.sidebar.product_sales_summary.revenue') }}
                        </p>
                        <p class="text-lg font-bold text-green-600 dark:text-green-500">
                            {{ formatCurrency(salesData.summary.total_revenue) }}
                        </p>
                    </div>
                    <!-- Preço Médio de Venda -->
                    <div class="rounded-md bg-muted/40 p-2.5">
                        <p class="text-xs text-muted-foreground">
                            {{ t('plannerate.sidebar.product_sales_summary.avg_sale_price') }}
                        </p>
                        <p class="text-lg font-bold text-foreground">
                            {{ formatCurrency(salesData.summary.avg_price) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Grupo 2: Custo e Lucro Bruto -->
            <div class="space-y-2 rounded-lg border p-3">
                <div class="flex items-center gap-2">
                    <Coins class="h-4 w-4 text-purple-600 dark:text-purple-500" />
                    <h5 class="text-sm font-semibold text-foreground">
                        {{ t('plannerate.sidebar.product_sales_summary.cost_profit_group') }}
                    </h5>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <!-- Custo médio unitário -->
                    <div class="rounded-md bg-muted/40 p-2.5">
                        <p class="text-xs text-muted-foreground">
                            {{ t('plannerate.sidebar.product_sales_summary.avg_cost_unit') }}
                        </p>
                        <p class="text-base font-bold text-foreground">
                            {{ formatCurrency(salesData.summary.avg_cost) }}
                        </p>
                    </div>
                    <!-- Custo total -->
                    <div class="rounded-md bg-muted/40 p-2.5">
                        <p class="text-xs text-muted-foreground">
                            {{ t('plannerate.sidebar.product_sales_summary.total_cost') }}
                        </p>
                        <p class="text-base font-bold text-foreground">
                            {{ formatCurrency(salesData.summary.total_cost) }}
                        </p>
                    </div>
                    <!-- Lucro bruto unitário = preço − custo de aquisição (backend) -->
                    <div class="rounded-md bg-muted/40 p-2.5">
                        <p class="text-xs text-muted-foreground">
                            {{ t('plannerate.sidebar.product_sales_summary.gross_profit_unit') }}
                        </p>
                        <p class="text-base font-bold text-green-600 dark:text-green-500">
                            {{ formatCurrency(salesData.summary.gross_profit_unit) }}
                        </p>
                    </div>
                    <!-- Lucro bruto total = faturamento − custo total (backend) -->
                    <div class="rounded-md bg-muted/40 p-2.5">
                        <p class="text-xs text-muted-foreground">
                            {{ t('plannerate.sidebar.product_sales_summary.gross_profit_total') }}
                        </p>
                        <p class="text-base font-bold text-green-600 dark:text-green-500">
                            {{ formatCurrency(salesData.summary.gross_profit_total) }}
                        </p>
                    </div>
                </div>
                <!-- Margem bruta = lucro bruto / faturamento (backend) -->
                <div class="rounded-md bg-muted/40 p-2.5">
                    <p class="text-xs text-muted-foreground">
                        {{ t('plannerate.sidebar.product_sales_summary.gross_margin') }}
                    </p>
                    <p class="text-base font-bold text-purple-600 dark:text-purple-500">
                        {{ formatPercent(salesData.summary.gross_margin_pct) }}
                    </p>
                </div>
            </div>

            <!-- Grupo 3: Margem Real / Líquida -->
            <div
                class="space-y-2 rounded-lg border border-green-500/30 bg-green-500/5 p-3"
            >
                <div class="flex items-center gap-2">
                    <BadgeCheck class="h-4 w-4 text-green-600 dark:text-green-500" />
                    <h5 class="text-sm font-semibold text-foreground">
                        {{ t('plannerate.sidebar.product_sales_summary.net_margin_group') }}
                    </h5>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <!-- Margem unitária líquida -->
                    <div class="rounded-md bg-background/60 p-2.5">
                        <p class="text-xs text-muted-foreground">
                            {{ t('plannerate.sidebar.product_sales_summary.net_margin_unit') }}
                        </p>
                        <p class="text-sm font-bold text-green-600 dark:text-green-500">
                            {{ formatCurrency(salesData.summary.avg_margin) }}
                        </p>
                    </div>
                    <!-- Margem total líquida -->
                    <div class="rounded-md bg-background/60 p-2.5">
                        <p class="text-xs text-muted-foreground">
                            {{ t('plannerate.sidebar.product_sales_summary.net_margin_total') }}
                        </p>
                        <p class="text-sm font-bold text-green-600 dark:text-green-500">
                            {{ formatCurrency(salesData.summary.total_margin) }}
                        </p>
                    </div>
                    <!-- Margem líquida (%) -->
                    <div class="rounded-md bg-background/60 p-2.5">
                        <p class="text-xs text-muted-foreground">
                            {{ t('plannerate.sidebar.product_sales_summary.net_margin_percentage') }}
                        </p>
                        <p class="text-sm font-bold text-green-600 dark:text-green-500">
                            {{ formatPercent(salesData.summary.margin_percentage) }}
                        </p>
                    </div>
                </div>
                <!-- Nota: a margem líquida desconta impostos além do custo -->
                <div class="flex gap-2 pt-1">
                    <Info class="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                    <p class="text-xs text-muted-foreground">
                        {{ t('plannerate.sidebar.product_sales_summary.net_margin_note') }}
                    </p>
                </div>
            </div>

            <Separator />

            <!-- Período -->
            <div class="space-y-2">
                <h5 class="text-xs font-semibold text-foreground">
                    {{ t('plannerate.sidebar.product_sales_summary.sales_period') }}
                </h5>
                <div class="rounded-md bg-muted/30 px-3 py-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-muted-foreground"
                            >{{ t('plannerate.sidebar.product_sales_summary.first_sale') }}</span
                        >
                        <span class="font-medium text-foreground">
                            {{ formatDate(salesData.summary.first_sale_date) }}
                        </span>
                    </div>
                    <div class="mt-1 flex items-center justify-between text-xs">
                        <span class="text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.last_sale') }}</span>
                        <span class="font-medium text-foreground">
                            {{ formatDate(salesData.summary.last_sale_date) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estoque (sempre visível, mesmo sem vendas) -->
        <div v-if="!isLoading && !error" class="space-y-2 rounded-lg border p-3">
            <div class="flex items-center gap-2">
                <Package class="h-4 w-4 text-blue-600 dark:text-blue-500" />
                <h5 class="text-sm font-semibold text-foreground">
                    {{ t('plannerate.sidebar.product_sales_summary.stock_group') }}
                </h5>
            </div>
            <div class="rounded-md bg-muted/40 p-2.5">
                <p class="text-xs text-muted-foreground">
                    {{ t('plannerate.sidebar.product_sales_summary.current_stock') }}
                </p>
                <!-- Mostra o valor quando há estoque; senão, indica "Sem estoque" -->
                <p
                    v-if="hasStock"
                    class="text-xl font-bold text-foreground"
                >
                    {{ formatStock(currentStock ?? 0) }}
                </p>
                <p
                    v-else
                    class="text-sm font-medium text-muted-foreground"
                >
                    {{ t('plannerate.sidebar.product_sales_summary.no_stock') }}
                </p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import {
    BadgeCheck,
    BarChart3,
    Coins,
    Info,
    Loader2,
    Package,
    TrendingDown,
} from 'lucide-vue-next';
import { computed, watch } from 'vue';
import { Separator } from '@/components/ui/separator';
import { useProductSales } from '@/composables/plannerate/products/useProductSales';
import { useT } from '@/composables/useT';

interface Props {
    productId: string | null;
    /** Data inicial do período do planograma (filtra as vendas) */
    startDate?: string | null;
    /** Data final do período do planograma (filtra as vendas) */
    endDate?: string | null;
    /** Estoque atual do produto (unidades) — exibido no card de Estoque */
    currentStock?: number | null;
}

const props = defineProps<Props>();
const { t } = useT();

const { salesData, isLoading, error, loadSales, clearSales } =
    useProductSales();

/**
 * Há estoque quando o valor está definido e é maior que zero.
 * Caso contrário, o card mostra "Sem estoque".
 */
const hasStock = computed(
    () => props.currentStock != null && props.currentStock > 0,
);

// Recarrega quando o produto ou o período do planograma muda
watch(
    () => [props.productId, props.startDate, props.endDate] as const,
    ([newProductId, newStartDate, newEndDate]) => {
        if (newProductId) {
            loadSales(newProductId, newStartDate, newEndDate);
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

function formatPercent(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    }).format(value) + '%';
}

/**
 * Formata o estoque: inteiro quando não há fração, senão com até 2 casas decimais.
 */
function formatStock(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(value);
}

function formatDate(date: string | null): string {
    if (!date) {
return '-';
}

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(date));
}
</script>

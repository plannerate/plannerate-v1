<template>
    <Card class="h-fit">
        <CardHeader>
            <CardTitle class="text-sm">{{ t('plannerate.analysis.selection.product_details') }}</CardTitle>
            <CardDescription class="text-xs">
                {{
                    selected
                        ? t('plannerate.analysis.paper_selection.selected_summary')
                        : t('plannerate.analysis.selection.select_from_table')
                }}
            </CardDescription>
        </CardHeader>
        <CardContent class="max-h-[55vh] overflow-y-auto pt-0">
            <div v-if="selected" class="space-y-2">
                <!-- Dados do produto -->
                <div class="rounded-lg border border-border bg-accent/40 p-2">
                    <div class="flex gap-2">
                        <div
                            v-if="selected.image_url"
                            class="size-14 shrink-0 overflow-hidden rounded-md border border-border bg-background"
                        >
                            <img
                                :src="selected.image_url"
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
                            <p class="mt-0.5 text-[11px] text-muted-foreground">
                                {{ selected.category_name || t('plannerate.analysis.selection.no_category') }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Papel estratégico do produto -->
                <div
                    :class="[
                        'rounded-lg border p-2 text-center',
                        roleStyle(selected.role).border,
                        roleStyle(selected.role).bg,
                    ]"
                >
                    <div :class="['text-lg font-bold', roleStyle(selected.role).text]">
                        {{ roleLabel(selected.role) }}
                    </div>
                    <div class="mt-0.5 text-[10px] text-muted-foreground">
                        {{ roleDescription(selected.role) }}
                    </div>
                </div>

                <!-- Indicadores numéricos -->
                <div class="rounded-lg border border-border bg-background p-2 text-[11px]">
                    <p class="mb-1 text-muted-foreground">{{ t('plannerate.analysis.selection.indicators') }}</p>
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ t('plannerate.analysis.paper_selection.market_share') }}</span>
                            <span class="font-semibold text-foreground">{{ formatPercent(selected.market_share) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ t('plannerate.analysis.paper_selection.growth_rate') }}</span>
                            <span
                                :class="[
                                    'font-semibold',
                                    selected.growth_rate >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400',
                                ]"
                            >
                                {{ formatPercent(selected.growth_rate) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ t('plannerate.analysis.paper_selection.share_threshold') }}</span>
                            <span class="font-semibold text-foreground">{{ formatPercent(selected.share_threshold) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ t('plannerate.analysis.paper_selection.value_current') }}</span>
                            <span class="font-semibold text-foreground">{{ formatCurrency(selected.total_value_current) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ t('plannerate.analysis.paper_selection.value_previous') }}</span>
                            <span class="font-semibold text-foreground">{{ formatCurrency(selected.total_value_previous) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Histórico de vendas do produto -->
                <ProductSalesSummary :product-id="selected.product_id" />
            </div>

            <div
                v-else
                class="rounded-lg border border-dashed border-border bg-accent/20 p-3 text-center text-xs text-muted-foreground"
            >
                {{ t('plannerate.analysis.selection.click_row_for_details') }}
            </div>
        </CardContent>
    </Card>
</template>

<script setup lang="ts">
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useT } from '@/composables/useT';
import ProductSalesSummary from '../../sidebar/properties/partials/ProductSalesSummary.vue';
import type { PaperResult, ProductRole } from './types';

defineProps<{
    selected: PaperResult | null;
}>();

const { t } = useT();

const formatPercent = (value: number): string => `${value.toFixed(2)}%`;

const formatCurrency = (value: number): string =>
    new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value ?? 0);

/**
 * Rótulo PT-BR para cada papel estratégico.
 */
const roleLabel = (role: ProductRole): string => {
    const labels: Record<ProductRole, string> = {
        leader:  t('plannerate.analysis.paper_selection.leader'),
        anchor:  t('plannerate.analysis.paper_selection.anchor'),
        rising:  t('plannerate.analysis.paper_selection.rising'),
        lagging: t('plannerate.analysis.paper_selection.lagging'),
    };

    return labels[role] ?? role;
};

/**
 * Descrição estratégica de cada papel.
 */
const roleDescription = (role: ProductRole): string => {
    const descriptions: Record<ProductRole, string> = {
        leader:  t('plannerate.analysis.paper_selection.leader_desc'),
        anchor:  t('plannerate.analysis.paper_selection.anchor_desc'),
        rising:  t('plannerate.analysis.paper_selection.rising_desc'),
        lagging: t('plannerate.analysis.paper_selection.lagging_desc'),
    };

    return descriptions[role] ?? '';
};

/**
 * Classes de cor do card de papel por role.
 */
const roleStyle = (role: ProductRole): { border: string; bg: string; text: string } => {
    const styles: Record<ProductRole, { border: string; bg: string; text: string }> = {
        leader: {
            border: 'border-yellow-300 dark:border-yellow-700',
            bg:     'bg-yellow-50 dark:bg-yellow-950/30',
            text:   'text-yellow-700 dark:text-yellow-300',
        },
        anchor: {
            border: 'border-green-300 dark:border-green-700',
            bg:     'bg-green-50 dark:bg-green-950/30',
            text:   'text-green-700 dark:text-green-300',
        },
        rising: {
            border: 'border-blue-300 dark:border-blue-700',
            bg:     'bg-blue-50 dark:bg-blue-950/30',
            text:   'text-blue-700 dark:text-blue-300',
        },
        lagging: {
            border: 'border-red-300 dark:border-red-700',
            bg:     'bg-red-50 dark:bg-red-950/30',
            text:   'text-red-700 dark:text-red-300',
        },
    };

    return styles[role] ?? styles.lagging;
};
</script>

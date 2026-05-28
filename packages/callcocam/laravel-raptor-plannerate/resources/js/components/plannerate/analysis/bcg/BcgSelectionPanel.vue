<template>
    <Card class="h-fit">
        <CardHeader>
            <CardTitle class="text-sm">{{ t('plannerate.analysis.selection.product_details') }}</CardTitle>
            <CardDescription class="text-xs">
                {{
                    selected
                        ? t('plannerate.analysis.bcg_selection.selected_summary')
                        : t('plannerate.analysis.selection.select_from_table')
                }}
            </CardDescription>
        </CardHeader>
        <CardContent class="max-h-[55vh] overflow-y-auto pt-0">
            <div v-if="selected" class="space-y-2">
                <!-- Produto -->
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

                <!-- Quadrante BCG -->
                <div
                    :class="[
                        'rounded-lg border p-2 text-center',
                        quadrantStyle(selected.quadrant).border,
                        quadrantStyle(selected.quadrant).bg,
                    ]"
                >
                    <div :class="['text-lg font-bold', quadrantStyle(selected.quadrant).text]">
                        {{ quadrantLabel(selected.quadrant) }}
                    </div>
                    <div class="mt-0.5 text-[10px] text-muted-foreground">
                        {{ quadrantDescription(selected.quadrant) }}
                    </div>
                </div>

                <!-- Indicadores -->
                <div class="rounded-lg border border-border bg-background p-2 text-[11px]">
                    <p class="mb-1 text-muted-foreground">{{ t('plannerate.analysis.selection.indicators') }}</p>
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ t('plannerate.analysis.bcg_selection.market_share') }}</span>
                            <span class="font-semibold text-foreground">{{ formatPercent(selected.market_share) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ t('plannerate.analysis.bcg_selection.growth_rate') }}</span>
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
                            <span class="text-muted-foreground">{{ t('plannerate.analysis.bcg_selection.share_threshold') }}</span>
                            <span class="font-semibold text-foreground">{{ formatPercent(selected.share_threshold) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ t('plannerate.analysis.bcg_selection.value_current') }}</span>
                            <span class="font-semibold text-foreground">{{ formatCurrency(selected.total_value_current) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ t('plannerate.analysis.bcg_selection.value_previous') }}</span>
                            <span class="font-semibold text-foreground">{{ formatCurrency(selected.total_value_previous) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Histórico de vendas -->
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
import type { BcgQuadrant, BcgResult } from './types';

defineProps<{
    selected: BcgResult | null;
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
 * Rótulo PT-BR para cada quadrante BCG
 */
const quadrantLabel = (quadrant: BcgQuadrant): string => {
    const labels: Record<BcgQuadrant, string> = {
        star: t('plannerate.analysis.bcg_selection.star'),
        cash_cow: t('plannerate.analysis.bcg_selection.cash_cow'),
        question_mark: t('plannerate.analysis.bcg_selection.question_mark'),
        dog: t('plannerate.analysis.bcg_selection.dog'),
    };

    return labels[quadrant] ?? quadrant;
};

/**
 * Descrição do quadrante BCG
 */
const quadrantDescription = (quadrant: BcgQuadrant): string => {
    const descriptions: Record<BcgQuadrant, string> = {
        star: t('plannerate.analysis.bcg_selection.star_desc'),
        cash_cow: t('plannerate.analysis.bcg_selection.cash_cow_desc'),
        question_mark: t('plannerate.analysis.bcg_selection.question_mark_desc'),
        dog: t('plannerate.analysis.bcg_selection.dog_desc'),
    };

    return descriptions[quadrant] ?? '';
};

/**
 * Classes de estilo por quadrante
 */
const quadrantStyle = (quadrant: BcgQuadrant) => {
    const styles: Record<BcgQuadrant, { border: string; bg: string; text: string }> = {
        star: {
            border: 'border-yellow-300 dark:border-yellow-700',
            bg: 'bg-yellow-50 dark:bg-yellow-950/30',
            text: 'text-yellow-700 dark:text-yellow-300',
        },
        cash_cow: {
            border: 'border-green-300 dark:border-green-700',
            bg: 'bg-green-50 dark:bg-green-950/30',
            text: 'text-green-700 dark:text-green-300',
        },
        question_mark: {
            border: 'border-blue-300 dark:border-blue-700',
            bg: 'bg-blue-50 dark:bg-blue-950/30',
            text: 'text-blue-700 dark:text-blue-300',
        },
        dog: {
            border: 'border-red-300 dark:border-red-700',
            bg: 'bg-red-50 dark:bg-red-950/30',
            text: 'text-red-700 dark:text-red-300',
        },
    };

    return styles[quadrant] ?? styles.dog;
};
</script>

<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useT } from '@/composables/useT';
import ProductSalesSummary from '../../sidebar/properties/partials/ProductSalesSummary.vue';
import { useBcgLabels } from './labels';
import type { BcgResult } from './types';

defineProps<{
    selected: BcgResult | null;
}>();

const { t } = useT();
const {
    axisLabel,
    quadrantLabel,
    quadrantDescription,
    quadrantStyle,
    spaceActionLabel,
    spaceActionIcon,
    spaceActionClass,
} = useBcgLabels();

const formatPercent = (value: number): string => `${(value ?? 0).toFixed(2)}%`;

const formatNumber = (value: number): string =>
    new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 }).format(value ?? 0);
</script>

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
                <!-- Dados do produto -->
                <div class="rounded-lg border border-border bg-accent/40 p-2">
                    <div class="flex gap-2">
                        <div
                            v-if="selected.image_url"
                            class="size-14 shrink-0 overflow-hidden rounded-md border border-border bg-background"
                        >
                            <img :src="selected.image_url" :alt="selected.product_name" class="h-full w-full object-contain" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-2 text-xs font-semibold text-foreground">{{ selected.product_name }}</p>
                            <p class="mt-0.5 font-mono text-[11px] text-muted-foreground">{{ selected.ean }}</p>
                            <p class="mt-0.5 text-[11px] text-muted-foreground">
                                {{ selected.category_name || t('plannerate.analysis.selection.no_category') }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Quadrante -->
                <div
                    :class="[
                        'rounded-lg border p-2 text-center',
                        quadrantStyle(selected.quadrant).border,
                        quadrantStyle(selected.quadrant).bg,
                    ]"
                >
                    <div :class="['text-base font-bold', quadrantStyle(selected.quadrant).text]">
                        {{ quadrantLabel(selected.quadrant, selected.x_axis, selected.y_axis) }}
                    </div>
                    <div class="mt-0.5 text-[10px] text-muted-foreground">
                        {{ quadrantDescription(selected.quadrant) }}
                    </div>
                </div>

                <!-- Avisos que mudam a leitura do quadrante -->
                <div v-if="selected.is_borderline || selected.alerta_margem_negativa || selected.sem_venda" class="space-y-1">
                    <p v-if="selected.is_borderline" class="rounded-md border border-amber-300 bg-amber-50 px-2 py-1 text-[10px] text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        ≈ {{ t('plannerate.analysis.bcg_selection.borderline') }} — {{ t('plannerate.analysis.bcg_results.borderline_tooltip') }}
                    </p>
                    <p v-if="selected.alerta_margem_negativa" class="rounded-md border border-red-300 bg-red-50 px-2 py-1 text-[10px] text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200">
                        ⚠ {{ t('plannerate.analysis.bcg_selection.negative_margin') }}
                    </p>
                    <p v-if="selected.sem_venda" class="rounded-md border border-border bg-muted px-2 py-1 text-[10px] text-muted-foreground">
                        {{ t('plannerate.analysis.bcg_selection.no_sales') }} — {{ t('plannerate.analysis.bcg_selection.no_sales_hint') }}
                    </p>
                </div>

                <!-- Indicadores dos eixos -->
                <div class="rounded-lg border border-border bg-background p-2 text-[11px]">
                    <p class="mb-1 text-muted-foreground">{{ t('plannerate.analysis.bcg_selection.indicators') }}</p>
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ axisLabel(selected.x_axis) }}</span>
                            <span class="font-semibold text-foreground">{{ formatNumber(selected.x_value) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ axisLabel(selected.y_axis) }}</span>
                            <span :class="['font-semibold', selected.y_value < 0 ? 'text-red-600 dark:text-red-400' : 'text-foreground']">
                                {{ formatNumber(selected.y_value) }}
                            </span>
                        </div>
                        <!-- A linha de corte do GRUPO: sem ela o valor bruto não diz nada -->
                        <div class="flex items-center justify-between border-t border-border pt-1">
                            <span class="text-muted-foreground">
                                {{ t('plannerate.analysis.bcg_selection.threshold') }} ({{ selected.group_name || '—' }})
                            </span>
                            <span class="font-mono text-[10px] text-muted-foreground">
                                {{ formatNumber(selected.x_threshold) }} / {{ formatNumber(selected.y_threshold) }}
                            </span>
                        </div>
                        <!-- Score contínuo por trás do rótulo discreto -->
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ t('plannerate.analysis.bcg_selection.percentil') }}</span>
                            <span class="font-semibold text-foreground">
                                {{ formatPercent(selected.x_percentil) }} / {{ formatPercent(selected.y_percentil) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Espaço na gôndola: é o cruzamento com o quadrante que vira ação -->
                <div class="rounded-lg border border-border bg-background p-2 text-[11px]">
                    <p class="mb-1 text-muted-foreground">{{ t('plannerate.analysis.bcg_selection.space') }}</p>
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ t('plannerate.analysis.bcg_selection.facings') }}</span>
                            <span class="font-semibold text-foreground">{{ selected.facings }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ t('plannerate.analysis.bcg_selection.linear_cm') }}</span>
                            <span class="font-semibold text-foreground">{{ formatNumber(selected.espaco_linear_cm) }} cm</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ t('plannerate.analysis.bcg_selection.share_gondola') }}</span>
                            <span v-if="selected.sem_dimensao" class="cursor-help text-muted-foreground" :title="t('plannerate.analysis.bcg_results.no_dimension_tooltip')">—</span>
                            <span v-else class="font-semibold text-foreground">{{ formatPercent(selected.share_gondola) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ t('plannerate.analysis.bcg_selection.share_threshold_gondola') }}</span>
                            <span class="font-mono text-[10px] text-muted-foreground">{{ formatPercent(selected.share_threshold_gondola) }}</span>
                        </div>
                        <div class="flex items-center justify-between border-t border-border pt-1">
                            <span class="text-muted-foreground">{{ t('plannerate.analysis.bcg_selection.action') }}</span>
                            <Badge variant="outline" :class="['text-[10px] font-semibold', spaceActionClass(selected.acao_espaco)]">
                                <span aria-hidden="true" class="mr-0.5">{{ spaceActionIcon(selected.acao_espaco) }}</span>
                                {{ spaceActionLabel(selected.acao_espaco) }}
                            </Badge>
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

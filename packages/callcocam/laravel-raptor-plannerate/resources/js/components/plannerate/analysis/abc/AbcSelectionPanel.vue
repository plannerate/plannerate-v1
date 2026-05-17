<template>
    <Card class="h-fit">
        <CardHeader>
            <CardTitle class="text-sm">{{ t('plannerate.analysis.selection.product_details') }}</CardTitle>
            <CardDescription class="text-xs">
                {{
                    selected
                        ? t('plannerate.analysis.abc_selection.selected_summary')
                        : t('plannerate.analysis.selection.select_from_table')
                }}
            </CardDescription>
        </CardHeader>
        <CardContent class="max-h-[55vh] overflow-y-auto pt-0">
            <div v-if="selected" class="space-y-2">
                <div class="rounded-lg border border-border bg-accent/40 p-2">
                    <div class="flex gap-2">
                        <div v-if="selected.image_url"
                            class="size-14 shrink-0 overflow-hidden rounded-md border border-border bg-background">
                            <img :src="selected.image_url" :alt="selected.product_name"
                                class="h-full w-full object-contain" />
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

                <div class="grid grid-cols-2 gap-1 text-[11px]">
                    <div class="rounded-md border border-border bg-background p-1.5">
                        <div class="text-muted-foreground">{{ t('plannerate.analysis.selection.class') }}</div>
                        <div class="font-semibold text-foreground">
                            {{ selected.classificacao }}
                        </div>
                    </div>
                    <div class="rounded-md border border-border bg-background p-1.5">
                        <div class="text-muted-foreground">{{ t('plannerate.analysis.selection.ranking') }}</div>
                        <div class="font-semibold text-foreground">
                            {{ selected.ranking }}
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-border bg-background p-2 text-[11px]">
                    <p class="mb-1 text-muted-foreground">{{ t('plannerate.analysis.selection.indicators') }}</p>
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{
                                t('plannerate.analysis.abc_selection.weighted_average') }}</span>
                            <span class="font-semibold text-foreground">{{
                                selected.media_ponderada.toFixed(2)
                                }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{
                                t('plannerate.analysis.abc_selection.individual_percent') }}</span>
                            <span class="font-semibold text-foreground">{{
                                formatPercent(selected.percentual_individual)
                                }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{
                                t('plannerate.analysis.abc_selection.accumulated_percent') }}</span>
                            <span class="font-semibold text-foreground">{{
                                formatPercent(selected.percentual_acumulado)
                                }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ t('plannerate.analysis.abc_selection.quantity')
                                }}</span>
                            <span class="font-semibold text-foreground">{{
                                selected.qtde
                                }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ t('plannerate.analysis.abc_selection.value')
                                }}</span>
                            <span class="font-semibold text-foreground">{{
                                formatCurrency(selected.valor)
                                }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ t('plannerate.analysis.abc_selection.margin')
                                }}</span>
                            <span class="font-semibold text-foreground">{{
                                formatCurrency(selected.margem)
                                }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-border bg-background p-2 text-[11px]">
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">{{ t('plannerate.analysis.abc_selection.remove_from_mix')
                            }}</span>
                        <Badge :variant="selected.retirar_do_mix
                                ? 'destructive'
                                : 'outline'
                            " class="text-[10px]">
                            {{ selected.retirar_do_mix ? t('plannerate.common.yes') : t('plannerate.common.no') }}
                        </Badge>
                    </div>
                    <div class="mt-1 flex items-center justify-between">
                        <span class="text-muted-foreground">{{ t('plannerate.analysis.selection.status') }}</span>
                        <Badge :variant="selected.status?.status === 'Ativo'
                                ? 'default'
                                : 'outline'
                            " class="text-[10px]">
                            {{ selected.status?.status }}
                        </Badge>
                    </div>
                    <p class="mt-1 text-muted-foreground">
                        {{ selected.status?.motivo }}
                    </p>
                </div>
                <!-- Product Sales Summary -->
                <ProductSalesSummary :product-id="selected.product_id" />
                <ButtonWithTooltip v-if="selected.retirar_do_mix" variant="destructive" size="sm" class="w-full"
                    :tooltip="t('plannerate.analysis.abc_selection.remove_segment_tooltip')"
                    @click="emit('remove-from-planogram', selected.product_id)">
                    <Trash2 class="mr-2 size-4" />
                    {{ t('plannerate.analysis.abc_selection.remove_from_planogram') }}
                </ButtonWithTooltip>
            </div>

            <div v-else
                class="rounded-lg border border-dashed border-border bg-accent/20 p-3 text-center text-xs text-muted-foreground">
                {{ t('plannerate.analysis.selection.click_row_for_details') }}
            </div>
        </CardContent>
    </Card>
</template>

<script setup lang="ts">
import { Trash2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import ButtonWithTooltip from '@/components/ui/ButtonWithTooltip.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useT } from '@/composables/useT';
import ProductSalesSummary from '../../sidebar/properties/partials/ProductSalesSummary.vue';
import type { AbcResult } from './types';

defineProps<{
    selected: AbcResult | null;
}>();

const emit = defineEmits<{
    'remove-from-planogram': [productId: string];
}>();
const { t } = useT();

const formatPercent = (value: number): string => `${value.toFixed(2)}%`;

const formatCurrency = (value: number): string => {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value ?? 0);
};
</script>

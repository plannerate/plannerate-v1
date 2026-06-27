<script setup lang="ts">
import { Hash, Package, Percent, Tag } from 'lucide-vue-next';
import { computed } from 'vue';
import { useT } from '@/composables/useT';
import type { ProductInfo, SalesTotals } from './types';

const props = defineProps<{
    product: ProductInfo;
    totals: SalesTotals;
}>();

const { t } = useT();

/**
 * Percentual de registros em promoção sobre o total de registros.
 * Retorna '0,0%' quando não há registros.
 */
const promoPercent = computed<string>(() => {
    if (!props.totals.total_records) {
        return '0,0%';
    }

    const pct = (props.totals.promo_records / props.totals.total_records) * 100;

    return `${pct.toFixed(1).replace('.', ',')}%`;
});
</script>

<template>
    <!-- ── Identificação do produto ─────────────────────────────────────── -->
    <div class="mb-6 flex items-center gap-4 rounded-xl border border-border bg-card p-4">
        <img
            v-if="product.image_url"
            :src="product.image_url"
            :alt="product.name ?? ''"
            class="size-16 shrink-0 rounded-lg object-contain ring-1 ring-border"
        />
        <div
            v-else
            class="flex size-16 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground ring-1 ring-border"
        >
            <Package class="size-7" />
        </div>
        <div class="min-w-0">
            <p class="truncate text-lg font-semibold text-foreground">
                {{ product.name ?? product.codigo_erp ?? '-' }}
            </p>
            <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                <span v-if="product.codigo_erp" class="inline-flex items-center gap-1">
                    <Hash class="size-3.5" /> {{ product.codigo_erp }}
                </span>
                <span v-if="product.ean" class="inline-flex items-center gap-1">
                    <Tag class="size-3.5" /> EAN {{ product.ean }}
                </span>
                <span
                    v-if="totals.total_records > 0"
                    class="inline-flex items-center gap-1 text-orange-600 dark:text-orange-400"
                >
                    <Percent class="size-3.5" /> {{ promoPercent }}
                    {{ t('app.tenant.products.sales.dashboard.promo_records').toLowerCase() }}
                </span>
            </div>
        </div>
    </div>
</template>

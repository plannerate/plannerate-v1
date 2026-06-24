<script setup lang="ts">
import { Package } from 'lucide-vue-next'
import { useT } from '@/composables/useT'
import type { Product } from '@/types/planogram'

interface Props {
  product: Product
}

defineProps<Props>()

const { t } = useT()
</script>

<template>
  <div class="space-y-2">
    <div class="flex items-center gap-1.5">
      <Package class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
      <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
        {{ t('plannerate.print.product_detail.identification') }}
      </span>
    </div>

    <!-- EAN / Código (linha no topo) -->
    <div class="grid grid-cols-2 gap-2">
      <div>
        <p class="text-[10px] text-muted-foreground">{{ t('plannerate.print.product_detail.code') }}</p>
        <p class="font-mono text-xs">{{ product.codigo_erp || '—' }}</p>
      </div>
      <div>
        <p class="text-[10px] text-muted-foreground">{{ t('plannerate.analysis.results.ean') }}</p>
        <p class="font-mono text-xs">{{ product.ean || '—' }}</p>
      </div>
    </div>

    <!-- Nome -->
    <div>
      <p class="text-[10px] text-muted-foreground">{{ t('plannerate.print.product_detail.name') }}</p>
      <p class="text-sm font-bold leading-tight">{{ product.name || '—' }}</p>
    </div>

    <!-- Marca -->
    <div>
      <p class="text-[10px] text-muted-foreground">{{ t('plannerate.print.product_detail.brand') }}</p>
      <p class="text-xs font-medium">{{ product.brand || '—' }}</p>
    </div>

    <!-- Categoria -->
    <div>
      <p class="text-[10px] text-muted-foreground">{{ t('plannerate.print.product_detail.category') }}</p>
      <p class="text-xs leading-tight">{{ product.category_full_path ?? product.category ?? '—' }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useT } from '@/composables/useT'
import type { Product } from '@/types/planogram'

interface Props {
  product: Product
}

defineProps<Props>()

const { t } = useT()
</script>

<template>
  <!-- ordem: Altura, Largura, Profundidade -->
  <div class="flex items-end gap-1.5 rounded border bg-muted/20 px-2.5 py-2">
    <!-- Altura -->
    <div class="flex flex-col items-start">
      <span class="text-[9px] text-muted-foreground leading-none mb-0.5">{{ t('plannerate.print.product_detail.height') }}</span>
      <span class="text-lg font-bold leading-none tabular-nums">
        {{ product.height ?? '—' }}<sup class="text-[9px] font-normal text-muted-foreground align-super">cm</sup>
      </span>
    </div>
    <span class="text-muted-foreground text-sm pb-0.5">×</span>
    <!-- Largura -->
    <div class="flex flex-col items-start">
      <span class="text-[9px] text-muted-foreground leading-none mb-0.5">{{ t('plannerate.print.product_detail.width') }}</span>
      <span class="text-lg font-bold leading-none tabular-nums">
        {{ product.width ?? '—' }}<sup class="text-[9px] font-normal text-muted-foreground align-super">cm</sup>
      </span>
    </div>
    <span class="text-muted-foreground text-sm pb-0.5">×</span>
    <!-- Profundidade -->
    <div class="flex flex-col items-start">
      <span class="text-[9px] text-muted-foreground leading-none mb-0.5">{{ t('plannerate.print.product_detail.depth') }}</span>
      <span class="text-lg font-bold leading-none tabular-nums">
        {{ product.depth ?? '—' }}<sup class="text-[9px] font-normal text-muted-foreground align-super">cm</sup>
      </span>
    </div>
  </div>
</template>

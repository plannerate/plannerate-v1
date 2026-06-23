<script setup lang="ts">
import { computed } from 'vue'
import { useT } from '@/composables/useT'

interface Props {
  /** Frentes lado a lado (layer.quantity) */
  layerQuantity?: number
  /** Empilhamento vertical (segment.quantity) */
  segmentQuantity?: number
  /** Profundidade do produto em cm (product.depth) */
  productDepth?: number
  /** Profundidade da prateleira em cm (shelf.shelf_depth) */
  shelfDepth?: number
}

const props = defineProps<Props>()

const { t } = useT()

/**
 * Quantos produtos cabem na profundidade da prateleira:
 * profundidade da prateleira ÷ profundidade do produto (arredondado p/ baixo).
 * Mínimo 1 quando não há dados suficientes.
 */
const itemsInDepth = computed(() => {
  const depth = props.productDepth ?? 0
  if (!props.shelfDepth || !depth) {
    return 1
  }
  return Math.max(1, Math.floor(props.shelfDepth / depth))
})

/** Total de unidades = frentes × empilhamento × profundidade */
const totalQuantity = computed(
  () => (props.layerQuantity ?? 1) * (props.segmentQuantity ?? 1) * itemsInDepth.value,
)
</script>

<template>
  <div class="flex items-end gap-1.5 rounded border bg-muted/20 px-2.5 py-2">
    <!-- Frentes -->
    <div class="flex flex-col items-start">
      <span class="text-[9px] text-muted-foreground leading-none mb-0.5">{{ t('plannerate.print.product_detail.fronts') }}</span>
      <span class="text-lg font-bold leading-none tabular-nums">
        {{ layerQuantity ?? 1 }}<sup class="text-[9px] font-normal text-muted-foreground align-super">un.</sup>
      </span>
    </div>
    <span class="text-muted-foreground text-sm pb-0.5">×</span>
    <!-- Empilhamento -->
    <div class="flex flex-col items-start">
      <span class="text-[9px] text-muted-foreground leading-none mb-0.5">{{ t('plannerate.print.product_detail.stacking') }}</span>
      <span class="text-lg font-bold leading-none tabular-nums">
        {{ segmentQuantity ?? 1 }}<sup class="text-[9px] font-normal text-muted-foreground align-super">un.</sup>
      </span>
    </div>
    <span class="text-muted-foreground text-sm pb-0.5">×</span>
    <!-- Profundidade -->
    <div class="flex flex-col items-start">
      <span class="text-[9px] text-muted-foreground leading-none mb-0.5">{{ t('plannerate.print.product_detail.depth') }}</span>
      <span class="text-lg font-bold leading-none tabular-nums">
        {{ itemsInDepth }}<sup class="text-[9px] font-normal text-muted-foreground align-super">un.</sup>
      </span>
    </div>
    <span class="text-muted-foreground text-sm pb-0.5">=</span>
    <!-- Total -->
    <div class="flex flex-col items-start">
      <span class="text-[9px] text-muted-foreground leading-none mb-0.5">{{ t('plannerate.print.product_detail.total') }}</span>
      <span class="text-lg font-bold leading-none tabular-nums text-primary">
        {{ totalQuantity }}<sup class="text-[9px] font-normal text-muted-foreground align-super">un.</sup>
      </span>
    </div>
  </div>
</template>

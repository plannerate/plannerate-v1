<script setup lang="ts">
import { computed } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { useAbcClassification } from '@/composables/plannerate/useAbcClassification'
import { useTargetStockAnalysis } from '@/composables/plannerate/useTargetStockAnalysis'
import { useT } from '@/composables/useT'
import type { Product } from '@/types/planogram'

interface Props {
  open: boolean
  product?: Product | null
  segmentQuantity?: number
  layerQuantity?: number
  shelfDepth?: number
}

const props = defineProps<Props>()
const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { getClassification } = useAbcClassification()
const { getTargetStockData, calculateSegmentCapacity, getStockStatus, DEFAULT_TOLERANCE } = useTargetStockAnalysis()
const { t } = useT()

// Classificação ABC
const abcClassification = computed(() => {
  const ean = props.product?.ean

  return getClassification(ean)
})

// Dados de Target Stock
const targetStockData = computed(() => {
  const ean = props.product?.ean

  if (!ean) {
return null
}

  return getTargetStockData(ean)
})

// Capacidade do segment atual
const segmentCapacity = computed(() => {
  const productDepth = props.product?.depth ?? 0

  return calculateSegmentCapacity(
    props.segmentQuantity ?? 1,
    props.layerQuantity ?? 1,
    productDepth,
    props.shelfDepth ?? 0
  )
})

// Status do estoque
const stockStatus = computed(() => {
  if (!targetStockData.value) {
return null
}

  return getStockStatus(
    segmentCapacity.value,
    targetStockData.value.estoque_alvo,
    DEFAULT_TOLERANCE
  )
})

// Cor do badge ABC
const abcBadgeClass = computed(() => {
  switch (abcClassification.value) {
    case 'A': return 'bg-green-500 hover:bg-green-500 text-white'
    case 'B': return 'bg-yellow-500 hover:bg-yellow-500 text-gray-900'
    case 'C': return 'bg-red-500 hover:bg-red-500 text-white'
    default: return 'bg-gray-400'
  }
})

// Status do estoque com cor
const stockStatusInfo = computed(() => {
  switch (stockStatus.value) {
    case 'increase': return { label: t('plannerate.print.product_detail.increase_space'), class: 'bg-red-500 hover:bg-red-500 text-white', icon: '↑' }
    case 'decrease': return { label: t('plannerate.print.product_detail.decrease_space'), class: 'bg-yellow-500 hover:bg-yellow-500 text-gray-900', icon: '↓' }
    case 'ok': return { label: t('plannerate.print.product_detail.space_ok'), class: 'bg-green-500 hover:bg-green-500 text-white', icon: '✓' }
    default: return null
  }
})

const totalQuantity = computed(() => {
  return (props.segmentQuantity ?? 1) * (props.layerQuantity ?? 1)
})

function handleClose() {
  emit('update:open', false)
}
</script>

<template>
  <Dialog :open="open" @update:open="handleClose" class="z-[500]">
    <DialogContent class="max-w-2xl max-h-[85vh] flex flex-col z-[1000]">
      <DialogHeader class="shrink-0">
        <DialogTitle>{{ t('plannerate.print.product_detail.title') }}</DialogTitle>
        <DialogDescription v-if="product">
          {{ t('plannerate.print.product_detail.description') }}
        </DialogDescription>
      </DialogHeader>

      <div v-if="product" class="flex-1 overflow-y-auto grid gap-6 py-4 pr-2">
        <!-- Imagem do produto -->
        <div class="flex justify-center">
          <img
            :src="product.image_url_encoded ?? product.image_url"
            :alt="product.name"
            class="max-h-36 w-auto rounded-lg border object-contain"
          />
        </div>

        <!-- Badges de Análise (ABC e Stock) -->
        <div v-if="abcClassification || stockStatusInfo" class="flex justify-center gap-3">
          <Badge v-if="abcClassification" :class="abcBadgeClass" class="text-sm px-3 py-1">
            ABC: {{ abcClassification }}
          </Badge>
          <Badge v-if="stockStatusInfo" :class="stockStatusInfo.class" class="text-sm px-3 py-1">
            {{ stockStatusInfo.icon }} {{ stockStatusInfo.label }}
          </Badge>
        </div>

        <!-- Informações do produto -->
        <div class="grid gap-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <div class="text-sm font-medium text-muted-foreground">{{ t('plannerate.print.product_detail.name') }}</div>
              <div class="text-lg font-semibold">{{ product.name }}</div>
            </div>
            
            <div>
              <div class="text-sm font-medium text-muted-foreground">{{ t('plannerate.analysis.results.ean') }}</div>
              <div class="font-mono text-sm">{{ product.ean || t('plannerate.print.product_detail.na') }}</div>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-4">
            <div>
              <div class="text-sm font-medium text-muted-foreground">{{ t('plannerate.print.product_detail.width') }}</div>
              <div class="text-lg">{{ product.width }} cm</div>
            </div>
            
            <div>
              <div class="text-sm font-medium text-muted-foreground">{{ t('plannerate.print.product_detail.height') }}</div>
              <div class="text-lg">{{ product.height }} cm</div>
            </div>

            <div>
              <div class="text-sm font-medium text-muted-foreground">{{ t('plannerate.print.product_detail.depth') }}</div>
              <div class="text-lg">{{ product.depth || t('plannerate.print.product_detail.na') }} cm</div>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-4">
            <div>
              <div class="text-sm font-medium text-muted-foreground">{{ t('plannerate.print.product_detail.fronts') }}</div>
              <div class="text-lg">{{ segmentQuantity ?? 1 }} un.</div>
            </div>
            
            <div>
              <div class="text-sm font-medium text-muted-foreground">{{ t('plannerate.print.product_detail.stacking') }}</div>
              <div class="text-lg">{{ layerQuantity ?? 1 }} un.</div>
            </div>

            <div>
              <div class="text-sm font-medium text-muted-foreground">{{ t('plannerate.print.product_detail.total_quantity') }}</div>
              <div class="text-lg font-semibold text-primary">{{ totalQuantity }} un.</div>
            </div>
          </div>

          <!-- Dados de Target Stock (quando disponível) -->
          <div v-if="targetStockData" class="border-t pt-4 mt-2">
            <h4 class="text-sm font-semibold text-muted-foreground mb-3">{{ t('plannerate.print.product_detail.stock_analysis') }}</h4>
            
            <div class="grid grid-cols-3 gap-4">
              <div>
                <div class="text-sm font-medium text-muted-foreground">{{ t('plannerate.print.product_detail.target_stock') }}</div>
                <div class="text-lg font-semibold text-blue-600">{{ targetStockData.estoque_alvo }} un.</div>
              </div>
              
              <div>
                <div class="text-sm font-medium text-muted-foreground">{{ t('plannerate.print.product_detail.min_stock') }}</div>
                <div class="text-lg">{{ targetStockData.estoque_minimo }} un.</div>
              </div>

              <div>
                <div class="text-sm font-medium text-muted-foreground">{{ t('plannerate.print.product_detail.current_capacity') }}</div>
                <div class="text-lg font-semibold" :class="{
                  'text-red-600': stockStatus === 'increase',
                  'text-yellow-600': stockStatus === 'decrease',
                  'text-green-600': stockStatus === 'ok',
                }">{{ segmentCapacity }} un.</div>
              </div>
            </div>

            <div class="grid grid-cols-3 gap-4 mt-3">
              <div>
                <div class="text-sm font-medium text-muted-foreground">{{ t('plannerate.print.product_detail.avg_demand') }}</div>
                <div class="text-lg">{{ targetStockData.demanda_media?.toFixed(2) ?? t('plannerate.print.product_detail.na') }}</div>
              </div>
              
              <div>
                <div class="text-sm font-medium text-muted-foreground">{{ t('plannerate.print.product_detail.coverage') }}</div>
                <div class="text-lg">{{ targetStockData.cobertura_dias }} dias</div>
              </div>

              <div>
                <div class="text-sm font-medium text-muted-foreground">{{ t('plannerate.print.product_detail.safety_stock') }}</div>
                <div class="text-lg">{{ targetStockData.estoque_seguranca }} un.</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>

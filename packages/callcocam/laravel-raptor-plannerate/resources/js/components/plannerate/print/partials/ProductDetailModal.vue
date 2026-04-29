<script setup lang="ts">
import { computed } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { useAbcClassification } from '@/composables/plannerate/useAbcClassification'
import { useTargetStockAnalysis } from '@/composables/plannerate/useTargetStockAnalysis'
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
    case 'increase': return { label: 'Aumentar Espaço', class: 'bg-red-500 hover:bg-red-500 text-white', icon: '↑' }
    case 'decrease': return { label: 'Diminuir Espaço', class: 'bg-yellow-500 hover:bg-yellow-500 text-gray-900', icon: '↓' }
    case 'ok': return { label: 'Espaço Adequado', class: 'bg-green-500 hover:bg-green-500 text-white', icon: '✓' }
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
        <DialogTitle>Detalhes do Produto</DialogTitle>
        <DialogDescription v-if="product">
          Informações completas sobre o produto selecionado
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
              <div class="text-sm font-medium text-muted-foreground">Nome</div>
              <div class="text-lg font-semibold">{{ product.name }}</div>
            </div>
            
            <div>
              <div class="text-sm font-medium text-muted-foreground">EAN</div>
              <div class="font-mono text-sm">{{ product.ean || 'N/A' }}</div>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-4">
            <div>
              <div class="text-sm font-medium text-muted-foreground">Largura</div>
              <div class="text-lg">{{ product.width }} cm</div>
            </div>
            
            <div>
              <div class="text-sm font-medium text-muted-foreground">Altura</div>
              <div class="text-lg">{{ product.height }} cm</div>
            </div>

            <div>
              <div class="text-sm font-medium text-muted-foreground">Profundidade</div>
              <div class="text-lg">{{ product.depth || 'N/A' }} cm</div>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-4">
            <div>
              <div class="text-sm font-medium text-muted-foreground">Frentes</div>
              <div class="text-lg">{{ segmentQuantity ?? 1 }} un.</div>
            </div>
            
            <div>
              <div class="text-sm font-medium text-muted-foreground">Empilhamento</div>
              <div class="text-lg">{{ layerQuantity ?? 1 }} un.</div>
            </div>

            <div>
              <div class="text-sm font-medium text-muted-foreground">Quantidade Total</div>
              <div class="text-lg font-semibold text-primary">{{ totalQuantity }} un.</div>
            </div>
          </div>

          <!-- Dados de Target Stock (quando disponível) -->
          <div v-if="targetStockData" class="border-t pt-4 mt-2">
            <h4 class="text-sm font-semibold text-muted-foreground mb-3">📊 Análise de Estoque</h4>
            
            <div class="grid grid-cols-3 gap-4">
              <div>
                <div class="text-sm font-medium text-muted-foreground">Estoque Alvo</div>
                <div class="text-lg font-semibold text-blue-600">{{ targetStockData.estoque_alvo }} un.</div>
              </div>
              
              <div>
                <div class="text-sm font-medium text-muted-foreground">Estoque Mínimo</div>
                <div class="text-lg">{{ targetStockData.estoque_minimo }} un.</div>
              </div>

              <div>
                <div class="text-sm font-medium text-muted-foreground">Capacidade Atual</div>
                <div class="text-lg font-semibold" :class="{
                  'text-red-600': stockStatus === 'increase',
                  'text-yellow-600': stockStatus === 'decrease',
                  'text-green-600': stockStatus === 'ok',
                }">{{ segmentCapacity }} un.</div>
              </div>
            </div>

            <div class="grid grid-cols-3 gap-4 mt-3">
              <div>
                <div class="text-sm font-medium text-muted-foreground">Demanda Média</div>
                <div class="text-lg">{{ targetStockData.demanda_media?.toFixed(2) ?? 'N/A' }}</div>
              </div>
              
              <div>
                <div class="text-sm font-medium text-muted-foreground">Cobertura</div>
                <div class="text-lg">{{ targetStockData.cobertura_dias }} dias</div>
              </div>

              <div>
                <div class="text-sm font-medium text-muted-foreground">Estoque Segurança</div>
                <div class="text-lg">{{ targetStockData.estoque_seguranca }} un.</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>

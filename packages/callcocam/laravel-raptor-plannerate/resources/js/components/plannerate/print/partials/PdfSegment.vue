<script setup lang="ts">
import { ref, computed } from 'vue'
import { useAbcClassification } from '@/composables/plannerate/analysis/useAbcClassification'
import { useBcgAnalysis } from '@/composables/plannerate/analysis/useBcgAnalysis'
import type { Segment } from '@/types/planogram'
import ProductIndicatorBadge from '../../editor/ProductIndicatorBadge.vue'
import PdfAbcBadge from './PdfAbcBadge.vue'
import PdfBcgBadge from './PdfBcgBadge.vue'
import PdfLayer from './PdfLayer.vue'
import PdfStockIndicator from './PdfStockIndicator.vue'
import ProductDetailModal from './ProductDetailModal.vue'
import ProductDetailModalShare from './ProductDetailModalShare.vue'

interface Props {
  segment: Segment
  scaleFactor: number
  shelfDepth?: number
  /**
   * Gap uniforme (px) do modo justificar. Repassado ao layer como column-gap
   * entre as frentes, para os produtos do segmento se distribuírem com o mesmo
   * espaçamento dos demais segmentos da prateleira.
   */
  facingGap?: number
  isShare?: boolean
}

const props = defineProps<Props>()

const isHovered = ref(false)
const showModal = ref(false)

const { getClassification, getRecommendation } = useAbcClassification()
const { getBcgData } = useBcgAnalysis()

// Busca quadrante BCG + ação de espaço do produto pelo EAN (mesmo selo do editor)
const bcgBadgeData = computed(() => getBcgData(props.segment.layer?.product?.ean))

// Busca classificação ABC do produto pelo EAN
const abcClassification = computed(() => {
  const ean = props.segment.layer?.product?.ean

  if (!ean) {
return undefined
}

  return getClassification(ean)
})

// Busca a recomendação de sortimento (proteger/potencializar/monitorar/retirar)
// pelo EAN, para exibir a label ao lado da classe (mesmo padrão do editor)
const abcRecommendation = computed(() => {
  const ean = props.segment.layer?.product?.ean

  if (!ean) {
return undefined
}

  return getRecommendation(ean)
})

function handleClick() {
  if (props.segment.layer?.product) {
    showModal.value = true
  }
}
</script>

<template>
  <div class="relative flex flex-col items-start pointer-events-auto transition-all duration-200 ease-out rounded overflow-visible"
    :class="[
      segment.layer?.product ? 'cursor-pointer' : 'cursor-default',
      isHovered && segment.layer?.product ? 'scale-105 shadow-md' : 'scale-100 shadow-none'
    ]"
    @mouseenter="isHovered = true" 
    @mouseleave="isHovered = false" 
    @click="handleClick">

    <!-- Indicador visual de performance A, B, C -->
    <PdfAbcBadge :classification="abcClassification" :recommendation="abcRecommendation" :scale="scaleFactor" />

    <!-- Selo BCG (quadrante + ação), no topo do produto -->
    <PdfBcgBadge :data="bcgBadgeData" :scale="scaleFactor" />
    
    <!-- Indicador visual de estoque alvo -->
    <PdfStockIndicator :segment="segment" :shelf-depth="shelfDepth" :scale="scaleFactor" />

    <!-- Selo configurável de indicador (Preço, Custo, Margem, Estoque, Ruptura) -->
    <ProductIndicatorBadge :product="segment.layer?.product" :scale="scaleFactor" />

    <div v-for="i in segment.quantity" :key="i" class="flex flex-col">
      <div class="flex items-center relative transition-shadow duration-200"
        :class="isHovered && segment.layer?.product ? 'shadow-lg' : 'shadow-none'">
        <PdfLayer :segment="segment" :layer="segment.layer" :scale="scaleFactor" :facing-gap="facingGap" />
      </div>
    </div>
  </div>

  <!-- Modal de detalhes: simplificado no share, completo no editor -->
  <ProductDetailModalShare
    v-if="isShare"
    v-model:open="showModal"
    :product="segment.layer?.product"
    :segment-quantity="segment.quantity"
    :layer-quantity="segment.layer?.quantity"
    :shelf-depth="shelfDepth"
  />
  <ProductDetailModal
    v-else
    v-model:open="showModal"
    :product="segment.layer?.product"
    :segment-id="segment.id"
    :segment-quantity="segment.quantity"
    :layer-quantity="segment.layer?.quantity"
    :shelf-depth="shelfDepth"
  />
</template>

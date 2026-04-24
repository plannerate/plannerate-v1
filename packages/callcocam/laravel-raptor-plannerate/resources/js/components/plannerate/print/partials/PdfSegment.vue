<script setup lang="ts">
import { ref, computed } from 'vue'
import ProductDetailModal from './ProductDetailModal.vue'
import PdfAbcBadge from './PdfAbcBadge.vue'
import PdfStockIndicator from './PdfStockIndicator.vue'
import type { Segment } from '@/types/planogram'
import PdfLayer from './PdfLayer.vue'
import { useAbcClassification } from '@/composables/plannerate/v3/useAbcClassification'

interface Props {
  segment: Segment
  scaleFactor: number
  shelfDepth?: number
}

const props = defineProps<Props>()

const isHovered = ref(false)
const showModal = ref(false)

const { getClassification } = useAbcClassification()

// Busca classificação ABC do produto pelo EAN
const abcClassification = computed(() => {
  const ean = props.segment.layer?.product?.ean
  if (!ean) return undefined
  return getClassification(ean)
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
    <PdfAbcBadge :classification="abcClassification" :scale="scaleFactor" />
    
    <!-- Indicador visual de estoque alvo -->
    <PdfStockIndicator :segment="segment" :shelf-depth="shelfDepth" :scale="scaleFactor" />

    <div v-for="i in segment.quantity" :key="i" class="flex flex-col">
      <div class="flex items-center relative transition-shadow duration-200"
        :class="isHovered && segment.layer?.product ? 'shadow-lg' : 'shadow-none'">
        <PdfLayer :segment="segment" :layer="segment.layer" :scale="scaleFactor" />
      </div>
    </div>
  </div>

  <!-- Modal de detalhes -->
  <ProductDetailModal 
    v-model:open="showModal" 
    :product="segment.layer?.product" 
    :segment-quantity="segment.quantity"
    :layer-quantity="segment.layer?.quantity"
    :shelf-depth="shelfDepth"
  />
</template>

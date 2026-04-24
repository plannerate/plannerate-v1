<script setup lang="ts">
import { computed } from 'vue'
import type { Section } from '@/types/planogram'
import { calculateHolePositions } from '@/composables/plannerate/useSectionHoles'

interface Props {
  width: number
  side: 'left' | 'right'
  section: Partial<Section>
  scale: number
  extraHeight?: number
}

const props = defineProps<Props>()

// Valores padrão (mesmos do composable useSectionFields)
const DEFAULT_FIELDS = {
  baseHeight: 20,
  holeHeight: 4,
  holeWidth: 4,
  holeSpacing: 2,
  height: 200,
}

// Altura da base escalada
const baseHeight = computed(() => {
  const base = props.section.base_height ?? DEFAULT_FIELDS.baseHeight
  return base * props.scale
})

// Dimensões dos furos escaladas
const holeHeight = computed(() => {
  const height = props.section.hole_height ?? DEFAULT_FIELDS.holeHeight
  return height * props.scale
})

const holeWidth = computed(() => {
  const width = props.section.hole_width ?? DEFAULT_FIELDS.holeWidth
  return width * props.scale
})

// Calcula posições dos furos usando composable
// Não adiciona extraHeight aqui pois será adicionado no posicionamento
const holePositions = computed(() => calculateHolePositions(props.section));
</script>

<template>
  <div class="absolute top-0 bottom-0 z-[3]"
    :class="side === 'left' ? 'left-0' : 'right-0'"
    :style="{ width: `${width}px` }">
    <div class="absolute w-full bottom-0 border border-slate-600 bg-slate-700"
      :style="{ 
        height: `${(props.section.height ?? DEFAULT_FIELDS.height) * props.scale}px`,
      }">
      <!-- Furos da cremalheira -->
      <div v-for="(position, index) in holePositions" :key="`hole-${index}`" 
        class="absolute left-1/2 -translate-x-1/2 border border-slate-500 bg-slate-400"
        :style="{
          width: `${holeWidth}px`,
          height: `${holeHeight}px`,
          top: `${position * props.scale}px`,
        }" />

      <!-- Base da cremalheira -->
      <div class="absolute bottom-0 left-0 w-full border-t border-slate-600 bg-slate-700"
        :style="{
          height: `${baseHeight}px`,
        }" />
    </div>
  </div>
</template>

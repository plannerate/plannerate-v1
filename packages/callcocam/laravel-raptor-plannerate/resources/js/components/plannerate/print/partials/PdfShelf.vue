<script setup lang="ts">
import { computed } from 'vue'
import { DEFAULT_SECTION_FIELDS } from '@/composables/plannerate/fields/useSectionFields'
import { calculateHolePositions } from '@/composables/plannerate/geometry/useSectionHoles'
import { useShelfAreaCalculation } from '@/composables/plannerate/geometry/useShelfAreaCalculation'
import type { Section, Shelf } from '@/types/planogram'
import PdfSegment from './PdfSegment.vue'

interface Props {
  shelf: Shelf
  section: Section
  sectionWidth: number
  scaleFactor: number
  cremalheiraWidth: number
  alignment: string
  extraHeight?: number
  previousShelf?: Shelf
  isShare?: boolean
}

const props = defineProps<Props>()

const { calculateShelfArea } = useShelfAreaCalculation()

const shelfArea = computed(() => {
  return calculateShelfArea({
    shelf: props.shelf,
    previousShelf: props.previousShelf,
    scale: props.scaleFactor,
  })
})

const shelfHeight = computed(() => props.shelf.shelf_height * props.scaleFactor)

const shelfAreaStyle = computed(() => ({
  top: `${(shelfArea.value.areaStartCm + (props.extraHeight ?? 0)) * props.scaleFactor}px`,
  width: `${props.sectionWidth}px`,
  height: `${shelfArea.value.areaHeightCm * props.scaleFactor}px`,
  left: `${props.cremalheiraWidth}px`,
  right: `-${props.cremalheiraWidth}px`,
}))

const shelfBasePosition = computed(() => {
  const { areaStartCm } = calculateShelfArea({
    shelf: props.shelf,
    previousShelf: props.previousShelf,
    scale: props.scaleFactor,
  })

  const holePositions = calculateHolePositions(props.section)

  if (holePositions.length === 0) {
    const offsetFromAreaStart = props.shelf.shelf_position - areaStartCm

    return offsetFromAreaStart * props.scaleFactor
  }

  const holeHeight = props.section.hole_height ?? DEFAULT_SECTION_FIELDS.holeHeight
  const shelfHeightCm = props.shelf.shelf_height
  const shelfPositionCm = props.shelf.shelf_position

  let closestHoleIdx = 0
  let minDistance = Math.abs(shelfPositionCm - holePositions[0])

  for (let i = 0; i < holePositions.length; i++) {
    const distance = Math.abs(shelfPositionCm - holePositions[i])

    if (distance < minDistance) {
      minDistance = distance
      closestHoleIdx = i
    }
  }

  const closestHolePos = holePositions[closestHoleIdx]
  const centeredPosition = closestHolePos + (holeHeight - shelfHeightCm) / 2
  const offsetFromAreaStart = centeredPosition - areaStartCm

  return offsetFromAreaStart * props.scaleFactor
})

// Detecta se é tipo hook (gancheira) - produtos pendurados
const isHookType = computed(() => props.shelf.product_type === 'hook')

const segments = computed(() => props.shelf.segments?.filter(segment => !segment.deleted_at) || [])

const shelfDisplayNumber = computed(() => {
  if (!props.section?.shelves) {
return 1
}

  const sorted = [...props.section.shelves]
    .filter((shelf) => !shelf.deleted_at)
    .sort((a, b) => (b.shelf_position || 0) - (a.shelf_position || 0))

  return Math.max(1, sorted.findIndex((shelf) => shelf.id === props.shelf.id) + 1)
})

/**
 * Gap uniforme (px) usado no modo "justificar" — mesma lógica do editor
 * (useShelfLayout). Distribui o espaço livre da prateleira igualmente entre
 * TODAS as frentes de produto, ignorando o agrupamento por segmento, de modo
 * que cada produto e ambas as bordas fiquem com o mesmo espaçamento.
 *
 * Retorna null quando não está justificando, sem largura conhecida ou em
 * overflow — nesses casos cai no fallback `justify-evenly`.
 */
const justifyGap = computed<number | null>(() => {
  const align = props.alignment || 'justify'

  if (align !== 'justify' || !props.sectionWidth) {
    return null
  }

  let totalFacings = 0
  let totalProductsWidthPx = 0

  for (const segment of segments.value) {
    const layer = segment.layer

    if (!layer) {
      continue
    }

    const facings = Math.max(1, Math.trunc(Number(layer.quantity ?? 1)) || 1)
    const facingWidthPx = (Number(layer.product?.width) || 0) * props.scaleFactor

    totalFacings += facings
    totalProductsWidthPx += facings * facingWidthPx
  }

  if (totalFacings === 0) {
    return null
  }

  const freeSpacePx = props.sectionWidth - totalProductsWidthPx

  if (freeSpacePx <= 0) {
    return null
  }

  return freeSpacePx / (totalFacings + 1)
})

const alignmentClass = computed(() => {
  const align = props.alignment || 'justify'
  const map: Record<string, string> = {
    left: 'justify-start',
    right: 'justify-end',
    center: 'justify-center',
    justify: 'justify-evenly',
    default: 'justify-evenly',
  }

  // No modo justificar com gap calculado, o espaçamento é controlado
  // manualmente (padding-left + column-gap), então alinhamos ao início.
  if (align === 'justify' && justifyGap.value !== null) {
    return 'justify-start'
  }

  return map[align] || map.justify
})

/**
 * Estilo do container dos segmentos: posição vertical (top para hook, bottom
 * para prateleira) acrescida do padding-left + column-gap uniformes quando o
 * gap do modo justificar está ativo.
 */
const segmentsContainerStyle = computed(() => {
  const style: Record<string, string> = isHookType.value
    ? { top: `${shelfBasePosition.value + shelfHeight.value}px` }
    : { bottom: `${shelfArea.value.areaHeightCm * props.scaleFactor - shelfBasePosition.value}px` }

  if (justifyGap.value !== null) {
    style.paddingLeft = `${justifyGap.value}px`
    style.columnGap = `${justifyGap.value}px`
  }

  return style
})
</script>

<template>
  <div class="absolute z-[50]" data-shelf-area="true" :style="shelfAreaStyle">
    <!-- Container dos segmentos -->
    <div class="absolute right-0 left-0 flex z-[1] pointer-events-none"
      :class="[alignmentClass, isHookType ? 'items-start' : 'items-end']"
      :style="segmentsContainerStyle">
      <PdfSegment
        v-for="segment in segments"
        :key="segment.id"
        :segment="segment"
        :scale-factor="scaleFactor"
        :shelf-depth="shelf.shelf_depth"
        :facing-gap="justifyGap ?? undefined"
        :is-share="isShare"
      />
    </div>

    <!-- Barra da prateleira -->
    <div class="absolute right-0 left-0 z-[1] border-t-2 border-slate-700 bg-slate-800/95 text-slate-300 flex items-center justify-center"
      :style="{
        top: `${shelfBasePosition}px`,
        height: `${shelfHeight}px`,
      }">
      <span class="font-medium" :style="{ fontSize: `${3 * scaleFactor}px` }">
        Prat #{{ shelfDisplayNumber }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import PdfSegment from './PdfSegment.vue'
import type { Section, Shelf } from '@/types/planogram'
import { useShelfAreaCalculation } from '@/composables/plannerate/useShelfAreaCalculation'
import { calculateHolePositions } from '@/composables/plannerate/useSectionHoles'
import { DEFAULT_SECTION_FIELDS } from '@/composables/plannerate/useSectionFields'
import { computed } from 'vue'

interface Props {
  shelf: Shelf
  section: Section
  sectionWidth: number
  scaleFactor: number
  cremalheiraWidth: number
  alignment: string
  extraHeight?: number
  previousShelf?: Shelf
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
  if (!props.section?.shelves) return 1

  const sorted = [...props.section.shelves]
    .filter((shelf) => !shelf.deleted_at)
    .sort((a, b) => (b.shelf_position || 0) - (a.shelf_position || 0))

  return Math.max(1, sorted.findIndex((shelf) => shelf.id === props.shelf.id) + 1)
})

const alignmentClass = computed(() => {
  const align = props.alignment || 'justify'
  const map: Record<string, string> = {
    left: 'justify-start',
    right: 'justify-end',
    center: 'justify-center',
    justify: 'justify-between',
    default: 'justify-between',
  }

  return map[align] || map.justify
})
</script>

<template>
  <div class="absolute z-[50]" data-shelf-area="true" :style="shelfAreaStyle">
    <!-- Container dos segmentos -->
    <div class="absolute right-0 left-0 flex gap-0 z-[1] pointer-events-none"
      :class="[alignmentClass, isHookType ? 'items-start' : 'items-end']"
      :style="
        isHookType
          ? {
              top: `${shelfBasePosition + shelfHeight}px`,
            }
          : {
              bottom: `${shelfHeight}px`,
            }
      ">
      <PdfSegment
        v-for="segment in segments"
        :key="segment.id"
        :segment="segment"
        :scale-factor="scaleFactor"
        :shelf-depth="shelf.shelf_depth"
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

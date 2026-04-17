<template>
  <div 
    ref="mapContainer" 
    class="relative overflow-hidden" 
    :class="{
      'cursor-crosshair': currentTool === 'draw',
      'cursor-grab': currentTool === 'pan' && !isPanning,
      'cursor-grabbing': currentTool === 'pan' && isPanning,
      'cursor-default': currentTool === 'select'
    }" 
    :style="{ height: containerHeight + 'px' }" 
    @mousedown="handleMouseDown" 
    @mousemove="handleMouseMove"
    @mouseup="handleMouseUp" 
    @mouseleave="handleMouseUp" 
    @wheel.prevent="handleWheel"
  >
    <div 
      ref="mapContent" 
      class="absolute origin-top-left transition-transform duration-100" 
      :style="{
        transform: `translate(${panX}px, ${panY}px) scale(${zoom})`,
      }"
    >
      <img 
        ref="mapImageEl" 
        :src="mapImage" 
        class="max-w-none" 
        @load="handleImageLoad" 
      />

      <!-- SVG overlay for regions -->
      <svg v-if="imageLoaded" class="absolute top-0 left-0" :width="imageWidth" :height="imageHeight">
        <!-- Existing regions -->
        <g v-for="region in regions" :key="region.id">
          <!-- Circle shape -->
          <ellipse 
            v-if="region.shape === 'circle'" 
            :cx="region.x + region.width / 2"
            :cy="region.y + region.height / 2" 
            :rx="region.width / 2" 
            :ry="region.height / 2"
            :fill="region.color || 'rgba(59, 130, 246, 0.3)'"
            :stroke="selectedRegionId === region.id ? '#22c55e' : (region.color?.replace('0.3', '1') || '#3b82f6')"
            :stroke-width="selectedRegionId === region.id ? 5 : 3"
            :class="selectedRegionId === region.id ? 'cursor-move' : 'cursor-pointer'"
            :style="selectedRegionId === region.id ? 'filter: drop-shadow(0 0 4px rgba(34, 197, 94, 0.8))' : ''"
            @click.stop="$emit('select-region', region)" 
            @dblclick.stop="$emit('edit-region', region)" 
          />
          <!-- Rectangle shape (default) -->
          <rect 
            v-else 
            :x="region.x" 
            :y="region.y" 
            :width="region.width" 
            :height="region.height"
            :fill="region.color || 'rgba(59, 130, 246, 0.3)'"
            :stroke="selectedRegionId === region.id ? '#22c55e' : (region.color?.replace('0.3', '1') || '#3b82f6')"
            :stroke-width="selectedRegionId === region.id ? 5 : 3"
            :class="selectedRegionId === region.id ? 'cursor-move' : 'cursor-pointer'"
            :style="selectedRegionId === region.id ? 'filter: drop-shadow(0 0 4px rgba(34, 197, 94, 0.8))' : ''"
            @click.stop="$emit('select-region', region)" 
            @dblclick.stop="$emit('edit-region', region)" 
          />
          <!-- Region label -->
          <text 
            :x="region.x + region.width / 2" 
            :y="region.y + region.height / 2" 
            text-anchor="middle"
            dominant-baseline="middle"
            class="text-xs font-medium fill-foreground pointer-events-none select-none"
            :style="{ fontSize: `${Math.max(10, Math.min(14, region.width / 8))}px` }"
          >
            {{ region.label || region.gondola?.name || 'Área ' + (regions.indexOf(region) + 1) }}
          </text>
        </g>

        <!-- Drawing preview -->
        <ellipse 
          v-if="isDrawing && drawStart && drawEnd && drawShape === 'circle'"
          :cx="(Math.min(drawStart.x, drawEnd.x) + Math.abs(drawEnd.x - drawStart.x) / 2)"
          :cy="(Math.min(drawStart.y, drawEnd.y) + Math.abs(drawEnd.y - drawStart.y) / 2)"
          :rx="Math.abs(drawEnd.x - drawStart.x) / 2" 
          :ry="Math.abs(drawEnd.y - drawStart.y) / 2"
          fill="rgba(34, 197, 94, 0.3)" 
          stroke="#22c55e" 
          stroke-width="2" 
          stroke-dasharray="5,5" 
        />
        <rect 
          v-else-if="isDrawing && drawStart && drawEnd" 
          :x="Math.min(drawStart.x, drawEnd.x)"
          :y="Math.min(drawStart.y, drawEnd.y)" 
          :width="Math.abs(drawEnd.x - drawStart.x)"
          :height="Math.abs(drawEnd.y - drawStart.y)" 
          fill="rgba(34, 197, 94, 0.3)" 
          stroke="#22c55e"
          stroke-width="2" 
          stroke-dasharray="5,5" 
        />

        <!-- Resize handles for selected region -->
        <g v-if="selectedRegion && currentTool === 'select'">
          <rect 
            v-for="handle in resizeHandles" 
            :key="handle.position" 
            :x="handle.x - 5" 
            :y="handle.y - 5"
            width="10" 
            height="10" 
            fill="#3b82f6" 
            stroke="white" 
            stroke-width="1" 
            class="cursor-nwse-resize"
            @mousedown.stop="startResize(handle.position, $event)" 
          />
        </g>
      </svg>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

interface Region {
  id: string
  x: number
  y: number
  width: number
  height: number
  shape?: 'rectangle' | 'circle'
  label?: string
  type?: string
  color?: string
  gondola_id?: string | null
  gondola?: { id: string; name: string } | null
}

interface Props {
  mapImage: string
  regions: Region[]
  selectedRegionId: string | null
  currentTool: 'select' | 'draw' | 'pan'
  drawShape: 'rectangle' | 'circle'
  zoom: number
  panX: number
  panY: number
  containerHeight?: number
}

const props = withDefaults(defineProps<Props>(), {
  containerHeight: 400,
})

const emit = defineEmits<{
  (e: 'select-region', region: Region): void
  (e: 'edit-region', region: Region): void
  (e: 'draw-complete', region: Omit<Region, 'id'>): void
  (e: 'region-move', regionId: string, x: number, y: number): void
  (e: 'region-resize', regionId: string, x: number, y: number, width: number, height: number): void
  (e: 'update:zoom', value: number): void
  (e: 'update:panX', value: number): void
  (e: 'update:panY', value: number): void
  (e: 'image-loaded', width: number, height: number): void
  (e: 'deselect'): void
}>()

// Refs
const mapContainer = ref<HTMLDivElement | null>(null)
const mapImageEl = ref<HTMLImageElement | null>(null)

// State
const imageLoaded = ref(false)
const imageWidth = ref(0)
const imageHeight = ref(0)

// Interaction state
const isDrawing = ref(false)
const isPanning = ref(false)
const isResizing = ref(false)
const isDragging = ref(false)
const resizeHandle = ref<string | null>(null)
const drawStart = ref<{ x: number; y: number } | null>(null)
const drawEnd = ref<{ x: number; y: number } | null>(null)
const panStart = ref<{ x: number; y: number } | null>(null)
const dragStart = ref<{ x: number; y: number; regionX: number; regionY: number } | null>(null)

// Computed
const selectedRegion = computed(() => {
  if (!props.selectedRegionId) return null
  return props.regions.find(r => r.id === props.selectedRegionId) || null
})

const resizeHandles = computed(() => {
  if (!selectedRegion.value) return []
  const r = selectedRegion.value
  return [
    { position: 'nw', x: r.x, y: r.y },
    { position: 'ne', x: r.x + r.width, y: r.y },
    { position: 'sw', x: r.x, y: r.y + r.height },
    { position: 'se', x: r.x + r.width, y: r.y + r.height },
  ]
})

// Methods
const handleImageLoad = () => {
  if (mapImageEl.value) {
    imageWidth.value = mapImageEl.value.naturalWidth
    imageHeight.value = mapImageEl.value.naturalHeight
    imageLoaded.value = true
    emit('image-loaded', imageWidth.value, imageHeight.value)
  }
}

const getMousePosition = (event: MouseEvent) => {
  const container = mapContainer.value
  if (!container) return { x: 0, y: 0 }

  const rect = container.getBoundingClientRect()
  const x = (event.clientX - rect.left - props.panX) / props.zoom
  const y = (event.clientY - rect.top - props.panY) / props.zoom

  return { x, y }
}

const handleMouseDown = (event: MouseEvent) => {
  if (event.button !== 0) return

  const pos = getMousePosition(event)

  if (props.currentTool === 'draw') {
    isDrawing.value = true
    drawStart.value = pos
    drawEnd.value = pos
    emit('deselect')
  } else if (props.currentTool === 'pan') {
    isPanning.value = true
    panStart.value = { x: event.clientX - props.panX, y: event.clientY - props.panY }
  } else if (props.currentTool === 'select') {
    const clickedRegion = props.regions.find(r => {
      if (r.shape === 'circle') {
        const cx = r.x + r.width / 2
        const cy = r.y + r.height / 2
        const rx = r.width / 2
        const ry = r.height / 2
        return Math.pow((pos.x - cx) / rx, 2) + Math.pow((pos.y - cy) / ry, 2) <= 1
      }
      return pos.x >= r.x && pos.x <= r.x + r.width &&
        pos.y >= r.y && pos.y <= r.y + r.height
    })

    if (clickedRegion) {
      if (props.selectedRegionId === clickedRegion.id) {
        isDragging.value = true
        dragStart.value = {
          x: pos.x,
          y: pos.y,
          regionX: clickedRegion.x,
          regionY: clickedRegion.y
        }
      } else {
        emit('select-region', clickedRegion)
      }
    } else {
      emit('deselect')
    }
  }
}

const handleMouseMove = (event: MouseEvent) => {
  if (isDrawing.value && props.currentTool === 'draw') {
    drawEnd.value = getMousePosition(event)
  } else if (isPanning.value && props.currentTool === 'pan' && panStart.value) {
    emit('update:panX', event.clientX - panStart.value.x)
    emit('update:panY', event.clientY - panStart.value.y)
  } else if (isDragging.value && selectedRegion.value && dragStart.value) {
    const pos = getMousePosition(event)
    const deltaX = pos.x - dragStart.value.x
    const deltaY = pos.y - dragStart.value.y

    let newX = Math.max(0, dragStart.value.regionX + deltaX)
    let newY = Math.max(0, dragStart.value.regionY + deltaY)

    if (imageWidth.value > 0) {
      newX = Math.min(newX, imageWidth.value - selectedRegion.value.width)
    }
    if (imageHeight.value > 0) {
      newY = Math.min(newY, imageHeight.value - selectedRegion.value.height)
    }

    emit('region-move', selectedRegion.value.id, newX, newY)
  } else if (isResizing.value && selectedRegion.value && resizeHandle.value) {
    const pos = getMousePosition(event)
    const r = selectedRegion.value
    let newX = r.x, newY = r.y, newWidth = r.width, newHeight = r.height

    switch (resizeHandle.value) {
      case 'se':
        newWidth = Math.max(20, pos.x - r.x)
        newHeight = Math.max(20, pos.y - r.y)
        break
      case 'sw':
        newWidth = Math.max(20, r.x + r.width - pos.x)
        newX = r.x + r.width - newWidth
        newHeight = Math.max(20, pos.y - r.y)
        break
      case 'ne':
        newWidth = Math.max(20, pos.x - r.x)
        newHeight = Math.max(20, r.y + r.height - pos.y)
        newY = r.y + r.height - newHeight
        break
      case 'nw':
        newWidth = Math.max(20, r.x + r.width - pos.x)
        newHeight = Math.max(20, r.y + r.height - pos.y)
        newX = r.x + r.width - newWidth
        newY = r.y + r.height - newHeight
        break
    }
    emit('region-resize', r.id, newX, newY, newWidth, newHeight)
  }
}

const handleMouseUp = () => {
  if (isDrawing.value && drawStart.value && drawEnd.value) {
    const width = Math.abs(drawEnd.value.x - drawStart.value.x)
    const height = Math.abs(drawEnd.value.y - drawStart.value.y)

    if (width > 20 && height > 20) {
      emit('draw-complete', {
        x: Math.min(drawStart.value.x, drawEnd.value.x),
        y: Math.min(drawStart.value.y, drawEnd.value.y),
        width,
        height,
        shape: props.drawShape,
        type: 'gondola',
        color: 'rgba(59, 130, 246, 0.3)',
      })
    }
  }

  isDrawing.value = false
  isPanning.value = false
  isResizing.value = false
  isDragging.value = false
  drawStart.value = null
  drawEnd.value = null
  panStart.value = null
  resizeHandle.value = null
  dragStart.value = null
}

const handleWheel = (event: WheelEvent) => {
  const delta = event.deltaY > 0 ? -0.1 : 0.1
  const newZoom = Math.max(0.1, Math.min(3, props.zoom + delta))

  const container = mapContainer.value
  if (container) {
    const rect = container.getBoundingClientRect()
    const mouseX = event.clientX - rect.left
    const mouseY = event.clientY - rect.top

    const scaleChange = newZoom / props.zoom
    emit('update:panX', mouseX - (mouseX - props.panX) * scaleChange)
    emit('update:panY', mouseY - (mouseY - props.panY) * scaleChange)
  }

  emit('update:zoom', newZoom)
}

const startResize = (position: string, event: MouseEvent) => {
  event.preventDefault()
  isResizing.value = true
  resizeHandle.value = position
}

// Expose methods for parent
defineExpose({
  fitToContainer: () => {
    if (mapContainer.value && imageWidth.value) {
      const containerWidth = mapContainer.value.clientWidth
      const scale = Math.min(containerWidth / imageWidth.value, props.containerHeight / imageHeight.value)
      emit('update:zoom', Math.min(scale, 1))
    }
  }
})
</script>

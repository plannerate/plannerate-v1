<!--
 * FormFieldMapSelector - Select a region from store map for gondola linking
 *
 * Features:
 * - Display store map with regions
 * - Click to select/link a region to gondola
 * - Show selected region info
 * - Read-only map view (no editing)
 -->
<template>
  <Field orientation="vertical" :data-invalid="hasError" class="gap-y-1">
    <div class="flex items-center justify-between w-full">
      <FieldLabel v-if="column.label" :for="column.name">
        {{ column.label }}
        <span v-if="column.required" class="text-destructive">*</span>
      </FieldLabel>
    </div>

    <!-- No store selected -->
    <div v-if="!storeId" class="border rounded-lg p-4 text-center text-muted-foreground">
      <MapPin class="h-8 w-8 mx-auto mb-2 opacity-50" />
      <p class="text-sm">Selecione uma loja para visualizar o mapa</p>
    </div>

    <!-- Store has no map -->
    <div v-else-if="!mapImageUrl && !loading" class="border rounded-lg p-4 text-center text-muted-foreground">
      <Map class="h-8 w-8 mx-auto mb-2 opacity-50" />
      <p class="text-sm">A loja selecionada não possui mapa configurado</p>
    </div>

    <!-- Loading -->
    <div v-else-if="loading" class="border rounded-lg p-8 text-center">
      <Loader2 class="h-8 w-8 mx-auto animate-spin text-muted-foreground" />
      <p class="text-sm text-muted-foreground mt-2">Carregando mapa...</p>
    </div>

    <!-- Map Selector -->
    <div v-else class="space-y-3">
      <!-- Selected region info -->
      <div v-if="selectedRegion" class="flex items-center gap-2 p-2 bg-muted rounded-md">
        <div 
          class="w-4 h-4 rounded" 
          :style="{ backgroundColor: selectedRegion.color || 'rgba(59, 130, 246, 0.5)' }"
        />
        <span class="text-sm font-medium">{{ selectedRegion.label || 'Área sem nome' }}</span>
        <span v-if="selectedRegion.type" class="text-xs text-muted-foreground">({{ regionTypeLabel(selectedRegion.type) }})</span>
        <Button type="button" variant="ghost" size="sm" class="ml-auto h-6 px-2" @click="clearSelection">
          <X class="h-3 w-3" />
        </Button>
      </div>

      <!-- Map preview with regions -->
      <div 
        ref="mapContainer"
        class="relative border rounded-lg overflow-hidden bg-muted/30"
        :style="{ height: containerHeight + 'px' }"
      >
        <!-- Toolbar -->
        <div class="absolute top-2 left-2 z-10 flex items-center gap-1 bg-background/90 backdrop-blur rounded-md p-1 shadow-sm">
          <Button type="button" variant="ghost" size="icon" class="h-7 w-7" @click="zoomOut" title="Diminuir zoom">
            <Minus class="h-3 w-3" />
          </Button>
          <span class="text-xs font-medium min-w-[40px] text-center">{{ Math.round(zoom * 100) }}%</span>
          <Button type="button" variant="ghost" size="icon" class="h-7 w-7" @click="zoomIn" title="Aumentar zoom">
            <Plus class="h-3 w-3" />
          </Button>
          <Separator orientation="vertical" class="h-5 mx-1" />
          <Button type="button" variant="ghost" size="icon" class="h-7 w-7" @click="resetView" title="Resetar visualização">
            <RotateCcw class="h-3 w-3" />
          </Button>
        </div>

        <!-- Hint -->
        <div class="absolute top-2 right-2 z-10 bg-background/90 backdrop-blur rounded-md px-2 py-1 shadow-sm">
          <span class="text-xs text-muted-foreground">Clique em uma área para selecionar</span>
        </div>

        <!-- Map content -->
        <div
          class="absolute inset-0 overflow-hidden cursor-crosshair"
          @wheel.prevent="handleWheel"
        >
          <div
            :style="{
              transform: `translate(${pan.x}px, ${pan.y}px) scale(${zoom})`,
              transformOrigin: '0 0',
              position: 'relative',
              width: imageWidth + 'px',
              height: imageHeight + 'px',
            }"
          >
            <!-- Map image -->
            <img
              ref="mapImageEl"
              :src="mapImageUrl"
              class="block max-w-none"
              @load="handleImageLoad"
            />

            <!-- Regions SVG overlay -->
            <svg
              v-if="imageLoaded"
              class="absolute inset-0 pointer-events-none"
              :width="imageWidth"
              :height="imageHeight"
              :viewBox="`0 0 ${imageWidth} ${imageHeight}`"
            >
              <g v-for="region in regions" :key="region.id">
                <!-- Rectangle region -->
                <rect
                  v-if="region.shape !== 'circle'"
                  :x="region.x"
                  :y="region.y"
                  :width="region.width"
                  :height="region.height"
                  :fill="region.color || 'rgba(59, 130, 246, 0.3)'"
                  :stroke="isSelected(region) ? '#2563eb' : (region.color?.replace('0.3', '0.8') || 'rgba(59, 130, 246, 0.8)')"
                  :stroke-width="isSelected(region) ? 3 : 2"
                  class="pointer-events-auto cursor-pointer transition-all"
                  :class="{ 'opacity-50': isLinked(region) && !isSelected(region) }"
                  @click="selectRegion(region)"
                />
                <!-- Circle region -->
                <ellipse
                  v-else
                  :cx="region.x + region.width / 2"
                  :cy="region.y + region.height / 2"
                  :rx="region.width / 2"
                  :ry="region.height / 2"
                  :fill="region.color || 'rgba(59, 130, 246, 0.3)'"
                  :stroke="isSelected(region) ? '#2563eb' : (region.color?.replace('0.3', '0.8') || 'rgba(59, 130, 246, 0.8)')"
                  :stroke-width="isSelected(region) ? 3 : 2"
                  class="pointer-events-auto cursor-pointer transition-all"
                  :class="{ 'opacity-50': isLinked(region) && !isSelected(region) }"
                  @click="selectRegion(region)"
                />
                <!-- Label -->
                <text
                  :x="region.x + region.width / 2"
                  :y="region.y + region.height / 2"
                  text-anchor="middle"
                  dominant-baseline="middle"
                  class="text-xs font-medium fill-foreground pointer-events-none select-none"
                  style="font-size: 12px;"
                >
                  {{ region.label || '' }}
                </text>
                <!-- Linked indicator -->
                <circle
                  v-if="isLinked(region) && !isSelected(region)"
                  :cx="region.x + region.width - 8"
                  :cy="region.y + 8"
                  r="6"
                  fill="#22c55e"
                  stroke="white"
                  stroke-width="1"
                  class="pointer-events-none"
                />
              </g>
            </svg>
          </div>
        </div>
      </div>

      <!-- Legend -->
      <div class="flex items-center gap-4 text-xs text-muted-foreground">
        <div class="flex items-center gap-1">
          <div class="w-3 h-3 rounded-full bg-green-500" />
          <span>Vinculada a outra gôndola</span>
        </div>
        <div class="flex items-center gap-1">
          <div class="w-3 h-3 rounded border-2 border-blue-600" />
          <span>Selecionada</span>
        </div>
      </div>
    </div>

    <FieldDescription v-if="column.helperText">
      {{ column.helperText }}
    </FieldDescription>

    <FieldError :errors="errorArray" />
  </Field>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Field, FieldLabel, FieldDescription, FieldError } from '@/components/ui/field'
import {
  Map,
  MapPin,
  Plus,
  Minus,
  RotateCcw,
  X,
  Loader2,
} from 'lucide-vue-next'

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
}

interface FormColumn {
  name: string
  label?: string
  helperText?: string
  required?: boolean
  storeId?: string
  mapData?: {
    image_url?: string
    regions?: Region[]
  }
}

interface Props {
  column: FormColumn
  modelValue?: string | null
  error?: string | string[]
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: null,
  error: undefined,
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: string | null): void
}>()

// Refs
const mapContainer = ref<HTMLDivElement | null>(null)
const mapImageEl = ref<HTMLImageElement | null>(null)

// State
const loading = ref(false)
const mapImageUrl = ref<string | null>(null)
const regions = ref<Region[]>([])
const imageLoaded = ref(false)
const imageWidth = ref(0)
const imageHeight = ref(0)
const containerHeight = ref(300)

// View state
const zoom = ref(1)
const pan = ref({ x: 0, y: 0 })

// Computed
const storeId = computed(() => props.column.storeId)

const selectedRegion = computed(() => {
  if (!props.modelValue) return null
  return regions.value.find(r => r.id === props.modelValue) || null
})

const hasError = computed(() => {
  return props.error && (Array.isArray(props.error) ? props.error.length > 0 : true)
})

const errorArray = computed(() => {
  if (!props.error) return []
  return Array.isArray(props.error) ? props.error : [props.error]
})

// Methods
const isSelected = (region: Region) => {
  return props.modelValue === region.id
}

const isLinked = (region: Region) => {
  return region.gondola_id && region.gondola_id !== props.modelValue
}

const selectRegion = (region: Region) => {
  if (isSelected(region)) {
    // Deselect if clicking on already selected
    emit('update:modelValue', null)
  } else {
    emit('update:modelValue', region.id)
  }
}

const clearSelection = () => {
  emit('update:modelValue', null)
}

const regionTypeLabel = (type: string) => {
  const labels: Record<string, string> = {
    gondola: 'Gôndola',
    island: 'Ilha',
    checkout: 'Checkout',
    entrance: 'Entrada',
    exit: 'Saída',
    storage: 'Estoque',
    other: 'Outro',
  }
  return labels[type] || type
}

// Zoom controls
const zoomIn = () => {
  zoom.value = Math.min(zoom.value * 1.2, 3)
}

const zoomOut = () => {
  zoom.value = Math.max(zoom.value / 1.2, 0.2)
}

const resetView = () => {
  zoom.value = 1
  pan.value = { x: 0, y: 0 }
  fitToContainer()
}

const handleWheel = (event: WheelEvent) => {
  const delta = event.deltaY > 0 ? 0.9 : 1.1
  zoom.value = Math.max(0.2, Math.min(3, zoom.value * delta))
}

const fitToContainer = () => {
  if (!mapContainer.value || !imageWidth.value) return
  const containerWidth = mapContainer.value.clientWidth
  const scale = Math.min(containerWidth / imageWidth.value, containerHeight.value / imageHeight.value)
  zoom.value = Math.min(scale, 1)
}

const handleImageLoad = () => {
  if (mapImageEl.value) {
    imageWidth.value = mapImageEl.value.naturalWidth
    imageHeight.value = mapImageEl.value.naturalHeight
    imageLoaded.value = true
    nextTick(fitToContainer)
  }
}

// Load map data from column props
const loadMapData = () => {
  if (props.column.mapData) {
    mapImageUrl.value = props.column.mapData.image_url || null
    regions.value = props.column.mapData.regions || []
  } else {
    mapImageUrl.value = null
    regions.value = []
  }
}

// Watch for store/map changes
watch(() => props.column.mapData, () => {
  loadMapData()
}, { deep: true, immediate: true })

watch(() => props.column.storeId, () => {
  // Reset when store changes
  imageLoaded.value = false
  loadMapData()
})

onMounted(() => {
  loadMapData()
})
</script>

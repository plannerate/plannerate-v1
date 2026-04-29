<script setup lang="ts">
import {
  Map,
  MapPin,
  Plus,
  Minus,
  RotateCcw,
  X,
  Check,
} from 'lucide-vue-next'
import { computed, nextTick, ref, watch } from 'vue'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Separator } from '@/components/ui/separator'

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

interface Props {
  open: boolean
  storeId?: string | null
  storeName?: string
  mapImageUrl?: string | null
  mapRegions?: Region[]
  currentRegionId?: string | null
  gondolaId?: string
  gondolaName?: string
}

const props = withDefaults(defineProps<Props>(), {
  open: false,
  storeId: null,
  storeName: '',
  mapImageUrl: null,
  mapRegions: () => [],
  currentRegionId: null,
  gondolaId: '',
  gondolaName: '',
})

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
  (e: 'select', regionId: string | null): void
}>()

// Refs
const mapContainer = ref<HTMLDivElement | null>(null)
const mapImageEl = ref<HTMLImageElement | null>(null)

// State
const selectedRegionId = ref<string | null>(props.currentRegionId)
const imageLoaded = ref(false)
const imageWidth = ref(0)
const imageHeight = ref(0)
const containerHeight = ref(400)

// View state
const zoom = ref(1)
const pan = ref({ x: 0, y: 0 })

// Computed
const regions = computed(() => props.mapRegions || [])

const selectedRegion = computed(() => {
  if (!selectedRegionId.value) {
return null
}

  return regions.value.find(r => r.id === selectedRegionId.value) || null
})

const hasMap = computed(() => !!props.mapImageUrl)

// Verifica se houve mudança na seleção (libera botão Confirmar)
const hasChanged = computed(() => {
  return selectedRegionId.value !== props.currentRegionId
})

// Verifica se pode desvincular (tem região atual vinculada E ainda não desvinculou)
const canUnlink = computed(() => {
  return props.currentRegionId && selectedRegionId.value !== null
})

// Methods
const isSelected = (region: Region) => {
  return selectedRegionId.value === region.id
}

const isLinkedToOther = (region: Region) => {
  // Vinculada a outra gôndola (não a atual)
  return region.gondola_id && region.gondola_id !== props.gondolaId
}

const selectRegion = (region: Region) => {
  if (isSelected(region)) {
    selectedRegionId.value = null
  } else {
    selectedRegionId.value = region.id
  }
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
  if (!mapContainer.value || !imageWidth.value) {
return
}

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

const handleConfirm = () => {
  emit('select', selectedRegionId.value)
  emit('update:open', false)
}

const handleCancel = () => {
  selectedRegionId.value = props.currentRegionId
  emit('update:open', false)
}

// Watch for prop changes
watch(() => props.currentRegionId, (newVal: string | null) => {
  selectedRegionId.value = newVal
})

watch(() => props.open, (newVal: boolean) => {
  if (newVal) {
    selectedRegionId.value = props.currentRegionId
    imageLoaded.value = false
  }
})
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)" class="relative">
    <DialogContent class="w-full md:max-w-4xl max-h-[90vh] flex flex-col">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          <MapPin class="h-5 w-5" />
          Vincular Gôndola ao Mapa
        </DialogTitle>
        <DialogDescription>
          Selecione a posição de <strong>{{ gondolaName || 'esta gôndola' }}</strong> no mapa da loja
          <strong v-if="storeName">{{ storeName }}</strong>
        </DialogDescription>
      </DialogHeader>

      <!-- No map available -->
      <div v-if="!hasMap" class="flex-1 flex items-center justify-center p-8">
        <div class="text-center text-muted-foreground">
          <Map class="h-12 w-12 mx-auto mb-3 opacity-50" />
          <p class="text-sm">A loja não possui um mapa configurado</p>
          <p class="text-xs mt-1">Configure o mapa na edição da loja</p>
        </div>
      </div>

      <!-- Map viewer -->
      <div v-else class="flex-1 flex flex-col gap-3 min-h-0">
        <!-- Selected region info -->
        <div v-if="selectedRegion" class="flex items-center gap-2 p-2 bg-muted rounded-md">
          <div class="w-4 h-4 rounded"
            :style="{ backgroundColor: selectedRegion.color || 'rgba(59, 130, 246, 0.5)' }" />
          <span class="text-sm font-medium">{{ selectedRegion.label || 'Área sem nome' }}</span>
          <span v-if="selectedRegion.type" class="text-xs text-muted-foreground">
            ({{ regionTypeLabel(selectedRegion.type) }})
          </span>
          <Check class="h-4 w-4 ml-auto text-green-500" />
        </div>
        <!-- Map container -->
        <div ref="mapContainer" class="relative flex-1 border rounded-lg overflow-hidden bg-muted/30 min-h-[60vh]">
          <!-- Toolbar -->
          <div
            class="absolute top-2 left-2 z-10 flex items-center gap-1 bg-background/90 backdrop-blur rounded-md p-1 shadow-sm">
            <Button type="button" variant="ghost" size="icon" class="h-7 w-7" @click="zoomOut">
              <Minus class="h-3 w-3" />
            </Button>
            <span class="text-xs font-medium min-w-[40px] text-center">{{ Math.round(zoom * 100) }}%</span>
            <Button type="button" variant="ghost" size="icon" class="h-7 w-7" @click="zoomIn">
              <Plus class="h-3 w-3" />
            </Button>
            <Separator orientation="vertical" class="h-5 mx-1" />
            <Button type="button" variant="ghost" size="icon" class="h-7 w-7" @click="resetView">
              <RotateCcw class="h-3 w-3" />
            </Button>
          </div>

          <!-- Hint -->
          <div class="absolute top-2 right-2 z-10 bg-background/90 backdrop-blur rounded-md px-2 py-1 shadow-sm">
            <span class="text-xs text-muted-foreground">Clique em uma área para selecionar</span>
          </div>

          <!-- Legend (bottom of map) -->
          <div
            class="absolute bottom-2 left-2 z-10 flex items-center gap-3 bg-background/90 backdrop-blur rounded-md px-2 py-1 shadow-sm">
            <div class="flex items-center gap-1 text-xs">
              <div class="w-2.5 h-2.5 rounded-full bg-green-500" />
              <span>Esta gôndola</span>
            </div>
            <div class="flex items-center gap-1 text-xs">
              <div class="w-2.5 h-2.5 rounded-full bg-amber-500" />
              <span>Outra gôndola</span>
            </div>
          </div>

          <!-- Map content -->
          <div class="absolute inset-0 overflow-hidden cursor-crosshair" @wheel.prevent="handleWheel">
            <div :style="{
              transform: `translate(${pan.x}px, ${pan.y}px) scale(${zoom})`,
              transformOrigin: '0 0',
              position: 'relative',
              width: imageWidth + 'px',
              height: imageHeight + 'px',
            }">
              <!-- Map image -->
              <img ref="mapImageEl" :src="mapImageUrl!" class="block max-w-none" @load="handleImageLoad" />

              <!-- Regions SVG overlay -->
              <svg v-if="imageLoaded" class="absolute inset-0 pointer-events-none" :width="imageWidth"
                :height="imageHeight" :viewBox="`0 0 ${imageWidth} ${imageHeight}`">
                <g v-for="region in regions" :key="region.id">
                  <!-- Rectangle region -->
                  <rect v-if="region.shape !== 'circle'" :x="region.x" :y="region.y" :width="region.width"
                    :height="region.height" :fill="region.color || 'rgba(59, 130, 246, 0.3)'"
                    :stroke="isSelected(region) ? '#22c55e' : (region.color?.replace('0.3', '1') || '#3b82f6')"
                    :stroke-width="isSelected(region) ? 5 : 3"
                    :style="isSelected(region) ? 'filter: drop-shadow(0 0 4px rgba(34, 197, 94, 0.8))' : ''"
                    class="pointer-events-auto cursor-pointer transition-all hover:opacity-80"
                    :class="{ 'opacity-40': isLinkedToOther(region) }" @click="selectRegion(region)" />
                  <!-- Circle region -->
                  <ellipse v-else :cx="region.x + region.width / 2" :cy="region.y + region.height / 2"
                    :rx="region.width / 2" :ry="region.height / 2" :fill="region.color || 'rgba(59, 130, 246, 0.3)'"
                    :stroke="isSelected(region) ? '#22c55e' : (region.color?.replace('0.3', '1') || '#3b82f6')"
                    :stroke-width="isSelected(region) ? 5 : 3"
                    :style="isSelected(region) ? 'filter: drop-shadow(0 0 4px rgba(34, 197, 94, 0.8))' : ''"
                    class="pointer-events-auto cursor-pointer transition-all hover:opacity-80"
                    :class="{ 'opacity-40': isLinkedToOther(region) }" @click="selectRegion(region)" />
                  <!-- Label -->
                  <text :x="region.x + region.width / 2" :y="region.y + region.height / 2" text-anchor="middle"
                    dominant-baseline="middle"
                    class="text-xs font-medium fill-foreground pointer-events-none select-none"
                    style="font-size: 12px;">
                    {{ region.label || '' }}
                  </text>
                  <!-- Linked indicator -->
                  <circle v-if="isLinkedToOther(region)" :cx="region.x + region.width - 8" :cy="region.y + 8" r="6"
                    fill="#f59e0b" stroke="white" stroke-width="1" class="pointer-events-none" />
                  <!-- Current gondola indicator -->
                  <circle v-if="region.gondola_id === gondolaId" :cx="region.x + region.width - 8" :cy="region.y + 8"
                    r="6" fill="#22c55e" stroke="white" stroke-width="1" class="pointer-events-none" />
                </g>
              </svg>
            </div>
          </div>
        </div>
      </div>

      <DialogFooter class="flex items-center justify-end gap-2 z-[100] p-2">
        <Button type="button" variant="outline" @click="handleCancel">
          Cancelar
        </Button>
        <Button v-if="canUnlink" type="button" variant="outline"
          class="text-destructive hover:text-destructive hover:bg-destructive/10" @click="selectedRegionId = null">
          <X class="h-4 w-4 mr-2" />
          Desvincular
        </Button>
        <Button type="button" :disabled="!hasChanged" @click="handleConfirm">
          <Check class="h-4 w-4 mr-2" />
          Confirmar
        </Button>
      </DialogFooter>
    </DialogContent>

  </Dialog>
</template>

<template>
  <div class="w-full border-t bg-background flex flex-col shrink-0 lg:w-64 lg:border-t-0 lg:border-l">
    <div class="p-3 border-b">
      <h3 class="font-semibold text-sm">Áreas Mapeadas</h3>
      <p class="text-xs text-muted-foreground mt-0.5">
        Clique para selecionar. Duplo clique para editar.
      </p>
    </div>

    <ScrollArea class="flex-1" :style="maxHeight ? { maxHeight: `${maxHeight}px` } : undefined">
      <div class="p-2 space-y-1">
        <div 
          v-for="region in regions" 
          :key="region.id"
          class="flex items-center gap-2 p-2 rounded-md cursor-pointer hover:bg-muted/50 transition-colors"
          :class="{ 'bg-muted': selectedRegionId === region.id }" 
          @click="$emit('select', region)"
          @dblclick="$emit('edit', region)"
        >
          <div 
            class="w-3 h-3 rounded shrink-0"
            :style="{ backgroundColor: region.color?.replace('0.3', '0.7') || 'rgba(59, 130, 246, 0.7)' }" 
          />
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium truncate">
              {{ region.label || 'Área ' + (regions.indexOf(region) + 1) }}
            </p>
            <p class="text-xs text-muted-foreground">
              {{ regionTypeLabel(region.type) }}
            </p>
          </div>
          <div class="flex items-center gap-1 shrink-0">
            <Button
              type="button"
              variant="ghost"
              size="icon"
              class="h-6 w-6"
              title="Duplicar área"
              @click.stop="$emit('duplicate', region)"
            >
              <Copy class="h-3 w-3" />
            </Button>
            <Button 
              type="button" 
              variant="ghost" 
              size="icon" 
              class="h-6 w-6"
              title="Editar área"
              @click.stop="$emit('edit', region)"
            >
              <Pencil class="h-3 w-3" />
            </Button>
          </div>
        </div>

        <div v-if="regions.length === 0" class="p-4 text-center text-xs text-muted-foreground">
          Nenhuma área mapeada.<br />
          Use "Desenhar" para criar.
        </div>
      </div>
    </ScrollArea>
  </div>
</template>

<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Copy, Pencil } from 'lucide-vue-next'

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
  regions: Region[]
  selectedRegionId: string | null
  maxHeight?: number
}

withDefaults(defineProps<Props>(), {
  maxHeight: 300,
})

defineEmits<{
  (e: 'select', region: Region): void
  (e: 'edit', region: Region): void
  (e: 'duplicate', region: Region): void
}>()

const regionTypes: Record<string, string> = {
  gondola: 'Gôndola',
  island: 'Ilha',
  checkout: 'Checkout',
  entrance: 'Entrada',
  exit: 'Saída',
  storage: 'Estoque',
  other: 'Outro',
}

const regionTypeLabel = (type?: string) => {
  return regionTypes[type as keyof typeof regionTypes] || type || 'Gôndola'
}
</script>

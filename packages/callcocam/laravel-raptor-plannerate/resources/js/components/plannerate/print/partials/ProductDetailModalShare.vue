<script setup lang="ts">
import { computed } from 'vue'
import { Package, Ruler, Tag } from 'lucide-vue-next'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useT } from '@/composables/useT'
import type { Product } from '@/types/planogram'

interface Props {
  open: boolean
  product?: Product | null
  segmentQuantity?: number
  layerQuantity?: number
}

const props = defineProps<Props>()
const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { t } = useT()

const totalQuantity = computed(() => (props.segmentQuantity ?? 1) * (props.layerQuantity ?? 1))

function handleClose() {
  emit('update:open', false)
}
</script>

<template>
  <Dialog :open="open" @update:open="handleClose" :style="{ zIndex: 1000 }">
    <DialogContent class="force-light sm:max-w-lg z-[1000]">
      <DialogHeader class="pb-2">
        <DialogTitle class="text-base">{{ t('plannerate.print.product_detail.title') }}</DialogTitle>
      </DialogHeader>

      <div v-if="product" class="space-y-4">

        <!-- Imagem + Identificação -->
        <div class="flex gap-4">
          <div class="shrink-0 w-28 rounded-lg border bg-muted/20 flex items-center justify-center p-2 aspect-square">
            <img
              :src="product.image_url_encoded ?? product.image_url"
              :alt="product.name"
              class="max-h-full w-auto object-contain"
            />
          </div>

          <div class="flex-1 min-w-0 space-y-2">
            <div class="flex items-center gap-1.5">
              <Package class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
              <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                {{ t('plannerate.print.product_detail.identification') }}
              </span>
            </div>
            <p class="text-sm font-bold leading-tight">{{ product.name || '—' }}</p>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <p class="text-[10px] text-muted-foreground">{{ t('plannerate.analysis.results.ean') }}</p>
                <p class="font-mono text-xs">{{ product.ean || '—' }}</p>
              </div>
              <div>
                <p class="text-[10px] text-muted-foreground">{{ t('plannerate.print.product_detail.code') }}</p>
                <p class="font-mono text-xs">{{ product.codigo_erp || '—' }}</p>
              </div>
            </div>
            <div>
              <p class="text-[10px] text-muted-foreground">{{ t('plannerate.print.product_detail.brand') }}</p>
              <p class="text-xs font-medium">{{ product.brand || '—' }}</p>
            </div>
            <div>
              <p class="text-[10px] text-muted-foreground">{{ t('plannerate.print.product_detail.category') }}</p>
              <p class="text-xs leading-tight">{{ product.category_full_path ?? product.category ?? '—' }}</p>
            </div>
          </div>
        </div>

        <!-- Dimensões -->
        <div class="space-y-1.5">
          <div class="flex items-center gap-1.5">
            <Ruler class="h-3.5 w-3.5 text-muted-foreground" />
            <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
              {{ t('plannerate.print.product_detail.dimensions') }}
            </span>
          </div>
          <!-- ordem: Altura, Largura, Profundidade -->
          <div class="flex items-end gap-3 rounded border bg-muted/20 px-3 py-2">
            <div class="flex flex-col items-start">
              <span class="text-[9px] text-muted-foreground leading-none mb-0.5">{{ t('plannerate.print.product_detail.height') }}</span>
              <span class="text-xl font-bold leading-none tabular-nums">
                {{ product.height ?? '—' }}<sup class="text-[10px] font-normal text-muted-foreground ml-0.5 align-super">cm</sup>
              </span>
            </div>
            <span class="text-muted-foreground text-base pb-0.5">×</span>
            <div class="flex flex-col items-start">
              <span class="text-[9px] text-muted-foreground leading-none mb-0.5">{{ t('plannerate.print.product_detail.width') }}</span>
              <span class="text-xl font-bold leading-none tabular-nums">
                {{ product.width ?? '—' }}<sup class="text-[10px] font-normal text-muted-foreground ml-0.5 align-super">cm</sup>
              </span>
            </div>
            <span class="text-muted-foreground text-base pb-0.5">×</span>
            <div class="flex flex-col items-start">
              <span class="text-[9px] text-muted-foreground leading-none mb-0.5">{{ t('plannerate.print.product_detail.depth') }}</span>
              <span class="text-xl font-bold leading-none tabular-nums">
                {{ product.depth ?? '—' }}<sup class="text-[10px] font-normal text-muted-foreground ml-0.5 align-super">cm</sup>
              </span>
            </div>
          </div>
        </div>

        <!-- Posicionamento -->
        <div class="space-y-1.5">
          <div class="flex items-center gap-1.5">
            <Tag class="h-3.5 w-3.5 text-muted-foreground" />
            <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
              {{ t('plannerate.print.product_detail.positioning') }}
            </span>
          </div>
          <div class="flex items-end gap-3 rounded border bg-muted/20 px-3 py-2">
            <div class="flex flex-col items-start">
              <span class="text-[9px] text-muted-foreground leading-none mb-0.5">{{ t('plannerate.print.product_detail.fronts') }}</span>
              <span class="text-xl font-bold leading-none tabular-nums">
                {{ segmentQuantity ?? 1 }}<sup class="text-[10px] font-normal text-muted-foreground ml-0.5 align-super">un.</sup>
              </span>
            </div>
            <span class="text-muted-foreground text-base pb-0.5">×</span>
            <div class="flex flex-col items-start">
              <span class="text-[9px] text-muted-foreground leading-none mb-0.5">{{ t('plannerate.print.product_detail.stacking') }}</span>
              <span class="text-xl font-bold leading-none tabular-nums">
                {{ layerQuantity ?? 1 }}<sup class="text-[10px] font-normal text-muted-foreground ml-0.5 align-super">un.</sup>
              </span>
            </div>
            <span class="text-muted-foreground text-base pb-0.5">=</span>
            <div class="flex flex-col items-start">
              <span class="text-[9px] text-muted-foreground leading-none mb-0.5">{{ t('plannerate.print.product_detail.total') }}</span>
              <span class="text-xl font-bold leading-none tabular-nums text-primary">
                {{ totalQuantity }}<sup class="text-[10px] font-normal text-muted-foreground ml-0.5 align-super">un.</sup>
              </span>
            </div>
          </div>
        </div>

      </div>
    </DialogContent>
  </Dialog>
</template>

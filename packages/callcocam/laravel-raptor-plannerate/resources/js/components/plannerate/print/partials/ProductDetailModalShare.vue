<script setup lang="ts">
import { Ruler, Tag } from 'lucide-vue-next'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useT } from '@/composables/useT'
import type { Product } from '@/types/planogram'
import ProductDimensions from './ProductDimensions.vue'
import ProductIdentification from './ProductIdentification.vue'
import ProductPositioning from './ProductPositioning.vue'

interface Props {
  open: boolean
  product?: Product | null
  segmentQuantity?: number
  layerQuantity?: number
  shelfDepth?: number
}

defineProps<Props>()
const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { t } = useT()

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

          <ProductIdentification :product="product" class="flex-1 min-w-0" />
        </div>

        <!-- Dimensões -->
        <div class="space-y-1.5">
          <div class="flex items-center gap-1.5">
            <Ruler class="h-3.5 w-3.5 text-muted-foreground" />
            <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
              {{ t('plannerate.print.product_detail.dimensions') }}
            </span>
          </div>
          <ProductDimensions :product="product" />
        </div>

        <!-- Posicionamento -->
        <div class="space-y-1.5">
          <div class="flex items-center gap-1.5">
            <Tag class="h-3.5 w-3.5 text-muted-foreground" />
            <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
              {{ t('plannerate.print.product_detail.positioning') }}
            </span>
          </div>
          <ProductPositioning
            :layer-quantity="layerQuantity"
            :segment-quantity="segmentQuantity"
            :product-depth="product.depth"
            :shelf-depth="shelfDepth"
          />
        </div>

      </div>
    </DialogContent>
  </Dialog>
</template>

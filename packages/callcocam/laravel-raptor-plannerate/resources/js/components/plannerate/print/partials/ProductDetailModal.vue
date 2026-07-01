<script setup lang="ts">
import { TrendingDown, Loader2, Tag, Ruler, BarChart3, ShoppingCart, MessageSquare, Send, StickyNote, Box, Star, Target, CircleDollarSign, Weight, Coins, BadgeCheck, CalendarDays, Store } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { Separator } from '@/components/ui/separator'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { useAbcClassification } from '@/composables/plannerate/analysis/useAbcClassification'
import { useProductSales } from '@/composables/plannerate/products/useProductSales'
import { useTargetStockAnalysis } from '@/composables/plannerate/analysis/useTargetStockAnalysis'
import { useT } from '@/composables/useT'
import type { Product } from '@/types/planogram'
import ProductDimensions from './ProductDimensions.vue'
import ProductIdentification from './ProductIdentification.vue'
import ProductPositioning from './ProductPositioning.vue'

interface SegmentNoteItem {
  id: string
  content: string
  author: string
  created_at: string
}

interface Props {
  open: boolean
  product?: Product | null
  segmentId?: string | null
  segmentQuantity?: number
  layerQuantity?: number
  shelfDepth?: number
}

const props = defineProps<Props>()
const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { getClassification } = useAbcClassification()
const { getTargetStockData, calculateSegmentCapacity, getStockStatus, DEFAULT_TOLERANCE } = useTargetStockAnalysis()
const { salesData, isLoading: salesLoading, loadSales, clearSales } = useProductSales()
const { t } = useT()

const abcClassification = computed(() => getClassification(props.product?.ean))

const targetStockData = computed(() => {
  const ean = props.product?.ean
  if (!ean) return null
  return getTargetStockData(ean)
})

const segmentCapacity = computed(() =>
  calculateSegmentCapacity(
    props.segmentQuantity ?? 1,
    props.layerQuantity ?? 1,
    props.product?.depth ?? 0,
    props.shelfDepth ?? 0,
  ),
)

const stockStatus = computed(() => {
  if (!targetStockData.value) return null
  return getStockStatus(segmentCapacity.value, targetStockData.value.estoque_alvo, DEFAULT_TOLERANCE)
})

const totalQuantity = computed(() => (props.segmentQuantity ?? 1) * (props.layerQuantity ?? 1))

const abcBadgeClass = computed(() => {
  switch (abcClassification.value) {
    case 'A': return 'bg-green-500 hover:bg-green-500 text-white'
    case 'B': return 'bg-yellow-500 hover:bg-yellow-500 text-gray-900'
    case 'C': return 'bg-red-500 hover:bg-red-500 text-white'
    default: return 'bg-gray-400 text-white'
  }
})

const stockStatusInfo = computed(() => {
  switch (stockStatus.value) {
    case 'increase': return { label: t('plannerate.print.product_detail.increase_space'), class: 'bg-red-500 hover:bg-red-500 text-white', icon: '↑', color: 'text-red-600' }
    case 'decrease': return { label: t('plannerate.print.product_detail.decrease_space'), class: 'bg-yellow-500 hover:bg-yellow-500 text-gray-900', icon: '↓', color: 'text-yellow-600' }
    case 'ok': return { label: t('plannerate.print.product_detail.space_ok'), class: 'bg-green-500 hover:bg-green-500 text-white', icon: '✓', color: 'text-green-600' }
    default: return null
  }
})

// Capacidade atual vs alvo em percentual
const capacityPercent = computed(() => {
  if (!targetStockData.value?.estoque_alvo || !segmentCapacity.value) return null
  return Math.round((segmentCapacity.value / targetStockData.value.estoque_alvo) * 100)
})

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value)
}

/** Formata um valor percentual já em escala 0–100 (ex.: 40.9 → "40,9%"). */
function formatPercent(value: number): string {
  return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(value ?? 0) + '%'
}

function formatDate(date: string | null): string {
  if (!date) return '-'
  return new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(date))
}

// --- Notas ---
const notes = ref<SegmentNoteItem[]>([])
const notesLoading = ref(false)
const noteContent = ref('')
const noteSending = ref(false)

async function loadNotes() {
  if (!props.segmentId) return
  notesLoading.value = true
  try {
    const res = await fetch(`/api/editor/segments/${props.segmentId}/notes`)
    if (res.ok) {
      const json = await res.json()
      notes.value = json.data ?? []
    }
  } finally {
    notesLoading.value = false
  }
}

async function submitNote() {
  if (!props.segmentId || !noteContent.value.trim()) return
  noteSending.value = true
  try {
    const res = await fetch(`/api/editor/segments/${props.segmentId}/notes`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ content: noteContent.value.trim() }),
    })
    if (res.ok) {
      const json = await res.json()
      notes.value.unshift(json.data)
      noteContent.value = ''
      toast.success(t('plannerate.print.product_detail.note_added_success'))
    } else {
      toast.error(t('plannerate.print.product_detail.note_save_error'))
    }
  } finally {
    noteSending.value = false
  }
}

watch(
  () => [props.open, props.product?.id],
  ([open, productId]) => {
    if (open && productId) {
      loadSales(productId as string)
      loadNotes()
    } else if (!open) {
      clearSales()
      notes.value = []
      noteContent.value = ''
    }
  },
  { immediate: true },
)

function handleClose() {
  emit('update:open', false)
}
</script>

<template>
  <Dialog :open="open" @update:open="handleClose" :style="{ zIndex: 1000 }">
    <DialogContent class="force-light sm:max-w-5xl max-h-[90vh] flex flex-col z-1000">
      <DialogHeader class="shrink-0 pb-2">
        <div class="flex items-center gap-3">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-600/10 text-blue-600">
            <Box class="h-5 w-5" />
          </div>
          <div class="min-w-0">
            <DialogTitle>{{ t('plannerate.print.product_detail.title') }}</DialogTitle>
            <DialogDescription v-if="product">
              {{ t('plannerate.print.product_detail.description') }}
            </DialogDescription>
          </div>
        </div>
      </DialogHeader>

      <div v-if="product" class="flex-1 overflow-y-auto space-y-4 pr-1">

        <!-- Resumo rápido (Summary strip) -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
          <!-- Classificação ABC -->
          <div class="rounded-lg border bg-muted/40 p-2.5 flex items-center gap-2.5">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-background text-muted-foreground">
              <Star class="h-4 w-4" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-[10px] font-medium text-muted-foreground uppercase tracking-wide">{{ t('plannerate.print.product_detail.classification') }}</p>
              <div class="flex items-center gap-1 mt-0.5">
                <Badge v-if="abcClassification" :class="abcBadgeClass" class="text-xs px-2 py-0">
                  ABC {{ abcClassification }}
                </Badge>
                <span v-else class="text-xs text-muted-foreground">—</span>
              </div>
            </div>
          </div>

          <!-- Status do espaço -->
          <div class="rounded-lg border bg-muted/40 p-2.5 flex items-center gap-2.5">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-background text-blue-600">
              <MessageSquare class="h-4 w-4" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-[10px] font-medium text-muted-foreground uppercase tracking-wide">{{ t('plannerate.print.product_detail.space_status') }}</p>
              <div class="flex items-center gap-1 mt-0.5">
                <Badge v-if="stockStatusInfo" :class="stockStatusInfo.class" class="text-xs px-2 py-0">
                  {{ stockStatusInfo.icon }} {{ stockStatusInfo.label }}
                </Badge>
                <span v-else class="text-xs text-muted-foreground">{{ t('plannerate.print.product_detail.no_analysis') }}</span>
              </div>
            </div>
          </div>

          <!-- Capacidade / Alvo -->
          <div class="rounded-lg border bg-muted/40 p-2.5 flex items-center gap-2.5">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-background text-blue-600">
              <Target class="h-4 w-4" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-[10px] font-medium text-muted-foreground uppercase tracking-wide">{{ t('plannerate.print.product_detail.capacity_target') }}</p>
              <p class="text-sm font-bold mt-0.5" :class="stockStatusInfo?.color ?? 'text-foreground'">
                <template v-if="targetStockData">
                  {{ segmentCapacity }} / {{ targetStockData.estoque_alvo }} un.
                  <span v-if="capacityPercent !== null" class="text-[10px] font-normal text-muted-foreground ml-1">({{ capacityPercent }}%)</span>
                </template>
                <span v-else class="text-muted-foreground text-xs">{{ totalQuantity }} un.</span>
              </p>
            </div>
          </div>

          <!-- Faturamento -->
          <div class="rounded-lg border bg-muted/40 p-2.5 flex items-center gap-2.5">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-background text-green-600">
              <CircleDollarSign class="h-4 w-4" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-[10px] font-medium text-muted-foreground uppercase tracking-wide">{{ t('plannerate.print.product_detail.revenue') }}</p>
              <p class="text-sm font-bold mt-0.5 text-green-600 dark:text-green-500">
                <template v-if="salesLoading">
                  <Loader2 class="h-3 w-3 animate-spin inline" />
                </template>
                <template v-else-if="salesData?.summary.total_revenue">
                  {{ formatCurrency(salesData.summary.total_revenue) }}
                </template>
                <span v-else class="text-muted-foreground text-xs">—</span>
              </p>
            </div>
          </div>
        </div>

        <!-- Conteúdo principal: imagem + identidade + dimensões -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

          <!-- Imagem -->
          <div class="flex flex-col items-center gap-2">
            <div class="w-full rounded-lg border bg-muted/20 flex items-center justify-center p-3 aspect-square max-h-48">
              <img
                :src="product.image_url_encoded ?? product.image_url"
                :alt="product.name"
                class="max-h-full w-auto object-contain"
              />
            </div>
          </div>

          <!-- Identidade do produto -->
          <div class="rounded-lg border p-3">
            <ProductIdentification :product="product" />
          </div>

          <!-- Especificações técnicas + posicionamento -->
          <div class="space-y-3">
            <!-- Dimensões -->
            <div class="space-y-2 rounded-lg border p-3">
              <div class="flex items-center gap-2">
                <Ruler class="h-4 w-4 text-blue-600" />
                <span class="text-sm font-semibold text-foreground">{{ t('plannerate.print.product_detail.dimensions') }}</span>
              </div>
              <ProductDimensions :product="product" />
            </div>

            <!-- Posicionamento: frentes × empilhamento × profundidade = total -->
            <div class="space-y-2 rounded-lg border p-3">
              <div class="flex items-center gap-2">
                <Tag class="h-4 w-4 text-blue-600" />
                <span class="text-sm font-semibold text-foreground">{{ t('plannerate.print.product_detail.positioning') }}</span>
              </div>
              <ProductPositioning
                :layer-quantity="layerQuantity"
                :segment-quantity="segmentQuantity"
                :product-depth="product.depth"
                :shelf-depth="shelfDepth"
              />
            </div>

            <!-- Peso / Volume -->
            <!-- <div class="space-y-2 rounded-lg border p-3">
              <div class="flex items-center gap-2">
                <Weight class="h-4 w-4 text-blue-600" />
                <span class="text-sm font-semibold text-foreground">{{ t('plannerate.print.product_detail.weight') }}</span>
              </div>
              <div class="flex items-end gap-4">
                <div class="flex flex-col items-start">
                  <span class="text-xl font-bold leading-none tabular-nums">
                    {{ product.weight ?? '0.00' }}<sup class="text-[10px] font-normal text-muted-foreground ml-0.5 align-super">kg</sup>
                  </span>
                </div>
                <div v-if="product.volume" class="flex flex-col items-start">
                  <span class="text-[9px] text-muted-foreground leading-none mb-0.5">{{ t('plannerate.print.product_detail.volume') }}</span>
                  <span class="text-xl font-bold leading-none tabular-nums">
                    {{ product.volume }}<sup class="text-[10px] font-normal text-muted-foreground ml-0.5 align-super">L</sup>
                  </span>
                </div>
              </div>
            </div> -->
          </div>
        </div>

        <Separator />

        <!-- Tabs de análise (Vendas primeiro e ativa por padrão) -->
        <Tabs default-value="sales" class="w-full">
          <TabsList class="w-full grid grid-cols-3 h-8">
            <TabsTrigger value="sales" class="text-xs gap-1">
              <ShoppingCart class="h-3 w-3" />
              {{ t('plannerate.print.product_detail.sales') }}
            </TabsTrigger>
            <TabsTrigger value="notes" class="text-xs gap-1">
              <MessageSquare class="h-3 w-3" />
              {{ t('plannerate.print.product_detail.notes') }}
              <span v-if="notes.length" class="ml-1 rounded-full bg-primary/20 px-1.5 text-[9px] font-bold text-primary leading-none py-0.5">{{ notes.length }}</span>
            </TabsTrigger>
            <TabsTrigger value="stock" class="text-xs gap-1">
              <BarChart3 class="h-3 w-3" />
              {{ t('plannerate.print.product_detail.stock_analysis') }}
            </TabsTrigger>
          </TabsList>

          <!-- Aba: Notas -->
          <TabsContent value="notes" class="mt-3 space-y-3">

            <!-- Formulário nova nota -->
            <div v-if="segmentId" class="space-y-2">
              <Textarea
                v-model="noteContent"
                :placeholder="t('plannerate.print.product_detail.note_placeholder')"
                class="resize-none text-sm min-h-[72px]"
                :disabled="noteSending"
                @keydown.ctrl.enter="submitNote"
              />
              <div class="flex items-center justify-between">
                <span class="text-[10px] text-muted-foreground">{{ t('plannerate.print.product_detail.ctrl_enter_to_send') }}</span>
                <Button
                  size="sm"
                  class="h-7 gap-1.5 text-xs"
                  :disabled="!noteContent.trim() || noteSending"
                  @click="submitNote"
                >
                  <Send v-if="!noteSending" class="h-3 w-3" />
                  <Loader2 v-else class="h-3 w-3 animate-spin" />
                  {{ t('plannerate.print.product_detail.send') }}
                </Button>
              </div>
            </div>

            <Separator v-if="segmentId && (notes.length > 0 || notesLoading)" />

            <!-- Lista de notas -->
            <div v-if="notesLoading" class="flex justify-center py-6">
              <Loader2 class="h-5 w-5 animate-spin text-muted-foreground" />
            </div>

            <div v-else-if="notes.length === 0" class="flex flex-col items-center justify-center py-8 text-muted-foreground">
              <StickyNote class="h-8 w-8 mb-2 opacity-40" />
              <p class="text-sm">{{ t('plannerate.print.product_detail.no_notes_title') }}</p>
              <p class="text-xs">{{ t('plannerate.print.product_detail.no_notes_description') }}</p>
            </div>

            <div v-else class="space-y-2">
              <div
                v-for="note in notes"
                :key="note.id"
                class="rounded-lg border bg-muted/30 p-3 space-y-1"
              >
                <div class="flex items-center justify-between">
                  <span class="text-xs font-semibold text-foreground">{{ note.author }}</span>
                  <span class="text-[10px] text-muted-foreground">{{ note.created_at }}</span>
                </div>
                <p class="text-sm text-foreground leading-snug whitespace-pre-wrap">{{ note.content }}</p>
              </div>
            </div>

          </TabsContent>

          <!-- Aba: Análise de Estoque -->
          <TabsContent value="stock" class="mt-3">
            <div v-if="targetStockData" class="space-y-3">
              <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                <div class="rounded border bg-blue-50 dark:bg-blue-950/20 p-2 text-center">
                  <p class="text-[10px] text-muted-foreground">{{ t('plannerate.print.product_detail.target_stock') }}</p>
                  <p class="text-base font-bold text-blue-600 dark:text-blue-400">{{ targetStockData.estoque_alvo }}</p>
                  <p class="text-[10px] text-muted-foreground">un.</p>
                </div>
                <div class="rounded border bg-muted/30 p-2 text-center">
                  <p class="text-[10px] text-muted-foreground">{{ t('plannerate.print.product_detail.min_stock') }}</p>
                  <p class="text-base font-bold">{{ targetStockData.estoque_minimo }}</p>
                  <p class="text-[10px] text-muted-foreground">un.</p>
                </div>
                <div class="rounded border bg-muted/30 p-2 text-center"
                  :class="{
                    'bg-red-50 dark:bg-red-950/20': stockStatus === 'increase',
                    'bg-yellow-50 dark:bg-yellow-950/20': stockStatus === 'decrease',
                    'bg-green-50 dark:bg-green-950/20': stockStatus === 'ok',
                  }">
                  <p class="text-[10px] text-muted-foreground">{{ t('plannerate.print.product_detail.current_capacity') }}</p>
                  <p class="text-base font-bold" :class="{
                    'text-red-600': stockStatus === 'increase',
                    'text-yellow-600': stockStatus === 'decrease',
                    'text-green-600': stockStatus === 'ok',
                  }">{{ segmentCapacity }}</p>
                  <p class="text-[10px] text-muted-foreground">un.</p>
                </div>
                <div class="rounded border bg-muted/30 p-2 text-center">
                  <p class="text-[10px] text-muted-foreground">{{ t('plannerate.print.product_detail.safety_stock') }}</p>
                  <p class="text-base font-bold">{{ targetStockData.estoque_seguranca }}</p>
                  <p class="text-[10px] text-muted-foreground">un.</p>
                </div>
                <div class="rounded border bg-muted/30 p-2 text-center">
                  <p class="text-[10px] text-muted-foreground">{{ t('plannerate.print.product_detail.avg_demand') }}</p>
                  <p class="text-base font-bold">{{ targetStockData.demanda_media?.toFixed(1) ?? '—' }}</p>
                  <p class="text-[10px] text-muted-foreground">un./dia</p>
                </div>
                <div class="rounded border bg-muted/30 p-2 text-center">
                  <p class="text-[10px] text-muted-foreground">{{ t('plannerate.print.product_detail.coverage') }}</p>
                  <p class="text-base font-bold">{{ targetStockData.cobertura_dias }}</p>
                  <p class="text-[10px] text-muted-foreground">dias</p>
                </div>
              </div>

              <!-- Barra de progresso capacidade vs alvo -->
              <div v-if="capacityPercent !== null" class="space-y-1">
                <div class="flex justify-between text-[10px] text-muted-foreground">
                  <span>{{ t('plannerate.print.product_detail.target_space_occupation') }}</span>
                  <span>{{ capacityPercent }}%</span>
                </div>
                <div class="h-1.5 rounded-full bg-muted overflow-hidden">
                  <div
                    class="h-full rounded-full transition-all"
                    :class="{
                      'bg-red-500': stockStatus === 'increase',
                      'bg-yellow-500': stockStatus === 'decrease',
                      'bg-green-500': stockStatus === 'ok',
                    }"
                    :style="{ width: `${Math.min(capacityPercent, 100)}%` }"
                  />
                </div>
              </div>

              <!-- Recomendação permite frentes -->
              <div v-if="targetStockData.permite_frentes" class="rounded border bg-muted/30 px-3 py-2 text-xs text-muted-foreground">
                <span class="font-medium text-foreground">{{ t('plannerate.print.product_detail.recommended_fronts') }}</span> {{ targetStockData.permite_frentes }}
              </div>
            </div>

            <div v-else class="flex flex-col items-center justify-center py-8 text-muted-foreground">
              <BarChart3 class="h-8 w-8 mb-2 opacity-40" />
              <p class="text-sm">{{ t('plannerate.print.product_detail.no_stock_analysis') }}</p>
            </div>
          </TabsContent>

          <!-- Aba: Vendas -->
          <TabsContent value="sales" class="mt-3">
            <div v-if="salesLoading" class="flex items-center justify-center py-8">
              <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
            </div>

            <div v-else-if="!salesData || salesData.summary.total_sales === 0" class="flex flex-col items-center justify-center py-8 text-muted-foreground">
              <TrendingDown class="h-8 w-8 mb-2 opacity-40" />
              <p class="text-sm">{{ t('plannerate.sidebar.product_sales_summary.no_data_title') }}</p>
              <p class="text-xs">{{ t('plannerate.sidebar.product_sales_summary.no_data_description') }}</p>
            </div>

            <div v-else class="space-y-3">
              <!-- Três grupos lado a lado: Resumo · Custo/Lucro · Margem Líquida -->
              <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">

                <!-- Grupo 1: Resumo de Vendas -->
                <div class="space-y-2 rounded-lg border p-3">
                  <div class="flex items-center gap-2">
                    <BarChart3 class="h-4 w-4 text-blue-600 dark:text-blue-500" />
                    <h5 class="text-sm font-semibold text-foreground">{{ t('plannerate.sidebar.product_sales_summary.sales_group') }}</h5>
                  </div>
                  <div class="grid grid-cols-2 gap-2">
                    <div class="rounded-md bg-muted/40 p-2.5">
                      <p class="text-xs text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.total_sales') }}</p>
                      <p class="text-xl font-bold text-foreground">{{ salesData.summary.total_sales }}</p>
                    </div>
                    <div class="rounded-md bg-muted/40 p-2.5">
                      <p class="text-xs text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.quantity') }}</p>
                      <p class="text-xl font-bold text-foreground">{{ salesData.summary.total_quantity }}</p>
                    </div>
                    <div class="rounded-md bg-muted/40 p-2.5">
                      <p class="text-xs text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.revenue') }}</p>
                      <p class="text-lg font-bold text-green-600 dark:text-green-500">{{ formatCurrency(salesData.summary.total_revenue) }}</p>
                    </div>
                    <div class="rounded-md bg-muted/40 p-2.5">
                      <p class="text-xs text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.avg_sale_price') }}</p>
                      <p class="text-lg font-bold text-foreground">{{ formatCurrency(salesData.summary.avg_price) }}</p>
                    </div>
                  </div>
                </div>

                <!-- Grupo 2: Custo e Lucro Bruto -->
                <div class="space-y-2 rounded-lg border p-3">
                  <div class="flex items-center gap-2">
                    <Coins class="h-4 w-4 text-purple-600 dark:text-purple-500" />
                    <h5 class="text-sm font-semibold text-foreground">{{ t('plannerate.sidebar.product_sales_summary.cost_profit_group') }}</h5>
                  </div>
                  <div class="grid grid-cols-2 gap-2">
                    <div class="rounded-md bg-muted/40 p-2.5">
                      <p class="text-xs text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.avg_cost_unit') }}</p>
                      <p class="text-base font-bold text-foreground">{{ formatCurrency(salesData.summary.avg_cost) }}</p>
                    </div>
                    <div class="rounded-md bg-muted/40 p-2.5">
                      <p class="text-xs text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.total_cost') }}</p>
                      <p class="text-base font-bold text-foreground">{{ formatCurrency(salesData.summary.total_cost) }}</p>
                    </div>
                    <div class="rounded-md bg-muted/40 p-2.5">
                      <p class="text-xs text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.gross_profit_unit') }}</p>
                      <p class="text-base font-bold text-green-600 dark:text-green-500">{{ formatCurrency(salesData.summary.gross_profit_unit) }}</p>
                    </div>
                    <div class="rounded-md bg-muted/40 p-2.5">
                      <p class="text-xs text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.gross_profit_total') }}</p>
                      <p class="text-base font-bold text-green-600 dark:text-green-500">{{ formatCurrency(salesData.summary.gross_profit_total) }}</p>
                    </div>
                  </div>
                  <div class="rounded-md bg-muted/40 p-2.5">
                    <p class="text-xs text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.gross_margin') }}</p>
                    <p class="text-base font-bold text-purple-600 dark:text-purple-500">{{ formatPercent(salesData.summary.gross_margin_pct) }}</p>
                  </div>
                </div>

                <!-- Grupo 3: Margem Real / Líquida -->
                <div class="space-y-2 rounded-lg border border-green-500/30 bg-green-500/5 p-3">
                  <div class="flex items-center gap-2">
                    <BadgeCheck class="h-4 w-4 text-green-600 dark:text-green-500" />
                    <h5 class="text-sm font-semibold text-foreground">{{ t('plannerate.sidebar.product_sales_summary.net_margin_group') }}</h5>
                  </div>
                  <div class="space-y-2">
                    <div class="rounded-md bg-background/60 p-2.5">
                      <p class="text-xs text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.net_margin_unit') }}</p>
                      <p class="text-sm font-bold text-green-600 dark:text-green-500">{{ formatCurrency(salesData.summary.avg_margin) }}</p>
                    </div>
                    <div class="rounded-md bg-background/60 p-2.5">
                      <p class="text-xs text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.net_margin_total') }}</p>
                      <p class="text-sm font-bold text-green-600 dark:text-green-500">{{ formatCurrency(salesData.summary.total_margin) }}</p>
                    </div>
                    <div class="rounded-md bg-background/60 p-2.5">
                      <p class="text-xs text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.net_margin_percentage') }}</p>
                      <p class="text-sm font-bold text-green-600 dark:text-green-500">{{ formatPercent(salesData.summary.margin_percentage) }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Período de vendas + Top 5 Lojas -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- Período -->
                <div class="space-y-2 rounded-lg border p-3">
                  <div class="flex items-center gap-2">
                    <CalendarDays class="h-4 w-4 text-blue-600 dark:text-blue-500" />
                    <h5 class="text-sm font-semibold text-foreground">{{ t('plannerate.sidebar.product_sales_summary.sales_period') }}</h5>
                  </div>
                  <div class="rounded-md bg-muted/30 px-3 py-2">
                    <div class="flex items-center justify-between text-xs">
                      <span class="text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.first_sale') }}</span>
                      <span class="font-medium text-foreground">{{ formatDate(salesData.summary.first_sale_date) }}</span>
                    </div>
                    <div class="mt-1 flex items-center justify-between text-xs">
                      <span class="text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.last_sale') }}</span>
                      <span class="font-medium text-foreground">{{ formatDate(salesData.summary.last_sale_date) }}</span>
                    </div>
                  </div>
                </div>

                <!-- Top 5 Lojas -->
                <div v-if="salesData.top_stores.length > 0" class="space-y-2 rounded-lg border p-3">
                  <div class="flex items-center gap-2">
                    <Store class="h-4 w-4 text-blue-600 dark:text-blue-500" />
                    <h5 class="text-sm font-semibold text-foreground">{{ t('plannerate.sidebar.product_sales_summary.top_stores') }}</h5>
                  </div>
                  <div class="space-y-1">
                    <div
                      v-for="(store, index) in salesData.top_stores.slice(0, 5)"
                      :key="store.store_id"
                      class="flex items-center gap-2 rounded bg-muted/30 px-2.5 py-1.5"
                    >
                      <Badge variant="outline" class="h-4 w-4 justify-center p-0 text-[10px] shrink-0">{{ index + 1 }}</Badge>
                      <span class="text-xs font-medium flex-1 truncate">{{ store.store_name }}</span>
                      <span class="text-xs text-muted-foreground">{{ store.quantity }} un.</span>
                      <span class="text-xs font-semibold text-green-600 dark:text-green-500 shrink-0">{{ formatCurrency(store.revenue) }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </TabsContent>
        </Tabs>

      </div>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { TrendingDown,  Loader2, Package, Tag, Ruler, BarChart3, ShoppingCart, MessageSquare, Send, StickyNote } from 'lucide-vue-next'
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
      toast.success('Nota adicionada com sucesso.')
    } else {
      toast.error('Não foi possível salvar a nota.')
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
    <DialogContent class="force-light sm:max-w-4xl max-h-[90vh] flex flex-col z-1000">
      <DialogHeader class="shrink-0 pb-2">
        <DialogTitle>{{ t('plannerate.print.product_detail.title') }}</DialogTitle>
        <DialogDescription v-if="product">
          {{ t('plannerate.print.product_detail.description') }} 
        </DialogDescription>
      </DialogHeader>

      <div v-if="product" class="flex-1 overflow-y-auto space-y-4 pr-1">

        <!-- Resumo rápido (Summary strip) -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
          <div class="rounded-lg border bg-muted/40 p-2 flex items-center gap-2">
            <div class="flex-1 min-w-0">
              <p class="text-[10px] font-medium text-muted-foreground uppercase tracking-wide">Classificação</p>
              <div class="flex items-center gap-1 mt-0.5">
                <Badge v-if="abcClassification" :class="abcBadgeClass" class="text-xs px-2 py-0">
                  ABC {{ abcClassification }}
                </Badge>
                <span v-else class="text-xs text-muted-foreground">—</span>
              </div>
            </div>
          </div>

          <div class="rounded-lg border bg-muted/40 p-2 flex items-center gap-2">
            <div class="flex-1 min-w-0">
              <p class="text-[10px] font-medium text-muted-foreground uppercase tracking-wide">Status Espaço</p>
              <div class="flex items-center gap-1 mt-0.5">
                <Badge v-if="stockStatusInfo" :class="stockStatusInfo.class" class="text-xs px-2 py-0">
                  {{ stockStatusInfo.icon }} {{ stockStatusInfo.label }}
                </Badge>
                <span v-else class="text-xs text-muted-foreground">Sem análise</span>
              </div>
            </div>
          </div>

          <div class="rounded-lg border bg-muted/40 p-2">
            <p class="text-[10px] font-medium text-muted-foreground uppercase tracking-wide">Capacidade / Alvo</p>
            <p class="text-sm font-bold mt-0.5" :class="stockStatusInfo?.color ?? 'text-foreground'">
              <template v-if="targetStockData">
                {{ segmentCapacity }} / {{ targetStockData.estoque_alvo }} un.
                <span v-if="capacityPercent !== null" class="text-[10px] font-normal text-muted-foreground ml-1">({{ capacityPercent }}%)</span>
              </template>
              <span v-else class="text-muted-foreground text-xs">{{ totalQuantity }} un.</span>
            </p>
          </div>

          <div class="rounded-lg border bg-muted/40 p-2">
            <p class="text-[10px] font-medium text-muted-foreground uppercase tracking-wide">Faturamento</p>
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
          <div class="space-y-2">
            <div class="flex items-center gap-1.5 mb-1">
              <Package class="h-3.5 w-3.5 text-muted-foreground" />
              <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Identificação</span>
            </div>
            <div>
              <p class="text-[10px] text-muted-foreground">{{ t('plannerate.print.product_detail.name') }}</p>
              <p class="text-sm font-semibold leading-tight">{{ product.name || '—' }}</p>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <p class="text-[10px] text-muted-foreground">{{ t('plannerate.analysis.results.ean') }}</p>
                <p class="font-mono text-xs">{{ product.ean || '—' }}</p>
              </div>
              <div>
                <p class="text-[10px] text-muted-foreground">Código</p>
                <p class="font-mono text-xs">{{ product.codigo_erp || '—' }}</p>
              </div>
            </div>
            <div>
              <p class="text-[10px] text-muted-foreground">Marca</p>
              <p class="text-sm">{{ product.brand || '—' }}</p>
            </div>
            <div>
              <p class="text-[10px] text-muted-foreground">Categoria</p>
              <p class="text-xs leading-tight">{{ product.category_full_path ?? product.category ?? '—' }}</p>
            </div>
          </div>

          <!-- Especificações técnicas + posicionamento -->
          <div class="space-y-2">
            <div class="flex items-center gap-1.5 mb-2">
              <Ruler class="h-3.5 w-3.5 text-muted-foreground" />
              <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Dimensões</span>
            </div>
            <!-- Dimensões inline com sobreescrito -->
            <div class="flex items-end gap-3 rounded border bg-muted/20 px-3 py-2">
              <div class="flex flex-col items-start">
                <span class="text-[9px] text-muted-foreground leading-none mb-0.5">{{ t('plannerate.print.product_detail.width') }}</span>
                <span class="text-xl font-bold leading-none tabular-nums">
                  {{ product.width ?? '—' }}<sup class="text-[10px] font-normal text-muted-foreground ml-0.5 align-super">cm</sup>
                </span>
              </div>
              <span class="text-muted-foreground text-base pb-0.5">×</span>
              <div class="flex flex-col items-start">
                <span class="text-[9px] text-muted-foreground leading-none mb-0.5">{{ t('plannerate.print.product_detail.height') }}</span>
                <span class="text-xl font-bold leading-none tabular-nums">
                  {{ product.height ?? '—' }}<sup class="text-[10px] font-normal text-muted-foreground ml-0.5 align-super">cm</sup>
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

            <div class="flex items-center gap-1.5 mt-3 mb-2">
              <Tag class="h-3.5 w-3.5 text-muted-foreground" />
              <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Posicionamento</span>
            </div>
            <!-- Posicionamento inline com sobreescrito -->
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
                <span class="text-[9px] text-muted-foreground leading-none mb-0.5">Total</span>
                <span class="text-xl font-bold leading-none tabular-nums text-primary">
                  {{ totalQuantity }}<sup class="text-[10px] font-normal text-muted-foreground ml-0.5 align-super">un.</sup>
                </span>
              </div>
            </div>

            <!-- Peso / Volume se disponíveis -->
            <div v-if="product.weight || product.volume" class="flex items-end gap-3 rounded border bg-muted/20 px-3 py-2 mt-2">
              <div v-if="product.weight" class="flex flex-col items-start">
                <span class="text-[9px] text-muted-foreground leading-none mb-0.5">Peso</span>
                <span class="text-xl font-bold leading-none tabular-nums">
                  {{ product.weight }}<sup class="text-[10px] font-normal text-muted-foreground ml-0.5 align-super">kg</sup>
                </span>
              </div>
              <div v-if="product.volume" class="flex flex-col items-start">
                <span class="text-[9px] text-muted-foreground leading-none mb-0.5">Volume</span>
                <span class="text-xl font-bold leading-none tabular-nums">
                  {{ product.volume }}<sup class="text-[10px] font-normal text-muted-foreground ml-0.5 align-super">L</sup>
                </span>
              </div>
            </div>
          </div>
        </div>

        <Separator />

        <!-- Tabs de análise -->
        <Tabs default-value="notes" class="w-full">
          <TabsList class="w-full grid grid-cols-3 h-8">
            <TabsTrigger value="notes" class="text-xs gap-1">
              <MessageSquare class="h-3 w-3" />
              Notas
              <span v-if="notes.length" class="ml-1 rounded-full bg-primary/20 px-1.5 text-[9px] font-bold text-primary leading-none py-0.5">{{ notes.length }}</span>
            </TabsTrigger>
            <TabsTrigger value="stock" class="text-xs gap-1">
              <BarChart3 class="h-3 w-3" />
              {{ t('plannerate.print.product_detail.stock_analysis') }}
            </TabsTrigger>
            <TabsTrigger value="sales" class="text-xs gap-1">
              <ShoppingCart class="h-3 w-3" />
              Vendas
            </TabsTrigger>
          </TabsList>

          <!-- Aba: Notas -->
          <TabsContent value="notes" class="mt-3 space-y-3">

            <!-- Formulário nova nota -->
            <div v-if="segmentId" class="space-y-2">
              <Textarea
                v-model="noteContent"
                placeholder="Adicione uma orientação ou observação sobre este segmento..."
                class="resize-none text-sm min-h-[72px]"
                :disabled="noteSending"
                @keydown.ctrl.enter="submitNote"
              />
              <div class="flex items-center justify-between">
                <span class="text-[10px] text-muted-foreground">Ctrl+Enter para enviar</span>
                <Button
                  size="sm"
                  class="h-7 gap-1.5 text-xs"
                  :disabled="!noteContent.trim() || noteSending"
                  @click="submitNote"
                >
                  <Send v-if="!noteSending" class="h-3 w-3" />
                  <Loader2 v-else class="h-3 w-3 animate-spin" />
                  Enviar
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
              <p class="text-sm">Nenhuma nota para este segmento</p>
              <p class="text-xs">Adicione orientações ou observações acima</p>
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
                  <span>Ocupação do espaço alvo</span>
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
                <span class="font-medium text-foreground">Frentes recomendadas:</span> {{ targetStockData.permite_frentes }}
              </div>
            </div>

            <div v-else class="flex flex-col items-center justify-center py-8 text-muted-foreground">
              <BarChart3 class="h-8 w-8 mb-2 opacity-40" />
              <p class="text-sm">Nenhuma análise de estoque disponível</p>
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
              <!-- KPIs principais -->
              <div class="grid grid-cols-4 gap-2">
                <div class="rounded border bg-muted/30 p-2 text-center">
                  <p class="text-[10px] text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.total_sales') }}</p>
                  <p class="text-base font-bold">{{ salesData.summary.total_sales }}</p>
                </div>
                <div class="rounded border bg-muted/30 p-2 text-center">
                  <p class="text-[10px] text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.quantity') }}</p>
                  <p class="text-base font-bold">{{ salesData.summary.total_quantity }}</p>
                </div>
                <div class="rounded border bg-muted/30 p-2 text-center">
                  <p class="text-[10px] text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.revenue') }}</p>
                  <p class="text-sm font-bold text-green-600 dark:text-green-500">{{ formatCurrency(salesData.summary.total_revenue) }}</p>
                </div>
                <div class="rounded border bg-muted/30 p-2 text-center">
                  <p class="text-[10px] text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.avg_margin') }}</p>
                  <p class="text-sm font-bold">{{ formatCurrency(salesData.summary.avg_margin) }}</p>
                </div>
              </div>

              <!-- Preços + Período -->
              <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                  <p class="text-[10px] font-semibold text-foreground uppercase tracking-wide">{{ t('plannerate.sidebar.product_sales_summary.avg_prices') }}</p>
                  <div class="flex items-center justify-between rounded bg-muted/30 px-2.5 py-1.5 text-xs">
                    <span class="text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.sale') }}</span>
                    <span class="font-medium">{{ formatCurrency(salesData.summary.avg_price) }}</span>
                  </div>
                  <div class="flex items-center justify-between rounded bg-muted/30 px-2.5 py-1.5 text-xs">
                    <span class="text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.cost') }}</span>
                    <span class="font-medium">{{ formatCurrency(salesData.summary.avg_cost) }}</span>
                  </div>
                </div>

                <div class="space-y-1">
                  <p class="text-[10px] font-semibold text-foreground uppercase tracking-wide">{{ t('plannerate.sidebar.product_sales_summary.sales_period') }}</p>
                  <div class="flex items-center justify-between rounded bg-muted/30 px-2.5 py-1.5 text-xs">
                    <span class="text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.first_sale') }}</span>
                    <span class="font-medium">{{ formatDate(salesData.summary.first_sale_date) }}</span>
                  </div>
                  <div class="flex items-center justify-between rounded bg-muted/30 px-2.5 py-1.5 text-xs">
                    <span class="text-muted-foreground">{{ t('plannerate.sidebar.product_sales_summary.last_sale') }}</span>
                    <span class="font-medium">{{ formatDate(salesData.summary.last_sale_date) }}</span>
                  </div>
                </div>
              </div>

              <!-- Top Lojas -->
              <div v-if="salesData.top_stores.length > 0" class="space-y-1">
                <p class="text-[10px] font-semibold text-foreground uppercase tracking-wide">{{ t('plannerate.sidebar.product_sales_summary.top_stores') }}</p>
                <div class="space-y-1">
                  <div
                    v-for="(store, index) in salesData.top_stores.slice(0, 3)"
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
          </TabsContent>
        </Tabs>

      </div>
    </DialogContent>
  </Dialog>
</template>

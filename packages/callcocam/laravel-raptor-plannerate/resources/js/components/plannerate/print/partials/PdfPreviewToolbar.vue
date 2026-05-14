<script setup lang="ts">
import { Columns, Download, Loader2, Minus, Plus, Rows } from 'lucide-vue-next'
import { computed } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { useT } from '@/composables/useT'
import type { AbcAnalysis, StockAnalysis } from '@/types/planogram'
import DropdownPerformance from '../../DropdownPerformance.vue'

interface GondolaInfo {
    id: string
    name?: string
    planogram?: {
        name?: string
        [key: string]: unknown
    }
    location?: string
    side?: string
    flow?: string
    [key: string]: unknown
}

interface Props {
    gondola: GondolaInfo
    sectionsCount: number
    flowLabel: string
    localScale: number
    scaleMin: number
    scaleMax: number
    layoutDirection: 'row' | 'column'
    isGenerating: boolean
    isDownloading: boolean
    analysis?: {
        abc?: AbcAnalysis
        stock?: StockAnalysis
        [key: string]: any
    }
}

const props = defineProps<Props>()
const emit = defineEmits<{
    'increase-scale': []
    'decrease-scale': []
    'toggle-layout': []
    'download-pdf': []
}>()

const { t } = useT()
const scaleDisplay = computed(() => `${props.localScale.toFixed(1)}x`)
</script>

<template>
    <div
        class="fixed top-0 left-0 right-0 z-[500] border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/95"
    >
        <div class="max-w-screen-2xl mx-auto flex h-auto min-h-16 items-center justify-between gap-4 px-4 py-2">
            <!-- Info do planograma + gôndola -->
            <div class="flex min-w-0 flex-col gap-0.5">
                <p v-if="gondola.planogram?.name" class="truncate text-xs font-medium text-primary">
                    {{ (gondola.planogram as any)?.name }}
                </p>
                <h1 class="truncate text-base font-semibold text-slate-800 dark:text-slate-100">
                    {{ gondola.name }}
                </h1>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5">
                    <span class="flex items-center gap-1 text-xs text-slate-500 dark:text-slate-300">
                        <span class="text-slate-400 dark:text-slate-500">⊞</span>
                        {{ sectionsCount }}
                        {{ t('plannerate.print.module_selector.module') }}{{ sectionsCount !== 1 ? 's' : '' }}
                    </span>
                    <span
                        v-if="gondola.location"
                        class="flex items-center gap-1 text-xs text-slate-500 dark:text-slate-300"
                    >
                        <span class="text-slate-400 dark:text-slate-500">📍</span>
                        {{ gondola.location }}
                    </span>
                    <span
                        v-if="gondola.side"
                        class="flex items-center gap-1 text-xs text-slate-500 dark:text-slate-300"
                    >
                        <span class="text-slate-400 dark:text-slate-500">◧</span>
                        {{ gondola.side }}
                    </span>
                    <span class="flex items-center gap-1 text-xs text-slate-500 dark:text-slate-300">
                        <span class="text-slate-400 dark:text-slate-500">→</span>
                        {{ flowLabel }}
                    </span>
                </div>
            </div>

            <!-- Ações -->
            <div class="flex items-center gap-2">
                <!-- Controle de zoom -->
                <div class="flex items-center gap-1 rounded-md border border-slate-200 bg-background p-1 dark:border-slate-700">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-7"
                        :disabled="localScale <= scaleMin"
                        @click="emit('decrease-scale')"
                    >
                        <Minus class="size-3.5" />
                    </Button>
                    <Input :model-value="scaleDisplay" class="h-7 w-14 text-center text-xs" readonly />
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-7"
                        :disabled="localScale >= scaleMax"
                        @click="emit('increase-scale')"
                    >
                        <Plus class="size-3.5" />
                    </Button>
                </div>

                <DropdownPerformance :gondola="gondola" :analysis="analysis" />

                <Button
                    variant="outline"
                    size="sm"
                    :title="
                        layoutDirection === 'column'
                            ? t('plannerate.print.preview.switch_to_row')
                            : t('plannerate.print.preview.switch_to_column')
                    "
                    @click="emit('toggle-layout')"
                >
                    <Rows v-if="layoutDirection === 'column'" class="mr-2 h-4 w-4" />
                    <Columns v-else class="mr-2 h-4 w-4" />
                    {{
                        layoutDirection === 'column'
                            ? t('plannerate.print.preview.in_row')
                            : t('plannerate.print.preview.in_column')
                    }}
                </Button>

                <Button
                    size="sm"
                    :disabled="isGenerating || isDownloading"
                    @click="emit('download-pdf')"
                >
                    <Loader2 v-if="isDownloading" class="mr-2 h-4 w-4 animate-spin" />
                    <Download v-else class="mr-2 h-4 w-4" />
                    {{
                        isDownloading
                            ? t('plannerate.print.preview.downloading')
                            : t('plannerate.print.preview.download_pdf')
                    }}
                </Button>
            </div>
        </div>
        <slot/>
    </div>
</template>

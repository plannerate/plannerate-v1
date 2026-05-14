<script setup lang="ts">
import {
    Columns,
    Download,
    Loader2,
    Rows,
    ZoomIn,
    ZoomOut,
} from 'lucide-vue-next';
import { computed } from 'vue';
import ButtonWithTooltip from '@/components/ui/ButtonWithTooltip.vue';
import { Separator } from '@/components/ui/separator';
import { useT } from '@/composables/useT';
import type { AbcAnalysis, StockAnalysis } from '@/types/planogram';
import DropdownPerformance from '../../DropdownPerformance.vue';
import PdfPageHeader from './PdfPageHeader.vue';

interface GondolaInfo {
    id: string;
    name?: string;
    location?: string;
    side?: string;
    flow?: string;
    planogram?: {
        name?: string;
        start_date?: string;
        category?: { name?: string } | null;
        [key: string]: unknown;
    } | null;
    [key: string]: unknown;
}

interface Props {
    gondola: GondolaInfo;
    sectionsCount: number;
    flowLabel: string;
    localScale: number;
    scaleMin: number;
    scaleMax: number;
    layoutDirection: 'row' | 'column';
    isGenerating: boolean;
    isDownloading: boolean;
    tenantName?: string;
    responsavel?: string;
    analysis?: {
        abc?: AbcAnalysis;
        stock?: StockAnalysis;
        [key: string]: any;
    };
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'increase-scale': [];
    'decrease-scale': [];
    'toggle-layout': [];
    'download-pdf': [];
}>();

const { t } = useT();
const scaleDisplay = computed(() => `${props.localScale.toFixed(1)}x`);
</script>

<template>
    <div
        class="fixed top-0 right-0 left-0 z-[500] border-b-2 border-primary bg-white/95 shadow-sm backdrop-blur dark:bg-slate-900/95"
    >
        <!-- Linha 1: logo + metadados + ações -->
        <div class="flex items-center justify-between gap-4 px-4 py-2">
            <!-- Logo -->
            <div class="shrink-0">
                <img
                    src="/img/marca-claro.png"
                    alt="Logo"
                    class="block h-8 w-auto dark:hidden"
                />
                <img
                    src="/img/marcadark.png"
                    alt="Logo"
                    class="hidden h-8 w-auto dark:block"
                />
            </div>

            <!-- Separador + título -->
            <div class="h-8 w-px shrink-0 bg-slate-200 dark:bg-slate-700"></div>
            <div class="shrink-0">
                <p
                    v-if="tenantName"
                    class="text-[9px] leading-none font-bold tracking-widest text-slate-500 uppercase dark:text-slate-400"
                >
                    {{ tenantName }}
                </p>
                <h1
                    class="text-sm leading-none font-black tracking-wide text-slate-900 uppercase dark:text-slate-100"
                >
                    {{ t('plannerate.print.preview.exposure_planogram') }}
                </h1>
            </div>

            <!-- Separador + metadados compactos -->
            <div class="h-8 w-px shrink-0 bg-slate-200 dark:bg-slate-700"></div>
            <div class="flex-1">
                <PdfPageHeader
                    :gondola="gondola"
                    :tenant-name="tenantName"
                    :responsavel="responsavel"
                    :flow-label="flowLabel"
                />
            </div>

            <!-- Ações -->
            <div class="flex shrink-0 items-center gap-1.5">
                <!-- Zoom -->
                <div
                    class="flex items-center gap-0.5 rounded-md border bg-background px-0.5 py-0.5"
                >
                    <ButtonWithTooltip
                        variant="ghost"
                        size="icon"
                        class="size-7"
                        :disabled="localScale <= scaleMin"
                        :tooltip="t('plannerate.toolbar.zoom_out')"
                        @click="emit('decrease-scale')"
                    >
                        <ZoomOut class="size-4" />
                    </ButtonWithTooltip>
                    <span
                        class="w-10 text-center text-xs font-medium tabular-nums select-none"
                        >{{ scaleDisplay }}</span
                    >
                    <ButtonWithTooltip
                        variant="ghost"
                        size="icon"
                        class="size-7"
                        :disabled="localScale >= scaleMax"
                        :tooltip="t('plannerate.toolbar.zoom_in')"
                        @click="emit('increase-scale')"
                    >
                        <ZoomIn class="size-4" />
                    </ButtonWithTooltip>
                </div>

                <Separator orientation="vertical" class="h-7" />

                <DropdownPerformance
                    :gondola="gondola as any"
                    :analysis="analysis"
                />

                <Separator orientation="vertical" class="h-7" />

                <ButtonWithTooltip
                    variant="outline"
                    size="sm"
                    :tooltip="
                        layoutDirection === 'column'
                            ? t('plannerate.print.preview.switch_to_row')
                            : t('plannerate.print.preview.switch_to_column')
                    "
                    @click="emit('toggle-layout')"
                >
                    <div
                        v-if="layoutDirection === 'column'"
                        class="flex items-center justify-center space-x-1"
                    >
                        <Rows class="size-4" />
                        <span>{{ t('plannerate.print.preview.rows') }}</span>
                    </div>
                    <div
                        v-else
                        class="flex items-center justify-center space-x-1"
                    >
                        <Columns class="size-4" />
                        <span>{{ t('plannerate.print.preview.columns') }}</span>
                    </div>
                </ButtonWithTooltip>

                <ButtonWithTooltip
                    variant="outline"
                    size="sm"
                    :disabled="isGenerating || isDownloading"
                    :tooltip="t('plannerate.print.preview.download_pdf')"
                    @click="emit('download-pdf')"
                >
                    <Loader2
                        v-if="isDownloading"
                        class="mr-1.5 h-4 w-4 animate-spin"
                    />
                    <Download v-else class="mr-1.5 h-4 w-4" />
                    {{
                        isDownloading
                            ? t('plannerate.print.preview.downloading')
                            : t('plannerate.print.preview.download_pdf')
                    }}
                </ButtonWithTooltip>
            </div>
        </div>

        <!-- Slot: indicador de fluxo -->
        <slot />
    </div>
</template>

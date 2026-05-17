<script setup lang="ts">
import {
    ArrowUpIcon,
    CalendarDaysIcon,
    ClipboardListIcon,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useT } from '@/composables/useT';
import type { Section } from '@/types/planogram';
import Indicador from '../Indicador.vue';
import PdfSection from './partials/PdfSection.vue';

interface GondolaMeta {
    id: string;
    name?: string;
    location?: string;
    side?: string;
    flow?: string;
    alignment?: string;
    planogram?: {
        name?: string;
        type?: string;
        start_date?: string;
        description?: string;
        category?: { name?: string } | null;
    } | null;
}

interface Props {
    section: Section;
    gondola: GondolaMeta;
    scaleFactor: number;
    alignment: string;
    index: number;
    total: number;
    responsavel?: string;
    tenantName?: string;
}

const props = defineProps<Props>();
const { t } = useT();

const moduleAnchorId = computed(() => `pdf-module-${props.index + 1}`);
const isLeftToRight = computed(() => props.gondola.flow !== 'right_to_left');

const dimensionsLabel = computed(() => {
    const w = props.section.width ?? 0;
    const h = props.section.height ?? 0;

    return `${t('plannerate.print.labels.width_short')}: ${w}  ${t('plannerate.print.labels.height_short')}: ${h}`;
});

const flowPositions = computed(() =>
    Array.from({ length: props.total }, (_, i) => ({
        anchorId: `pdf-module-${i + 1}`,
        position: i + 1,
        isCurrent: i === props.index,
    })),
);

const flowStartLabel = computed(() =>
    isLeftToRight.value
        ? t('plannerate.print.module_page.start_flow')
        : t('plannerate.print.module_page.end_flow'),
);
const flowEndLabel = computed(() =>
    isLeftToRight.value
        ? t('plannerate.print.module_page.end_flow')
        : t('plannerate.print.module_page.start_flow'),
);

function scrollToModule(anchorId: string): void {
    const target = document.getElementById(anchorId);

    if (!target) {
        return;
    }

    target.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });

    window.history.replaceState(null, '', `#${anchorId}`);
}
</script>

<template>
    <div
        :id="moduleAnchorId"
        :data-pdf-module-page="section.id"
        :data-section-id="section.id"
        class="w-full scroll-mt-28 overflow-hidden border border-slate-200 bg-white shadow-lg"
    >
        <!-- HEADER -->
        <div class="border-b border-slate-200 px-6 py-4">
            <div class="flex items-start justify-between gap-6">
                <!-- Logo + título -->
                <div class="flex items-center gap-3">
                    <div class="shrink-0">
                        <img
                            src="/img/marca-claro.png"
                            alt="Logo"
                            class="block h-12 w-auto dark:hidden"
                        />
                        <img
                            src="/img/marcadark.png"
                            alt="Logo"
                            class="hidden h-12 w-auto dark:block"
                        />
                    </div>
                    <div class="border-l border-slate-200 pl-3">
                        <p
                            v-if="tenantName"
                            class="mb-0.5 text-[10px] leading-none font-bold tracking-widest text-slate-500 uppercase"
                        >
                            {{ tenantName }}
                        </p>
                        <h1
                            class="text-xl leading-none font-black tracking-wide text-slate-900 uppercase"
                        >
                            {{ t('plannerate.print.module_page.title') }}
                        </h1>
                        <p
                            class="mt-1 text-[9px] font-semibold tracking-widest text-primary uppercase"
                        >
                            {{ t('plannerate.print.module_page.tagline') }}
                        </p>
                    </div>
                </div>

                <!-- Campos informativos: data / loja / cód. loja -->
                <div
                    class="grid grid-cols-3 gap-x-4 gap-y-1 rounded-lg border border-slate-200 p-3"
                >
                    <div class="flex flex-col gap-1">
                        <div class="flex items-center gap-1">
                            <CalendarDaysIcon
                                class="h-3 w-3 shrink-0 text-primary"
                            />
                            <span
                                class="text-[9px] tracking-wider text-slate-400 uppercase"
                                >{{
                                    t(
                                        'plannerate.print.labels.publication_date',
                                    )
                                }}</span
                            >
                        </div>
                        <span
                            class="min-w-20 border-b border-dashed border-slate-300 pb-0.5 text-xs font-medium text-slate-700"
                        >
                            {{ gondola.planogram?.start_date || '—' }}
                        </span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span
                            class="text-[9px] tracking-wider text-slate-400 uppercase"
                            >{{ t('plannerate.print.labels.store') }}</span
                        >
                        <span
                            class="min-w-20 border-b border-dashed border-slate-300 pb-0.5 text-xs font-medium text-slate-700"
                        >
                            {{ gondola.location || '—' }}
                        </span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span
                            class="text-[9px] tracking-wider text-slate-400 uppercase"
                            >{{ t('plannerate.print.labels.store_code') }}</span
                        >
                        <span
                            class="min-w-16 border-b border-dashed border-slate-300 pb-0.5 text-xs font-medium text-slate-700"
                            >—</span
                        >
                    </div>
                </div>
            </div>
        </div>

        <!-- INFO BAR -->
        <div class="border-b border-slate-200 bg-slate-50 px-6 py-2">
            <div class="flex items-start gap-0 divide-x divide-slate-200">
                <div class="flex min-w-20 flex-col gap-0.5 pr-4">
                    <span
                        class="text-[9px] tracking-wider text-slate-400 uppercase"
                        >{{ t('plannerate.print.labels.category') }}</span
                    >
                    <span
                        class="border-b border-dashed border-slate-400 pb-0.5 text-xs font-medium text-slate-700"
                    >
                        {{ gondola.planogram?.category?.name || '—' }}
                    </span>
                </div>
                <div class="flex min-w-20 flex-col gap-0.5 px-4">
                    <span
                        class="text-[9px] tracking-wider text-slate-400 uppercase"
                        >{{ t('plannerate.print.labels.subcategory') }}</span
                    >
                    <span
                        class="border-b border-dashed border-slate-400 pb-0.5 text-xs font-medium text-slate-700"
                    >
                        {{ gondola.side || '—' }}
                    </span>
                </div>
                <div class="flex min-w-20 flex-col gap-0.5 px-4">
                    <span
                        class="text-[9px] tracking-wider text-slate-400 uppercase"
                        >{{ t('plannerate.print.labels.gondola_type') }}</span
                    >
                    <span
                        class="border-b border-dashed border-slate-400 pb-0.5 text-xs font-medium text-slate-700"
                    >
                        {{ gondola.name || '—' }}
                    </span>
                </div>
                <div class="flex min-w-14 flex-col gap-0.5 px-4">
                    <span
                        class="text-[9px] tracking-wider text-slate-400 uppercase"
                        >{{ t('plannerate.print.labels.module') }}</span
                    >
                    <span
                        class="border-b border-dashed border-slate-400 pb-0.5 text-xs font-medium text-slate-700"
                    >
                        {{ section.ordering }} / {{ total }}
                    </span>
                </div>
                <div class="flex min-w-28 flex-col gap-0.5 px-4">
                    <span
                        class="text-[9px] tracking-wider text-slate-400 uppercase"
                        >{{
                            t('plannerate.print.labels.module_dimensions')
                        }}</span
                    >
                    <span
                        class="border-b border-dashed border-slate-400 pb-0.5 text-xs font-medium text-slate-700"
                    >
                        {{ dimensionsLabel }} mm
                    </span>
                </div>
                <div class="flex min-w-20 flex-col gap-0.5 pl-4">
                    <span
                        class="text-[9px] tracking-wider text-slate-400 uppercase"
                        >{{
                            t('plannerate.print.labels.execution_level')
                        }}</span
                    >
                    <span
                        class="border-b border-dashed border-slate-400 pb-0.5 text-xs font-medium text-slate-700"
                    >
                        {{ gondola.planogram?.type || '—' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- CONTEÚDO PRINCIPAL -->
        <div class="flex">
            <!-- Esquerda: Vista Frontal + seção -->
            <div class="min-w-0 flex-1 border-r border-slate-200 p-4">
                <!-- Indicador de fluxo -->
                <div class="relative mb-2 h-8">
                    <Indicador :is-left-to-right="isLeftToRight" />
                </div>

                <!-- Seção + indicador de altura -->
                <div class="flex items-start gap-4">
                    <!-- Gondola section rendering — layout row evita mt-12 do modo coluna -->
                    <div
                        class="flex flex-1 justify-center overflow-x-auto"
                        :style="{
                            paddingTop: `${Math.ceil(scaleFactor * 50)}px`,
                        }"
                    >
                        <PdfSection
                            :section="section"
                            :scale-factor="scaleFactor"
                            :alignment="alignment"
                            layout-direction="row"
                            :index="0"
                            :extra-height="0"
                        />
                    </div>

                    <!-- Indicador de altura -->
                    <div
                        class="flex flex-col items-center gap-1 self-stretch py-2"
                    >
                        <ArrowUpIcon class="h-3 w-3 text-slate-400" />
                        <div class="w-px flex-1 bg-slate-300"></div>
                        <span
                            class="text-[9px] tracking-wider whitespace-nowrap text-slate-500 uppercase"
                            style="
                                writing-mode: vertical-rl;
                                transform: rotate(180deg);
                            "
                        >
                            {{
                                t('plannerate.print.module_page.total_height')
                            }}: {{ section.height }}mm
                        </span>
                        <div class="w-px flex-1 bg-slate-300"></div>
                        <ArrowUpIcon
                            class="h-3 w-3 rotate-180 text-slate-400"
                        />
                    </div>
                </div>

                <!-- Identificação do módulo + largura -->
                <div class="mt-4 text-center">
                    <p
                        class="text-xs font-bold tracking-wide text-slate-700 uppercase"
                    >
                        {{ t('plannerate.print.labels.module') }} #{{
                            section.ordering 
                        }}
                        — {{ t('plannerate.print.module_page.front') }}
                    </p>
                    <div class="mt-1 flex items-center justify-center gap-2">
                        <div class="relative h-px flex-1 bg-slate-400">
                            <span
                                class="absolute top-1/2 left-0 -translate-y-1/2 text-[10px] text-slate-400"
                                >←</span
                            >
                        </div>
                        <span
                            class="px-1 text-[10px] whitespace-nowrap text-slate-600"
                        >
                            {{ t('plannerate.print.product_detail.width') }}:
                            {{ section.width }}mm
                        </span>
                        <div class="relative h-px flex-1 bg-slate-400">
                            <span
                                class="absolute top-1/2 right-0 -translate-y-1/2 text-[10px] text-slate-400"
                                >→</span
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- Direita: Posição do Fluxo -->
            <div class="flex w-44 shrink-0 flex-col gap-5 p-4">
                <div>
                    <div class="mb-3 flex justify-center">
                        <span
                            class="bg-primary px-3 py-1.5 text-[9px] font-bold tracking-widest text-primary-foreground uppercase"
                        >
                            {{
                                t('plannerate.print.module_page.flow_position')
                            }}
                        </span>
                    </div>

                    <div class="flex flex-col items-center gap-0.5">
                        <p
                            class="mb-1 text-[9px] tracking-wider text-slate-500 uppercase"
                        >
                            {{ flowStartLabel }}
                        </p>

                        <template
                            v-for="pos in flowPositions"
                            :key="pos.position"
                        >
                            <!-- Seta para cima -->
                            <div class="flex flex-col items-center">
                                <div
                                    class="h-0 w-0 border-x-[5px] border-b-[7px] border-x-transparent"
                                    :class="
                                        pos.isCurrent
                                            ? 'border-b-primary'
                                            : 'border-b-slate-300'
                                    "
                                ></div>
                                <div
                                    class="h-3 w-px"
                                    :class="
                                        pos.isCurrent
                                            ? 'bg-primary'
                                            : 'bg-slate-300'
                                    "
                                ></div>
                            </div>

                            <!-- Caixa de posição -->
                            <a
                                :href="`#${pos.anchorId}`"
                                @click.prevent="scrollToModule(pos.anchorId)"
                                :aria-label="
                                    t(
                                        'plannerate.print.module_page.module_anchor_label',
                                        { position: String(pos.position) },
                                    )
                                "
                                class="block w-24 rounded border-2 px-2 py-1.5 text-center transition-colors hover:border-primary/70 hover:bg-primary/5 focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:outline-none"
                                :class="
                                    pos.isCurrent
                                        ? 'border-primary bg-primary/10'
                                        : 'border-dashed border-slate-300'
                                "
                            >
                                <p
                                    class="text-[10px] font-semibold"
                                    :class="
                                        pos.isCurrent
                                            ? 'text-primary'
                                            : 'text-slate-500'
                                    "
                                >
                                    {{
                                        t(
                                            'plannerate.print.module_page.position',
                                        )
                                    }}
                                </p>
                                <p
                                    class="text-[9px]"
                                    :class="
                                        pos.isCurrent
                                            ? 'font-bold text-primary'
                                            : 'text-slate-400'
                                    "
                                >
                                    {{ pos.position }}
                                </p>
                            </a>
                        </template>

                        <!-- Última seta -->
                        <div class="mt-0.5 h-3 w-px bg-slate-300"></div>
                        <p
                            class="mt-1 text-[9px] tracking-wider text-slate-500 uppercase"
                        >
                            {{ flowEndLabel }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Observações -->
        <div class="border-t border-slate-200 px-6 py-4">
            <div class="mb-2 flex items-center gap-2">
                <span
                    class="bg-primary px-3 py-1 text-[9px] font-bold tracking-widest text-primary-foreground uppercase"
                >
                    {{ t('plannerate.print.labels.observations') }}
                </span>
            </div>
            <div class="min-h-14 rounded border border-slate-200 p-3">
                <p class="text-[10px] leading-relaxed text-slate-500">
                    {{ gondola.planogram?.description || '' }}
                </p>
            </div>
        </div>

        <!-- RODAPÉ -->
        <div
            class="flex items-center gap-4 border-t border-slate-200 bg-slate-50 px-6 py-3"
        >
            <div
                class="flex flex-1 items-center gap-2 border-r border-slate-200 pr-4"
            >
                <ClipboardListIcon class="h-4 w-4 shrink-0 text-primary" />
                <div>
                    <p
                        class="text-[9px] tracking-wider text-slate-400 uppercase"
                    >
                        {{ t('plannerate.print.labels.responsible') }}
                    </p>
                    <p
                        class="min-w-24 border-b border-dashed border-slate-300 pb-0.5 text-xs font-medium text-slate-700"
                    >
                        {{ responsavel || '—' }}
                    </p>
                </div>
            </div>
            <div class="flex-1 border-r border-slate-200 px-4">
                <p class="text-[9px] tracking-wider text-slate-400 uppercase">
                    {{ t('plannerate.print.labels.approved_by') }}
                </p>
                <p
                    class="min-w-24 border-b border-dashed border-slate-300 pb-0.5 text-xs font-medium text-slate-700"
                >
                    —
                </p>
            </div>
            <div class="flex-1 border-r border-slate-200 px-4">
                <p class="text-[9px] tracking-wider text-slate-400 uppercase">
                    {{ t('plannerate.print.labels.approval_date') }}
                </p>
                <p
                    class="border-b border-dashed border-slate-300 pb-0.5 text-xs font-medium text-slate-700"
                >
                    —/—/—
                </p>
            </div>
            <div class="shrink-0">
                <div
                    class="rounded bg-primary px-4 py-2 text-center text-primary-foreground"
                >
                    <p
                        class="mb-0.5 text-[9px] leading-none tracking-wider uppercase"
                    >
                        {{ t('plannerate.print.labels.version') }}
                    </p>
                    <p class="text-sm leading-none font-black">V1.0</p>
                </div>
            </div>
        </div>
    </div>
</template>

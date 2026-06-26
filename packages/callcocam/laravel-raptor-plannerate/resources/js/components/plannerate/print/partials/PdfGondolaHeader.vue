<script setup lang="ts">
import {
    ArrowRightIcon,
    Building2Icon,
    CalendarDaysIcon,
    LayersIcon,
    LayoutGridIcon,
    PackageIcon,
    StoreIcon,
    UserIcon,
} from 'lucide-vue-next';
import { computed, type Component } from 'vue';
import { useT } from '@/composables/useT';

/**
 * Metadados mínimos da gôndola/planograma exibidos no cabeçalho da
 * impressão em linha (modo row / página única landscape).
 */
interface GondolaMeta {
    name?: string;
    location?: string;
    side?: string;
    flow?: string;
    planogram?: {
        name?: string;
        type?: string;
        start_date?: string;
        description?: string;
        category?: { name?: string } | null;
    } | null;
}

interface Props {
    gondola: GondolaMeta;
    tenantName?: string;
    responsavel?: string;
    flowLabel: string;
    sectionsCount: number;
}

const props = defineProps<Props>();
const { t } = useT();

/**
 * Título principal do cabeçalho: usa o nome do planograma quando existir,
 * caindo para o rótulo genérico de planograma de exposição.
 */
const title = computed(
    () =>
        props.gondola.planogram?.name ||
        t('plannerate.print.preview.exposure_planogram'),
);

/**
 * Campos informativos pertinentes da gôndola, exibidos em linha no
 * cabeçalho. Cada item tem ícone, rótulo curto e valor (com fallback '—').
 */
const metaItems = computed<Array<{ icon: Component; label: string; value: string }>>(
    () => [
        {
            icon: Building2Icon,
            label: t('plannerate.print.preview.client'),
            value: props.tenantName || '—',
        },
        {
            icon: LayoutGridIcon,
            label: t('plannerate.print.share.module'),
            value: props.gondola.name || '—',
        },
        {
            icon: StoreIcon,
            label: t('plannerate.print.labels.store'),
            value: props.gondola.location || '—',
        },
        {
            icon: PackageIcon,
            label: t('plannerate.print.labels.category'),
            value: props.gondola.planogram?.category?.name || '—',
        },
        {
            icon: LayersIcon,
            label: t('plannerate.print.preview.modules'),
            value: String(props.sectionsCount),
        },
        {
            icon: CalendarDaysIcon,
            label: t('plannerate.print.labels.publication'),
            value: props.gondola.planogram?.start_date || '—',
        },
        {
            icon: UserIcon,
            label: t('plannerate.print.labels.responsible'),
            value: props.responsavel || '—',
        },
        {
            icon: ArrowRightIcon,
            label: t('plannerate.print.labels.flow'),
            value: props.flowLabel,
        },
    ],
);
</script>

<template>
    <div class="border-b border-slate-200 bg-white px-6 py-4">
        <div class="flex items-center  justify-between space-x-3 gap-6">
            <!-- Logo + título -->
            <div class="flex  items-center justify-between gap-6">
                <div class="flex shrink-0 items-center gap-3">
                    <img src="/img/marca-claro.png" alt="Logo" class="block h-11 w-auto" />
                    <div class="border-l border-slate-200 pl-3">
                        <p v-if="tenantName"
                            class="mb-0.5 text-xs leading-none font-bold tracking-widest text-slate-500 uppercase">
                            {{ tenantName }}
                        </p>
                        <h1 class="text-2xl leading-none font-black tracking-wide text-slate-900 uppercase">
                            {{ title }}
                        </h1>
                    </div>
                </div>
                <div class="h-10 w-px shrink-0 bg-slate-200 dark:bg-slate-700"></div>
                <!-- Metadados em linha -->
                <div class="flex flex-wrap flex-1 items-start  gap-x-5 gap-y-2">
                    <div v-for="(item, idx) in metaItems" :key="idx" class="flex flex-col items-end gap-0.5 text-right">
                        <div class="flex items-center gap-1">
                            <component :is="item.icon" class="h-3.5 w-3.5 shrink-0 text-primary" />
                            <span class="text-[11px] tracking-wider text-slate-400 uppercase">{{ item.label }}</span>
                        </div>
                        <span
                            class="min-w-15 border-b border-dashed border-slate-300 pb-0.5 text-sm font-semibold text-slate-700">
                            {{ item.value }}
                        </span>
                    </div>
                </div>
            </div>
            <!-- Badge de versão -->
            <div
                class="flex shrink-0 flex-col items-center justify-center rounded-lg bg-primary px-3 py-2 text-primary-foreground">
                <span class="text-[8px] leading-none tracking-wider uppercase opacity-80">{{
                    t('plannerate.print.labels.version') }}</span>
                <span class="mt-0.5 text-base leading-none font-black">V1.0</span>
            </div>
        </div>
    </div>
</template>

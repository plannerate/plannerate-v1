<script setup lang="ts">
import {
    ArrowRightIcon,
    CalendarDaysIcon,
    FileTextIcon,
    PackageIcon,
    StoreIcon,
    UserIcon,
} from 'lucide-vue-next';
import { useT } from '@/composables/useT';

interface GondolaMeta {
    location?: string;
    side?: string;
    planogram?: {
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
}

defineProps<Props>();

const { t } = useT();
</script>

<template>
    <div class="flex items-center gap-5">
        <div class="flex flex-col gap-0.5">
            <div class="flex items-center gap-1">
                <StoreIcon class="h-3 w-3 shrink-0 text-primary" />
                <span
                    class="text-[9px] tracking-wider text-slate-400 uppercase"
                    >{{ t('plannerate.print.labels.store') }}</span
                >
            </div>
            <span
                class="min-w-[60px] border-b border-dashed border-slate-300 pb-0.5 text-sm font-semibold text-slate-700 dark:text-slate-200"
            >
                {{ gondola.location || '—' }}
            </span>
        </div>
        <div class="flex flex-col gap-0.5">
            <div class="flex items-center gap-1">
                <FileTextIcon class="h-3 w-3 shrink-0 text-primary" />
                <span
                    class="text-[9px] tracking-wider text-slate-400 uppercase"
                    >{{ t('plannerate.print.labels.sector') }}</span
                >
            </div>
            <span
                class="min-w-[50px] border-b border-dashed border-slate-300 pb-0.5 text-xs font-semibold text-slate-700 dark:text-slate-200"
            >
                {{ gondola.side || '—' }}
            </span>
        </div>
        <div class="flex flex-col gap-0.5">
            <div class="flex items-center gap-1">
                <PackageIcon class="h-3 w-3 shrink-0 text-primary" />
                <span
                    class="text-[9px] tracking-wider text-slate-400 uppercase"
                    >{{ t('plannerate.print.labels.category') }}</span
                >
            </div>
            <span
                class="min-w-[80px] border-b border-dashed border-slate-300 pb-0.5 text-xs font-semibold text-slate-700 dark:text-slate-200"
            >
                {{ gondola.planogram?.category?.name || '—' }}
            </span>
        </div>
        <div class="flex flex-col gap-0.5">
            <div class="flex items-center gap-1">
                <CalendarDaysIcon class="h-3 w-3 shrink-0 text-primary" />
                <span
                    class="text-[9px] tracking-wider text-slate-400 uppercase"
                    >{{ t('plannerate.print.labels.publication') }}</span
                >
            </div>
            <span
                class="min-w-[75px] border-b border-dashed border-slate-300 pb-0.5 text-xs font-semibold text-slate-700 dark:text-slate-200"
            >
                {{ gondola.planogram?.start_date || '—' }}
            </span>
        </div>
        <div class="flex flex-col gap-0.5">
            <div class="flex items-center gap-1">
                <UserIcon class="h-3 w-3 shrink-0 text-primary" />
                <span
                    class="text-[9px] tracking-wider text-slate-400 uppercase"
                    >{{ t('plannerate.print.labels.responsible') }}</span
                >
            </div>
            <span
                class="min-w-[75px] border-b border-dashed border-slate-300 pb-0.5 text-xs font-semibold text-slate-700 dark:text-slate-200"
            >
                {{ responsavel || '—' }}
            </span>
        </div>
        <div class="flex flex-col gap-0.5">
            <div class="flex items-center gap-1">
                <ArrowRightIcon class="h-3 w-3 shrink-0 text-primary" />
                <span
                    class="text-[9px] tracking-wider text-slate-400 uppercase"
                    >{{ t('plannerate.print.labels.flow') }}</span
                >
            </div>
            <span
                class="min-w-[90px] border-b border-dashed border-slate-300 pb-0.5 text-xs font-semibold text-slate-700 dark:text-slate-200"
            >
                {{ flowLabel }}
            </span>
        </div>
        <div
            class="flex shrink-0 flex-col items-center justify-center rounded-lg bg-primary px-3 py-2 text-primary-foreground"
        >
            <span
                class="text-[8px] leading-none tracking-wider uppercase opacity-80"
                >{{ t('plannerate.print.labels.version') }}</span
            >
            <span class="mt-0.5 text-base leading-none font-black">V1.0</span>
        </div>
    </div>
</template>

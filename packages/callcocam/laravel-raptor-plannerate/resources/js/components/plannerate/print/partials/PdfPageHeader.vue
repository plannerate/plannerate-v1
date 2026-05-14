<script setup lang="ts">
import {
    ArrowRightIcon,
    CalendarDaysIcon,
    FileTextIcon,
    PackageIcon,
    StoreIcon,
    UserIcon,
} from 'lucide-vue-next'

interface GondolaMeta {
    location?: string
    side?: string
    planogram?: {
        start_date?: string
        description?: string
        category?: { name?: string } | null
    } | null
}

interface Props {
    gondola: GondolaMeta
    tenantName?: string
    responsavel?: string
    flowLabel: string
}

defineProps<Props>()
</script>

<template>
    <div class="flex items-center gap-5">
        <div class="flex flex-col gap-0.5">
            <div class="flex items-center gap-1">
                <StoreIcon class="w-3 h-3 text-primary shrink-0" />
                <span class="text-[9px] text-slate-400 uppercase tracking-wider">Loja</span>
            </div>
            <span
                class="text-sm font-semibold text-slate-700 dark:text-slate-200 border-b border-dashed border-slate-300 pb-0.5 min-w-[60px]">
                {{ gondola.location || '—' }}
            </span>
        </div>
        <div class="flex flex-col gap-0.5">
            <div class="flex items-center gap-1">
                <FileTextIcon class="w-3 h-3 text-primary shrink-0" />
                <span class="text-[9px] text-slate-400 uppercase tracking-wider">Setor</span>
            </div>
            <span
                class="text-xs font-semibold text-slate-700 dark:text-slate-200 border-b border-dashed border-slate-300 pb-0.5 min-w-[50px]">
                {{ gondola.side || '—' }}
            </span>
        </div>
        <div class="flex flex-col gap-0.5">
            <div class="flex items-center gap-1">
                <PackageIcon class="w-3 h-3 text-primary shrink-0" />
                <span class="text-[9px] text-slate-400 uppercase tracking-wider">Categoria</span>
            </div>
            <span
                class="text-xs font-semibold text-slate-700 dark:text-slate-200 border-b border-dashed border-slate-300 pb-0.5 min-w-[80px]">
                {{ gondola.planogram?.category?.name || '—' }}
            </span>
        </div>
        <div class="flex flex-col gap-0.5">
            <div class="flex items-center gap-1">
                <CalendarDaysIcon class="w-3 h-3 text-primary shrink-0" />
                <span class="text-[9px] text-slate-400 uppercase tracking-wider">Publicação</span>
            </div>
            <span
                class="text-xs font-semibold text-slate-700 dark:text-slate-200 border-b border-dashed border-slate-300 pb-0.5 min-w-[75px]">
                {{ gondola.planogram?.start_date || '—' }}
            </span>
        </div>
        <div class="flex flex-col gap-0.5">
            <div class="flex items-center gap-1">
                <UserIcon class="w-3 h-3 text-primary shrink-0" />
                <span class="text-[9px] text-slate-400 uppercase tracking-wider">Responsável</span>
            </div>
            <span
                class="text-xs font-semibold text-slate-700 dark:text-slate-200 border-b border-dashed border-slate-300 pb-0.5 min-w-[75px]">
                {{ responsavel || '—' }}
            </span>
        </div>
        <div class="flex flex-col gap-0.5">
            <div class="flex items-center gap-1">
                <ArrowRightIcon class="w-3 h-3 text-primary shrink-0" />
                <span class="text-[9px] text-slate-400 uppercase tracking-wider">Fluxo</span>
            </div>
            <span
                class="text-xs font-semibold text-slate-700 dark:text-slate-200 border-b border-dashed border-slate-300 pb-0.5 min-w-[90px]">
                {{ flowLabel }}
            </span>
        </div>
        <div
            class="flex flex-col items-center justify-center bg-primary text-primary-foreground rounded-lg px-3 py-2 shrink-0">
            <span class="text-[8px] uppercase tracking-wider leading-none opacity-80">Versão</span>
            <span class="text-base font-black leading-none mt-0.5">V1.0</span>
        </div>
    </div>
</template>

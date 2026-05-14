<script setup lang="ts">
import { useT } from '@/composables/useT'

interface Props {
    isLeftToRight: boolean
}

defineProps<Props>()
const { t } = useT()
</script>

<template>
    <div class="flex items-center justify-between px-6 py-2  border-t border-slate-100 dark:border-slate-800">
        <!-- Início -->
        <div
            class="flex items-center gap-2 text-sm font-semibold shrink-0"
            :class="isLeftToRight ? 'text-primary' : 'text-slate-400 dark:text-slate-500'"
        >
            <div
                class="flex items-center justify-center w-6 h-6 rounded-full text-xs font-black"
                :class="isLeftToRight ? 'bg-primary text-primary-foreground' : 'bg-slate-200 dark:bg-slate-700 text-slate-400'"
            >
                {{ isLeftToRight ? '★' : '☆' }}
            </div>
            <span>{{ isLeftToRight ? t('plannerate.indicator.start_flow') : t('plannerate.indicator.end') }}</span>
        </div>

        <!-- Linha central -->
        <div class="flex-1 mx-6 flex items-center gap-2">
            <div
                class="flex-1 h-px bg-linear-to-r"
                :class="isLeftToRight ? 'from-primary/60 to-primary/20' : 'from-slate-300 to-primary/20 dark:from-slate-600'"
            ></div>
            <span class="text-primary font-black">{{ isLeftToRight ? '→' : '←' }}</span>
            <span class="text-[9px] text-slate-400 uppercase tracking-widest">Fluxo da Gôndola</span>
            <span class="text-primary font-black">{{ isLeftToRight ? '→' : '←' }}</span>
            <div
                class="flex-1 h-px bg-linear-to-l"
                :class="isLeftToRight ? 'from-primary/60 to-primary/20' : 'from-slate-300 to-primary/20 dark:from-slate-600'"
            ></div>
        </div>

        <!-- Fim -->
        <div
            class="flex items-center gap-2 text-sm font-semibold shrink-0"
            :class="!isLeftToRight ? 'text-primary' : 'text-slate-400 dark:text-slate-500'"
        >
            <span>{{ !isLeftToRight ? t('plannerate.indicator.start_flow') : t('plannerate.indicator.end') }}</span>
            <div
                class="flex items-center justify-center w-6 h-6 rounded-full text-xs font-black"
                :class="!isLeftToRight ? 'bg-primary text-primary-foreground' : 'bg-slate-200 dark:bg-slate-700 text-slate-400'"
            >
                {{ !isLeftToRight ? '★' : '☆' }}
            </div>
        </div>
    </div>
</template>

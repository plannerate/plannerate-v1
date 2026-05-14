<script setup lang="ts">
import { useT } from '@/composables/useT';

interface Props {
    isLeftToRight: boolean;
}

defineProps<Props>();
const { t } = useT();
</script>

<template>
    <div
        class="flex items-center justify-between border-t border-slate-100 px-6 py-2 dark:border-slate-800"
    >
        <!-- Início -->
        <div
            class="flex shrink-0 items-center gap-2 text-sm font-semibold"
            :class="
                isLeftToRight
                    ? 'text-primary'
                    : 'text-slate-400 dark:text-slate-500'
            "
        >
            <div
                class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-black"
                :class="
                    isLeftToRight
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-slate-200 text-slate-400 dark:bg-slate-700'
                "
            >
                {{ isLeftToRight ? '★' : '☆' }}
            </div>
            <span>{{
                isLeftToRight
                    ? t('plannerate.indicator.start_flow')
                    : t('plannerate.indicator.end')
            }}</span>
        </div>

        <!-- Linha central -->
        <div class="mx-6 flex flex-1 items-center gap-2">
            <div
                class="h-px flex-1 bg-linear-to-r"
                :class="
                    isLeftToRight
                        ? 'from-primary/60 to-primary/20'
                        : 'from-slate-300 to-primary/20 dark:from-slate-600'
                "
            ></div>
            <span class="font-black text-primary">{{
                isLeftToRight ? '→' : '←'
            }}</span>
            <span class="text-[9px] tracking-widest text-slate-400 uppercase">{{
                t('plannerate.print.labels.gondola_flow')
            }}</span>
            <span class="font-black text-primary">{{
                isLeftToRight ? '→' : '←'
            }}</span>
            <div
                class="h-px flex-1 bg-linear-to-l"
                :class="
                    isLeftToRight
                        ? 'from-primary/60 to-primary/20'
                        : 'from-slate-300 to-primary/20 dark:from-slate-600'
                "
            ></div>
        </div>

        <!-- Fim -->
        <div
            class="flex shrink-0 items-center gap-2 text-sm font-semibold"
            :class="
                !isLeftToRight
                    ? 'text-primary'
                    : 'text-slate-400 dark:text-slate-500'
            "
        >
            <span>{{
                !isLeftToRight
                    ? t('plannerate.indicator.start_flow')
                    : t('plannerate.indicator.end')
            }}</span>
            <div
                class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-black"
                :class="
                    !isLeftToRight
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-slate-200 text-slate-400 dark:bg-slate-700'
                "
            >
                {{ !isLeftToRight ? '★' : '☆' }}
            </div>
        </div>
    </div>
</template>

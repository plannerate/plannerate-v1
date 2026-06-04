<template>
    <div class="flex items-center justify-between border-b border-border/50 px-2 py-1.5 sm:px-4 sm:py-2">
        <!-- Lado Esquerdo -->
        <div
            class="flex items-center gap-1 sm:gap-1.5 rounded-full px-2 py-1 sm:px-3 sm:py-1.5 text-xs font-medium shadow-sm transition-all duration-300"
            :class="
                isLeftToRight
                    ? 'bg-primary text-primary-foreground shadow-primary/30'
                    : 'bg-background/80 text-muted-foreground/50 ring-1 ring-border/40 backdrop-blur-sm'
            "
        >
            <Star
                class="size-3 sm:size-3.5 shrink-0 transition-all duration-300"
                :class="isLeftToRight ? 'text-primary-foreground' : 'text-muted-foreground/30'"
                :fill="isLeftToRight ? 'currentColor' : 'none'"
            />
            <span class="hidden sm:inline tracking-wide uppercase">{{
                isLeftToRight ? t('plannerate.indicator.start_flow') : t('plannerate.indicator.end')
            }}</span>
            <ArrowRight v-if="isLeftToRight" class="size-3 sm:size-3.5 animate-pulse" />
        </div>

        <!-- Linha de Fluxo Central -->
        <div class="mx-1 sm:mx-3 flex flex-1 items-center gap-1">
            <div class="h-px flex-1 bg-linear-to-r from-border/60 to-border/20" />
            <div
                class="flex size-4 sm:size-5 shrink-0 items-center justify-center rounded-full ring-1 transition-all duration-300"
                :class="
                    isLeftToRight
                        ? 'bg-primary/10 ring-primary/30 text-primary'
                        : 'bg-muted/60 ring-border/30 text-muted-foreground/40'
                "
            >
                <component :is="isLeftToRight ? ArrowRight : ArrowLeft" class="size-2.5 sm:size-3" />
            </div>
            <div class="h-px flex-1 bg-linear-to-r from-border/20 to-border/60" />
        </div>

        <!-- Lado Direito -->
        <div
            class="flex items-center gap-1 sm:gap-1.5 rounded-full px-2 py-1 sm:px-3 sm:py-1.5 text-xs font-medium shadow-sm transition-all duration-300"
            :class="
                !isLeftToRight
                    ? 'bg-primary text-primary-foreground shadow-primary/30'
                    : 'bg-background/80 text-muted-foreground/50 ring-1 ring-border/40 backdrop-blur-sm'
            "
        >
            <ArrowLeft v-if="!isLeftToRight" class="size-3 sm:size-3.5 animate-pulse" />
            <span class="hidden sm:inline tracking-wide uppercase">{{
                !isLeftToRight ? t('plannerate.indicator.start_flow') : t('plannerate.indicator.end')
            }}</span>
            <Star
                class="size-3 sm:size-3.5 shrink-0 transition-all duration-300"
                :class="!isLeftToRight ? 'text-primary-foreground' : 'text-muted-foreground/30'"
                :fill="!isLeftToRight ? 'currentColor' : 'none'"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
import { ArrowLeft, ArrowRight, Star } from 'lucide-vue-next';
import { useT } from '@/composables/useT';

interface Props {
    isLeftToRight: boolean;
}

defineProps<Props>();
const { t } = useT();
</script>

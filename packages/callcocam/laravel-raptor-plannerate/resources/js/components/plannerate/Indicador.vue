<template>
    <div
        class="absolute top-2 right-0 left-0 z-[40] flex items-center justify-between px-4"
    >
        <!-- Lado Esquerdo -->
        <div
            class="flex items-center gap-1.5 rounded px-2 py-1 text-xs transition-all"
            :class="
                isLeftToRight
                    ? 'bg-primary/5 font-medium text-primary'
                    : 'text-muted-foreground/60'
            "
        >
            <Star
                :class="
                    isLeftToRight ? 'text-primary' : 'text-muted-foreground/40'
                "
                class="size-3.5"
                :fill="isLeftToRight ? 'currentColor' : 'none'"
            />
            <span>{{
                isLeftToRight ? 'INÍCIO - Fluxo da Gôndola' : 'Fim'
            }}</span>
            <ArrowRight v-if="isLeftToRight" class="size-3 opacity-50" />
        </div>

        <!-- Botões Centrais -->

        <!-- Lado Direito -->
        <div
            class="flex items-center gap-1.5 rounded px-2 py-1 text-xs transition-all"
            :class="
                !isLeftToRight
                    ? 'bg-primary/5 font-medium text-primary'
                    : 'text-muted-foreground/60'
            "
        >
            <ArrowLeft v-if="!isLeftToRight" class="size-3 opacity-50" />
            <span>{{
                !isLeftToRight ? 'INÍCIO - Fluxo da Gôndola' : 'Fim'
            }}</span>
            <Star
                :class="
                    !isLeftToRight ? 'text-primary' : 'text-muted-foreground/40'
                "
                class="size-3.5"
                :fill="!isLeftToRight ? 'currentColor' : 'none'"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
import { ArrowLeft, ArrowRight, Star } from 'lucide-vue-next';

interface Props {
    isLeftToRight: boolean;
}

defineProps<Props>();
</script>

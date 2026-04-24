<template>
    <div class="rounded-lg border border-border bg-accent/40 p-2.5">
        <div class="flex items-center justify-between gap-2">
            <p class="text-[11px] font-semibold text-foreground">Ajustes rápidos</p>
            <span class="text-[10px] text-muted-foreground">
                {{ matchedSegmentsCount }} segmento(s)
            </span>
        </div>

        <div class="mt-2 flex items-center justify-between gap-2">
            <div class="text-xs text-muted-foreground">Frentes atuais</div>
            <div class="text-xs font-semibold text-foreground">
                {{ currentFronts ?? 0 }}
            </div>
        </div>

        <div class="mt-2 flex items-center justify-end gap-1">
            <button
                type="button"
                class="inline-flex size-7 items-center justify-center rounded-md border border-border bg-background text-foreground transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!hasPlacement || (currentFronts ?? 0) <= 1"
                @click="$emit('decrease')"
                aria-label="Diminuir frentes"
            >
                <Minus class="size-3.5" />
            </button>
            <button
                type="button"
                class="inline-flex size-7 items-center justify-center rounded-md border border-border bg-background text-foreground transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!hasPlacement"
                @click="$emit('increase')"
                aria-label="Aumentar frentes"
            >
                <Plus class="size-3.5" />
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Minus, Plus } from 'lucide-vue-next';

defineProps<{
    hasPlacement: boolean;
    currentFronts: number | null;
    matchedSegmentsCount: number;
}>();

defineEmits<{
    increase: [];
    decrease: [];
}>();
</script>

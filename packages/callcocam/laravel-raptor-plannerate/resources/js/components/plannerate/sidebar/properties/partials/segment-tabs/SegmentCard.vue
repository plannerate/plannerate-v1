<template>
    <!-- Card de seção com badge de ícone colorido + título, usado nas abas do produto -->
    <div
        class="rounded-xl border border-border bg-card"
        :class="dense ? 'space-y-2 p-3' : 'space-y-3 p-4'"
    >
        <div class="flex items-center gap-2">
            <div
                class="flex shrink-0 items-center justify-center rounded-lg"
                :class="[badgeClass, dense ? 'size-7' : 'size-8']"
            >
                <component :is="icon" :class="dense ? 'size-3.5' : 'size-4'" />
            </div>
            <h4
                class="font-semibold leading-tight text-foreground"
                :class="dense ? 'text-sm' : 'text-base'"
            >
                {{ title }}
            </h4>
        </div>
        <slot />
    </div>
</template>

<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';

interface Props {
    /** Componente de ícone (lucide-vue-next) exibido no badge */
    icon: Component;
    /** Título da seção */
    title: string;
    /** Cor do badge do ícone */
    color?: 'blue' | 'purple' | 'emerald';
    /** Versão compacta: reduz paddings, ícone e tamanho do título */
    dense?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    color: 'blue',
    dense: false,
});

/** Classe de cor do badge conforme a prop `color` */
const badgeClass = computed(() => {
    const map: Record<string, string> = {
        blue: 'bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400',
        purple: 'bg-purple-50 text-purple-600 dark:bg-purple-950/40 dark:text-purple-400',
        emerald: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400',
    };

    return map[props.color];
});
</script>

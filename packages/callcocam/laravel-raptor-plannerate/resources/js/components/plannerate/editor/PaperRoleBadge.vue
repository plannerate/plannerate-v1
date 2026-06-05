<template>
    <div
        v-if="role && isVisible"
        class="flex items-center justify-center rounded-lg px-1 py-0.5 text-[10px] font-bold shadow-md"
        :class="badgeClasses"
        :title="roleLabel"
    >
        {{ roleIcon }}
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { ProductRole } from '@/components/plannerate/analysis/paper/types';
import { usePaperAnalysis } from '@/composables/plannerate/analysis/usePaperAnalysis';

interface Props {
    role?: ProductRole;
}

const props = defineProps<Props>();

const { isVisible } = usePaperAnalysis();

/** Ícone emoji representando cada papel estratégico. */
const roleIcon = computed(() => {
    switch (props.role) {
        case 'leader':  return '⭐';
        case 'anchor':  return '⚓';
        case 'rising':  return '📈';
        case 'lagging': return '📉';
        default:        return '';
    }
});

/** Rótulo PT-BR para o tooltip. */
const roleLabel = computed(() => {
    switch (props.role) {
        case 'leader':  return 'Líder — alto share e alto crescimento';
        case 'anchor':  return 'Âncora — alto share, crescimento estável';
        case 'rising':  return 'Ascendente — crescimento acima da média';
        case 'lagging': return 'Retardatário — baixo share e baixo crescimento';
        default:        return '';
    }
});

/**
 * Classes de cor por papel estratégico.
 *   leader  → amarelo (estrela)
 *   anchor  → verde   (estável)
 *   rising  → azul    (crescendo)
 *   lagging → vermelho (declínio)
 */
const badgeClasses = computed(() => {
    switch (props.role) {
        case 'leader':  return 'bg-yellow-400 text-gray-900';
        case 'anchor':  return 'bg-green-500 text-white';
        case 'rising':  return 'bg-blue-500 text-white';
        case 'lagging': return 'bg-red-500 text-white';
        default:        return 'bg-gray-400 text-white';
    }
});
</script>

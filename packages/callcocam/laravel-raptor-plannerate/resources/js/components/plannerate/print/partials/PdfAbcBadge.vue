<template>
    <div
        v-if="classification && isVisible"
        class="absolute -top-0.5 -right-0.5 z-50 flex items-center justify-center rounded-full font-semibold border border-white/60"
        :class="badgeClasses"
        :style="{
            fontSize: `${Math.max(4 * scale, 7)}px`,
            width: `${Math.max(7 * scale, 13)}px`,
            height: `${Math.max(7 * scale, 13)}px`,
        }"
        :title="`Classificação ABC: ${classification}`"
    >
        {{ classification }}
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useAbcClassification } from '@/composables/plannerate/v3/useAbcClassification';

const { isVisible } = useAbcClassification();

interface Props {
    classification?: 'A' | 'B' | 'C';
    scale?: number;
}

const props = withDefaults(defineProps<Props>(), {
    scale: 1,
});

/**
 * Classes CSS baseadas na classificação ABC
 * - A (Verde): Alta performance - produtos premium
 * - B (Amarelo): Média performance - produtos intermediários  
 * - C (Vermelho): Baixa performance - produtos de cauda longa
 */
const badgeClasses = computed(() => {
    switch (props.classification) {
        case 'A':
            return 'bg-green-500/70 text-white';
        case 'B':
            return 'bg-yellow-400/70 text-gray-800';
        case 'C':
            return 'bg-red-500/70 text-white';
        default:
            return 'bg-gray-400 text-white';
    }
});
</script>

<template>
    <div
        v-if="classification && isVisible"
        class="flex items-center justify-center rounded-lg px-1 py-0.5 text-[10px] font-bold shadow-md"
        :class="badgeClasses"
        :title="t('plannerate.editor.abc_badge.title', { classification })"
    >
        {{ classification }}
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useAbcClassification } from '@/composables/plannerate/analysis/useAbcClassification';
import { useT } from '@/composables/useT';

interface Props {
    classification?: 'A' | 'B' | 'C';
}

const props = defineProps<Props>();

const { isVisible } = useAbcClassification();
const { t } = useT();

/**
 * Classes CSS baseadas na classificação ABC
 * - A (Verde): Alta performance - produtos premium
 * - B (Amarelo): Média performance - produtos intermediários
 * - C (Cinza): Baixa performance - produtos de cauda longa
 */
const badgeClasses = computed(() => {
    switch (props.classification) {
        case 'A':
            return 'bg-green-500 text-white';
        case 'B':
            return 'bg-yellow-500 text-gray-900';
        case 'C':
            return 'bg-red-500 text-white';
        default:
            return 'bg-red-500 text-white';
    }
});
</script> 

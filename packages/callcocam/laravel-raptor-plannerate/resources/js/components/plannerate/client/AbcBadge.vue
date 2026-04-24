<template>
    <div
        v-if="classification && isVisible"
        class="absolute -top-2 -right-2 z-30 flex items-center justify-center rounded-bl-md px-1 py-0.5 text-[10px] font-bold shadow-md rounded-lg"
        :class="badgeClasses"
        :title="`Performance ABC: Classe ${classification}`"
    >
        {{ classification }}
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useAbcClassification } from '@/composables/plannerate/v3/useAbcClassification';

interface Props {
    classification?: 'A' | 'B' | 'C' | null;
}

const props = defineProps<Props>();

const { isVisible } = useAbcClassification();

const badgeClasses = computed(() => {
    switch (props.classification) {
        case 'A':
            return 'bg-green-500 text-white';
        case 'B':
            return 'bg-yellow-500 text-gray-900';
        case 'C':
            return 'bg-red-500 text-white';
        default:
            return 'bg-gray-400 text-white';
    }
});
</script>


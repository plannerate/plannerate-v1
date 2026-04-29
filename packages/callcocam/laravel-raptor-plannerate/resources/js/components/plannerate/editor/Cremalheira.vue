<template>
    <div
        class="absolute top-0 bottom-0 border border-slate-600 bg-slate-700 dark:border-border dark:bg-muted"
        :class="{
            'left-0': side === 'left',
            'right-0': side === 'right',
        }"
        :style="{
            width: `${cremalheiraWidth}px`,
            zIndex: 30,
        }"
    >
        <!-- Uma coluna de furos centralizada -->
        <div
            v-for="(position, index) in holePositions"
            :key="`hole-${index}`"
            class="absolute border border-slate-500 bg-slate-400 dark:border-border dark:bg-muted-foreground/40"
            data-furo-cremalheira="true"
            :style="{
                width: `${holeWidth}px`,
                height: `${holeHeight}px`,
                top: `${position * scale}px`,
                left: '50%',
                transform: 'translateX(-50%)',
            }"
        />

        <!-- Base da cremalheira (sem furos) -->
        <div
            class="absolute bottom-0 left-0 w-full border-t border-slate-600 bg-slate-700 dark:border-border dark:bg-muted"
            data-base-cremalheira="true"
            :style="{
                height: `${baseHeight}px`,
            }"
        />
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import {
    DEFAULT_SECTION_FIELDS,
    toCamelCase,
} from '../../../composables/plannerate/useSectionFields';
import { calculateHolePositions } from '../../../composables/plannerate/useSectionHoles';
import type { Section } from '../../../types/planogram';

interface Props {
    section: Section;
    side?: 'left' | 'right';
    scale?: number;
}

const props = withDefaults(defineProps<Props>(), {
    side: 'left',
    scale: 3,
});

// Converte seção para camelCase e obtém valores padrão
const sectionCamel = computed(() => toCamelCase(props.section));

// Largura da cremalheira usando valores padrão do composable
const cremalheiraWidth = computed(() => {
    const rackWidth =
        sectionCamel.value.rackWidth ?? DEFAULT_SECTION_FIELDS.rackWidth;

    return rackWidth * props.scale;
});

// Altura da base usando valores padrão do composable
const baseHeight = computed(() => {
    const baseHeightValue =
        sectionCamel.value.baseHeight ?? DEFAULT_SECTION_FIELDS.baseHeight;

    return baseHeightValue * props.scale;
});

// Valores escalados usando valores padrão do composable
const holeHeight = computed(() => {
    const holeHeightValue =
        sectionCamel.value.holeHeight ?? DEFAULT_SECTION_FIELDS.holeHeight;

    return holeHeightValue * props.scale;
});

const holeWidth = computed(() => {
    const holeWidthValue =
        sectionCamel.value.holeWidth ?? DEFAULT_SECTION_FIELDS.holeWidth;

    return holeWidthValue * props.scale;
});

// Calcula posições dos furos usando composable
const holePositions = computed(() => calculateHolePositions(props.section));
</script>

<style scoped></style>

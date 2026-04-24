<template>
    <div class="flex">
        <Section
            v-for="(section, index) in sections"
            :key="section.id"
            :section="section"
            :scale="scale"
            :firstSection="index === 0"
            :lastSection="index === sections.length - 1"
        />
    </div>
</template>

<script setup lang="ts">
import type { Section as SectionType } from '../../../../types/planogram';
import Section from './Section.vue';

interface Props {
    sections: SectionType[];
    scale: number;
}

defineProps<Props>();
</script>

<style scoped></style>

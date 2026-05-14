<script setup lang="ts">
import { computed } from 'vue'
import type { Section } from '@/types/planogram'
import PdfSection from './PdfSection.vue'

interface Props {
    sections: Section[]
    localScale: number
    alignment: string
}

const props = defineProps<Props>()

const footHeight = computed(() => {
    const baseHeight = props.sections[0]?.base_height ?? 20
    return Math.round(baseHeight * props.localScale)
})
</script>

<template>
    <div class="flex-1 overflow-auto bg-slate-50 dark:bg-slate-800/50">
        <div
            class="w-max min-w-full"
            :style="{
                paddingTop: `${Math.ceil(localScale * 60)}px`,
                paddingBottom: `${Math.ceil(localScale * 40)}px`,
                paddingLeft: `${Math.ceil(localScale * 40)}px`,
                paddingRight: `${Math.ceil(localScale * 40)}px`,
            }"
        >
            <!-- Seções da gôndola -->
            <div class="flex flex-row items-end gap-0 w-max">
                <PdfSection
                    v-for="(section, index) in sections"
                    :key="section.id"
                    :section="section"
                    :scale-factor="localScale"
                    :alignment="alignment"
                    :index="index"
                    layout-direction="row"
                    :extra-height="0"
                    :data-section-id="section.id"
                />
            </div>

            
        </div>
    </div>
</template>

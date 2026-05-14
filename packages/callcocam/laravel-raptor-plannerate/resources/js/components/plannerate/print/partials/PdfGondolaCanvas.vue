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
    <div class="flex-1 px-6 pb-6 overflow-x-auto bg-slate-50 dark:bg-slate-800/50">
        <div
            class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 px-6 pb-0 w-max min-w-full"
            :style="{ paddingTop: `${Math.ceil(localScale * 50)}px` }"
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

            <!-- Pé da gôndola — barra única contínua -->
            <div
                class="w-full bg-slate-700 border-t-2 border-slate-600"
                :style="{ height: `${footHeight}px` }"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
import type { Section } from '@/types/planogram'
import PdfSection from './PdfSection.vue'

interface Props {
    sections: Section[]
    localScale: number
    alignment: string
}

defineProps<Props>()
</script>

<template>
    <div class="flex-1 px-6 pb-6 bg-slate-50 dark:bg-slate-800/50 overflow-x-auto">
        <div
            class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 px-6 pb-6 w-max min-w-full"
            :style="{ paddingTop: `${Math.ceil(localScale * 50)}px` }"
        >
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

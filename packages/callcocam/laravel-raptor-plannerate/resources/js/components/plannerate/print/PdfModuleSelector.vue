<script setup lang="ts">
import { Download, Eye } from 'lucide-vue-next'
import { ref, computed } from 'vue'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import type { Section } from '@/types/planogram'

interface Props {
    open: boolean
    sections: Section[]
}

interface Emits {
    (e: 'update:open', value: boolean): void
    (e: 'generate', data: { sectionIds: string[], autoDownload: boolean }): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const selectedSectionIds = ref<string[]>([])

// Inicializa com todos selecionados quando abrir o modal
const handleOpenChange = (value: boolean) => {
    if (value) {
        // Quando abrir, seleciona todos
        selectedSectionIds.value = props.sections.map(s => s.id)
    }

    emit('update:open', value)
}

const allSelected = computed(() => {
    return selectedSectionIds.value.length === props.sections.length
})

const someSelected = computed(() => {
    return selectedSectionIds.value.length > 0 && !allSelected.value
})

function isChecked(sectionId: string): boolean {
    return selectedSectionIds.value.includes(sectionId)
}

function toggleSection(sectionId: string) {
    const index = selectedSectionIds.value.indexOf(sectionId)

    if (index > -1) {
        selectedSectionIds.value.splice(index, 1)
    } else {
        selectedSectionIds.value.push(sectionId)
    }
}

function toggleAll() {
    if (allSelected.value) {
        selectedSectionIds.value = []
    } else {
        selectedSectionIds.value = props.sections.map(s => s.id)
    }
}

function handlePreview() {
    if (selectedSectionIds.value.length === 0) {
        alert('Selecione pelo menos um módulo')

        return
    }

    emit('generate', {
        sectionIds: [...selectedSectionIds.value],
        autoDownload: false
    })
}

function handleDownload() {
    if (selectedSectionIds.value.length === 0) {
        alert('Selecione pelo menos um módulo')

        return
    }

    emit('generate', {
        sectionIds: [...selectedSectionIds.value],
        autoDownload: true
    })
}
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="w-full md:max-w-2xl max-h-[80vh] flex flex-col z-[1000]">
            <DialogHeader>
                <DialogTitle>Selecionar Módulos para PDF</DialogTitle>
                <DialogDescription>
                    Escolha quais módulos deseja incluir no PDF.
                    {{ selectedSectionIds.length }} de {{ sections.length }} selecionados.
                </DialogDescription>
            </DialogHeader>

            <div class="flex-1 overflow-y-auto">
                <!-- Selecionar todos -->
                <div class="flex items-center gap-2 p-4 border-b bg-slate-50 sticky top-0">
                    <Label :for="'select-all'" class="text-sm font-medium cursor-pointer">
                        <Checkbox :id="'select-all'" :model-value="allSelected" :indeterminate="someSelected"
                            @update:model-value="toggleAll" />
                        {{ allSelected ? 'Desmarcar todos' : 'Selecionar todos' }}
                    </Label>
                </div>

                <!-- Lista de módulos -->
                <div class="divide-y grid grid-cols-1 gap-2 md:grid-cols-2">
                    <div v-for="(section) in sections" :key="section.id"
                        class="flex items-start gap-3 p-4 hover:bg-slate-50 transition-colors">
                        <Label :for="`section-${section.id}`"
                            class="text-sm font-medium cursor-pointer flex items-center ">
                            <Checkbox :id="`section-${section.id}`" :model-value="isChecked(section.id)"
                                @update:model-value="() => toggleSection(section.id)" />
                            <div class="flex flex-col">
                                <span> Módulo {{ section.ordering }}</span>
                                <p class="text-xs text-slate-500 mt-1">
                                    {{ section.shelves?.length || 0 }} prateleiras
                                </p>
                            </div>
                        </Label>
                    </div>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="handleOpenChange(false)">
                    Cancelar
                </Button>
                <Button variant="outline" @click="handlePreview" :disabled="selectedSectionIds.length === 0">
                    <Eye class="mr-2 h-4 w-4" />
                    Visualizar
                </Button>
                <Button @click="handleDownload" :disabled="selectedSectionIds.length === 0">
                    <Download class="mr-2 h-4 w-4" />
                    Baixar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

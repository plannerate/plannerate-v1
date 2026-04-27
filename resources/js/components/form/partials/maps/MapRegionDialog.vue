<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ isEditing ? 'Editar' : 'Nova' }} Área</DialogTitle>
                <DialogDescription>
                    Configure as propriedades desta área do mapa
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <div class="space-y-2">
                    <Label for="region-label">Nome/Identificação</Label>
                    <Input id="region-label" v-model="form.label" placeholder="Ex: Gôndola 01, Ilha Promoções..." />
                </div>

                <div class="space-y-2">
                    <Label>Cor</Label>
                    <div class="flex gap-2">
                        <button v-for="color in colorOptions" :key="color.value" type="button"
                            class="w-8 h-8 rounded-md border-2 transition-all"
                            :class="form.color === color.value ? 'border-foreground scale-110' : 'border-transparent'"
                            :style="{ backgroundColor: color.value.replace('0.3', '0.5') }"
                            @click.stop.prevent="form.color = color.value" />
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="region-type">Tipo</Label>
                    <Select v-model="form.type">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Selecione o tipo" />
                        </SelectTrigger>
                        <SelectContent class="z-[200] w-full">
                            <SelectItem value="gondola">Gôndola</SelectItem>
                            <SelectItem value="island">Ilha</SelectItem>
                            <SelectItem value="checkout">Checkout</SelectItem>
                            <SelectItem value="entrance">Entrada</SelectItem>
                            <SelectItem value="exit">Saída</SelectItem>
                            <SelectItem value="storage">Estoque</SelectItem>
                            <SelectItem value="other">Outro</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-2">
                        <Label for="region-width">Largura (px)</Label>
                        <Input id="region-width" v-model.number="form.width" type="number" min="20" step="1"
                            inputmode="numeric" />
                    </div>
                    <div class="space-y-2">
                        <Label for="region-height">Altura (px)</Label>
                        <Input id="region-height" v-model.number="form.height" type="number" min="20" step="1"
                            inputmode="numeric" />
                    </div>
                </div>
            </div>

            <DialogFooter class="flex-col sm:flex-row gap-2">
                <Button v-if="isEditing" type="button" variant="outline" @click="$emit('duplicate')">
                    <Copy class="h-4 w-4 mr-2" />
                    Duplicar
                </Button>
                <Button v-if="isEditing" type="button" variant="destructive" @click="$emit('delete')">
                    <Trash2 class="h-4 w-4 mr-2" />
                    Excluir
                </Button>
                <div class="flex-1" />
                <Button type="button" variant="outline" @click="$emit('close')">
                    Cancelar
                </Button>
                <Button type="button" @click="handleSave">
                    Salvar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { Copy, Trash2 } from 'lucide-vue-next'

interface Region {
    id: string
    type?: string | null
}

interface RegionForm {
    label: string
    type: string
    color: string
    gondola_id: string | null
    width: number
    height: number
}

interface Gondola {
    id: string
    name: string
}

interface Props {
    open: boolean
    isEditing: boolean
    initialForm: RegionForm
    gondolas: Gondola[]
    regions: Region[]
}

const props = defineProps<Props>()

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void
    (e: 'save', form: RegionForm): void
    (e: 'delete'): void
    (e: 'duplicate'): void
    (e: 'close'): void
}>()

const colorOptions = [
    { name: 'Azul', value: 'rgba(59, 130, 246, 0.3)' },
    { name: 'Verde', value: 'rgba(34, 197, 94, 0.3)' },
    { name: 'Amarelo', value: 'rgba(234, 179, 8, 0.3)' },
    { name: 'Vermelho', value: 'rgba(239, 68, 68, 0.3)' },
    { name: 'Roxo', value: 'rgba(168, 85, 247, 0.3)' },
    { name: 'Rosa', value: 'rgba(236, 72, 153, 0.3)' },
    { name: 'Laranja', value: 'rgba(249, 115, 22, 0.3)' },
    { name: 'Ciano', value: 'rgba(6, 182, 212, 0.3)' },
]

const form = ref<RegionForm>({ ...props.initialForm })

// Guarda o estado original ao abrir o dialog
const originalLabel = ref('')
const originalType = ref('')

// Gera label sugerido baseado no tipo
const generateSuggestedLabel = (type: string): string => {
    const prefixes: Record<string, string> = {
        gondola: 'G',
        island: 'I',
        checkout: 'CK',
        entrance: 'E',
        exit: 'S',
        storage: 'EST',
        other: 'A',
    }
    const prefix = prefixes[type] || 'A'
    const countOfType = props.regions.filter(r => r.type === type).length
    const nextNumber = String(countOfType + 1).padStart(2, '0')
    return `${prefix}-${nextNumber}`
}

watch(() => props.initialForm, (newForm) => {
    form.value = { ...newForm }
}, { deep: true })

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        form.value = { ...props.initialForm }
        // Salva o estado original ao abrir
        originalLabel.value = props.initialForm.label
        originalType.value = props.initialForm.type
    }
})

// Quando trocar o tipo, atualiza o label com nova sugestão
// Mas se voltar para o tipo original, restaura o nome original
watch(() => form.value.type, (newType, oldType) => {
    if (newType !== oldType) {
        if (newType === originalType.value) {
            // Voltou para o tipo original, restaura o nome original
            form.value.label = originalLabel.value
        } else {
            // Tipo diferente, gera nova sugestão
            form.value.label = generateSuggestedLabel(newType)
        }
    }
})

const handleOpenChange = (value: boolean) => {
    emit('update:open', value)
    if (!value) {
        emit('close')
    }
}

const handleSave = () => {
    emit('save', { ...form.value })
}
</script>
<template>
    <div
        class="absolute top-2 left-2 z-10 flex items-center gap-1 bg-background/90 backdrop-blur rounded-md p-1 shadow-sm">
        <Button type="button" variant="ghost" size="icon" class="h-8 w-8" @click="$emit('zoom-out')"
            title="Diminuir zoom">
            <Minus class="h-4 w-4" />
        </Button>
        <span class="text-xs font-medium min-w-[50px] text-center">{{ Math.round(zoom * 100) }}%</span>
        <Button type="button" variant="ghost" size="icon" class="h-8 w-8" @click="$emit('zoom-in')"
            title="Aumentar zoom">
            <Plus class="h-4 w-4" />
        </Button>
        <Separator orientation="vertical" class="h-6 mx-1" />
        <Button type="button" :variant="currentTool === 'select' ? 'secondary' : 'ghost'" size="icon" class="h-8 w-8"
            @click="$emit('tool-change', 'select')" title="Selecionar">
            <MousePointer class="h-4 w-4" />
        </Button>
        <Button type="button" :variant="currentTool === 'draw' && drawShape === 'rectangle' ? 'secondary' : 'ghost'"
            size="icon" class="h-8 w-8" @click="$emit('tool-change', 'draw'); $emit('shape-change', 'rectangle')"
            title="Desenhar retângulo">
            <Square class="h-4 w-4" />
        </Button>
        <Button type="button" :variant="currentTool === 'draw' && drawShape === 'circle' ? 'secondary' : 'ghost'"
            size="icon" class="h-8 w-8" @click="$emit('tool-change', 'draw'); $emit('shape-change', 'circle')"
            title="Desenhar círculo">
            <Circle class="h-4 w-4" />
        </Button>
        <Button type="button" :variant="currentTool === 'pan' ? 'secondary' : 'ghost'" size="icon" class="h-8 w-8"
            @click="$emit('tool-change', 'pan')" title="Mover mapa">
            <Move class="h-4 w-4" />
        </Button>
        <Separator orientation="vertical" class="h-6 mx-1" />
        <Button type="button" variant="ghost" size="icon" class="h-8 w-8" @click="$emit('reset-view')"
            title="Resetar visualização">
            <RotateCcw class="h-4 w-4" />
        </Button>
        <Button type="button" variant="ghost" size="icon" class="h-8 w-8" @click="$emit('change-image')"
            title="Trocar imagem">
            <ImageIcon class="h-4 w-4" />
        </Button>
    </div>
</template>

<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import {
    Plus,
    Minus,
    Move,
    MousePointer,
    Square,
    Circle,
    RotateCcw,
    Image as ImageIcon,
} from 'lucide-vue-next'

interface Props {
    zoom: number
    currentTool: 'select' | 'draw' | 'pan'
    drawShape: 'rectangle' | 'circle'
}

defineProps<Props>()

defineEmits<{
    (e: 'zoom-in'): void
    (e: 'zoom-out'): void
    (e: 'tool-change', tool: 'select' | 'draw' | 'pan'): void
    (e: 'shape-change', shape: 'rectangle' | 'circle'): void
    (e: 'reset-view'): void
    (e: 'change-image'): void
}>()
</script>
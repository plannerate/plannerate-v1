<template>
    <div class="border-2 border-dashed border-muted-foreground/25 rounded-lg p-8 text-center">
        <Map class="h-12 w-12 mx-auto text-muted-foreground/50 mb-4" />
        <p class="text-sm text-muted-foreground mb-4">
            Faça upload da planta baixa da loja para começar o mapeamento
        </p>
        <Button type="button" variant="outline" @click="$emit('upload')">
            <Upload class="h-4 w-4 mr-2" />
            Selecionar Imagem
        </Button>
    </div>
</template>

<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Map, Upload } from 'lucide-vue-next'

defineEmits<{
    (e: 'upload'): void
}>()
</script>
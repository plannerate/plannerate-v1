<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Copy, Pencil, ArrowUpDown, Plus, Trash2, Package } from 'lucide-vue-next';

defineProps<{
    storeUrl?: string;
    duplicateUrl?: string;
    updateUrl?: string;
    productsCount?: number;
}>();

const emit = defineEmits<{
    (e: 'add-subcategory'): void;
    (e: 'edit'): void;
    (e: 'duplicate'): void;
    (e: 'remove'): void;
    (e: 'open-products'): void;
}>();
</script>

<template>
    <div class="detail-section border-b border-border px-4 py-3">
        <div class="detail-section-title mb-2 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
            Ações
        </div>
        <div class="flex flex-col gap-1.5">
            <Button
                variant="outline"
                size="sm"
                class="h-8 justify-start gap-2 text-[11px] font-medium"
                title="Abrir janela de produtos (arrastar para esta categoria ou para outra janela)"
                @click="emit('open-products')"
            >
                <Package class="h-3.5 w-3.5 shrink-0" />
                Ver produtos ({{ productsCount ?? 0 }})
            </Button>
            <Button
                v-if="storeUrl"
                variant="outline"
                size="sm"
                class="h-8 justify-start gap-2 text-[11px] font-medium"
                @click="emit('add-subcategory')"
            >
                <Plus class="h-3.5 w-3.5 shrink-0" />
                Adicionar subcategoria
            </Button>
            <Button
                v-if="updateUrl"
                variant="outline"
                size="sm"
                class="h-8 justify-start gap-2 text-[11px] font-medium"
                @click="emit('edit')"
            >
                <Pencil class="h-3.5 w-3.5 shrink-0" />
                Editar nome / slug
            </Button>
            <p class="px-1 text-[10px] text-muted-foreground">
                Use o arraste nas colunas para mover de nível.
            </p>
            <Button
                variant="outline"
                size="sm"
                class="h-8 justify-start gap-2 text-[11px] font-medium"
                disabled
            >
                <ArrowUpDown class="h-3.5 w-3.5 shrink-0" />
                Mover de nível
            </Button>
            <Button
                v-if="duplicateUrl"
                variant="outline"
                size="sm"
                class="h-8 justify-start gap-2 text-[11px] font-medium"
                @click="emit('duplicate')"
            >
                <Copy class="h-3.5 w-3.5 shrink-0" />
                Duplicar
            </Button>
            <Button
                variant="destructive"
                size="sm"
                class="h-8 justify-start gap-2 text-[11px] font-medium"
                @click="emit('remove')"
            >
                <Trash2 class="h-3.5 w-3.5 shrink-0" />
                Remover categoria
            </Button>
        </div>
    </div>
</template>

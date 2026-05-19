<script setup lang="ts">
import type {
    PlanogramTemplateSlot,
    SlotProduct,
} from '@/components/planogram-templates/types';

const props = defineProps<{
    selectedSlot: PlanogramTemplateSlot | null;
    products: SlotProduct[];
    loading: boolean;
}>();
</script>

<template>
    <div class="rounded-lg border bg-card p-4 col-end-12 md:col-span-9 lg:col-span-8">
        <p class="mb-1 text-sm font-semibold">Produtos relacionados</p>
        <p class="mb-3 text-xs text-muted-foreground">
            {{
                props.selectedSlot
                    ? `Grouping: ${props.selectedSlot.grouping}`
                    : 'Selecione um slot para listar os produtos.'
            }}
        </p>

        <div v-if="props.loading" class="text-sm text-muted-foreground">
            Carregando produtos...
        </div>
        <div
            v-else-if="props.selectedSlot && props.products.length === 0"
            class="text-sm text-muted-foreground"
        >
            Nenhum produto encontrado para este grouping.
        </div>
        <div v-else-if="props.products.length > 0" class="space-y-2">
            <div
                v-for="product in props.products"
                :key="product.id"
                class="rounded-md border border-border px-3 py-2"
            >
                <p class="text-sm font-medium">{{ product.name }}</p>
                <p class="text-xs text-muted-foreground">
                    EAN: {{ product.ean || '-' }} · Marca: {{ product.brand || '-' }}
                </p>
            </div>
        </div>
    </div>
</template>

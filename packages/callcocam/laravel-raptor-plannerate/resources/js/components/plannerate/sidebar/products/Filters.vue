<template>
    <div class="flex flex-col gap-2 p-2">
        <div class="flex  gap-2 w-full">
            <!-- Mostrar produtos usados -->
            <label
                class="flex cursor-pointer items-center gap-2 text-sm text-muted-foreground"
            >
                <input
                    type="checkbox"
                    :checked="filters.showUsed"
                    @change="
                        emit('update:filters', {
                            ...filters,
                            showUsed: ($event.target as HTMLInputElement)
                                .checked,
                        })
                    "
                    :disabled="disabled"
                    class="size-4 rounded border-input text-primary focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                />
                Mostrar usados
            </label>

            <!-- Com dimensões -->
            <label
                class="flex cursor-pointer items-center gap-2 text-sm text-muted-foreground"
            >
                <input
                    type="checkbox"
                    :checked="filters.hasDimensions"
                    @change="
                        emit('update:filters', {
                            ...filters,
                            hasDimensions: ($event.target as HTMLInputElement)
                                .checked,
                        })
                    "
                    :disabled="disabled"
                    class="size-4 rounded border-input text-primary focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                />
                Com dimensões
            </label>
        </div>
    </div>
</template>

<script setup lang="ts">
import type { ProductFilters } from '@/composables/plannerate/useProductsPanel'; 

// Re-exporta o tipo para uso em outros componentes
export type { ProductFilters };

interface Props {
    filters: ProductFilters;
    disabled?: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
    'update:filters': [value: ProductFilters];
}>();
</script>

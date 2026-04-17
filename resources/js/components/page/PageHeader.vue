<script setup lang="ts">
interface Props {
    title: string;
    description?: string;
}

defineProps<Props>();
</script>

<template>
    <!-- Cabeçalho de página: título, descrição opcional e ações no lado direito -->
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0 space-y-0.5">
            <h1 class="text-2xl font-bold tracking-tight text-foreground truncate">
                {{ title }}
            </h1>
            <p v-if="description" class="text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>

        <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
            <!-- Botões de ação: criar, importar, exportar, etc. -->
            <slot name="actions" />
        </div>
    </div>
</template>

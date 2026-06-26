<script setup lang="ts" generic="T extends string | number | boolean">
/**
 * Alternador segmentado reutilizável (estilo "pill") para escolher entre
 * poucas opções mutuamente exclusivas — ex.: Editar/Visualizar, Sim/Não.
 * Substitui checkboxes/selects por um controle visual compacto.
 */
type SegmentedOption = {
    value: T;
    label: string;
};

defineProps<{
    /** Opções exibidas, na ordem desejada (geralmente 2). */
    options: SegmentedOption[];
    /** Rótulo opcional exibido acima do controle. */
    label?: string;
}>();

const model = defineModel<T>({ required: true });
</script>

<template>
    <div>
        <p v-if="label" class="mb-1 text-xs font-medium text-muted-foreground">
            {{ label }}
        </p>
        <div class="inline-flex h-9 items-center rounded-lg border border-input bg-background p-0.5">
            <button
                v-for="option in options"
                :key="String(option.value)"
                type="button"
                class="rounded-md px-3 py-1 text-xs font-medium transition"
                :class="model === option.value
                    ? 'bg-primary text-primary-foreground shadow-sm'
                    : 'text-muted-foreground hover:text-foreground'"
                @click="model = option.value"
            >
                {{ option.label }}
            </button>
        </div>
    </div>
</template>

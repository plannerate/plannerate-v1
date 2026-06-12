<script setup lang="ts">
/**
 * Editor de planograma — modo MANUAL (painel de produtos com drag & drop).
 *
 * Wrapper fino sobre PlanogramEditor (mode="manual"), mantido para
 * compatibilidade com os imports existentes (Plannerate.vue do app decide
 * entre Planogram e PlanogramAuto pelo generation_mode da gôndola).
 */
import PlanogramEditor from './PlanogramEditor.vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    record?: any;
    availableUsers?: Array<{ id: string; name: string }>;
    saveChangesRoute?: string;
    backRoute?: string;
    analysis?: {
        abc?: any;
        stock?: any;
    };
    permissions: {
        can_create_gondola: boolean;
        can_update_gondola: boolean;
        can_remove_gondola: boolean;
        can_autogenate_gondola: boolean;
        can_autogenate_gondola_ia: boolean;
    };
}

const props = defineProps<Props>();
</script>

<template>
    <PlanogramEditor mode="manual" v-bind="props" />
</template>

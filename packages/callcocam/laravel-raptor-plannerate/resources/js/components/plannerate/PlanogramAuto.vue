<script setup lang="ts">
/**
 * Editor de planograma — modos TEMPLATE/AUTOMÁTICO (painel de geração:
 * regerar/redistribuir/reordenar + overrides por categoria).
 *
 * Wrapper fino sobre PlanogramEditor (mode="generated"), mantido para
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
    <PlanogramEditor mode="generated" v-bind="props" />
</template>

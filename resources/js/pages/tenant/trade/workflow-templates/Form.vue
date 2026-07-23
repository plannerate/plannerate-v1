<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import WorkflowTemplatesFormPage from '@trade/pages/workflow-templates/Form.vue';
import type { TradeWorkflowTemplateFormProps } from '@trade/types';

const props = defineProps<TradeWorkflowTemplateFormProps>();
</script>

<template>
    <WorkflowTemplatesFormPage v-bind="props" />
</template>

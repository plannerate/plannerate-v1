<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import WorkflowTemplatesIndexPage from '@trade/pages/workflow-templates/Index.vue';
import type { TradeWorkflowTemplatesIndexProps } from '@trade/types';

const props = defineProps<TradeWorkflowTemplatesIndexProps>();
</script>

<template>
    <WorkflowTemplatesIndexPage v-bind="props" />
</template>

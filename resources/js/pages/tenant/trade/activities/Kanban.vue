<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import ActivitiesKanbanPage from '@trade/pages/activities/Kanban.vue';
import type { TradeActivityKanbanProps } from '@trade/types';

const props = defineProps<TradeActivityKanbanProps>();
</script>

<template>
    <ActivitiesKanbanPage v-bind="props" />
</template>

<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import ActivityTypesFormPage from '@trade/pages/activity-types/Form.vue';
import type { TradeActivityTypeFormProps } from '@trade/types';

const props = defineProps<TradeActivityTypeFormProps>();
</script>

<template>
    <ActivityTypesFormPage v-bind="props" />
</template>

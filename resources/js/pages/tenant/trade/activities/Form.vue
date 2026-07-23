<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import ActivitiesFormPage from '@trade/pages/activities/Form.vue';
import type { TradeActivityFormProps } from '@trade/types';

const props = defineProps<TradeActivityFormProps>();
</script>

<template>
    <ActivitiesFormPage v-bind="props" />
</template>

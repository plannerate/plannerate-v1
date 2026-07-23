<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import ActivitiesShowPage from '@trade/pages/activities/Show.vue';
import type { TradeActivityShowProps } from '@trade/types';

const props = defineProps<TradeActivityShowProps>();
</script>

<template>
    <ActivitiesShowPage v-bind="props" />
</template>

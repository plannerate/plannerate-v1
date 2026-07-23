<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import MyActivitiesShowPage from '@trade/pages/my-activities/Show.vue';
import type { TradeMyActivityShowProps } from '@trade/types';

const props = defineProps<TradeMyActivityShowProps>();
</script>

<template>
    <MyActivitiesShowPage v-bind="props" />
</template>

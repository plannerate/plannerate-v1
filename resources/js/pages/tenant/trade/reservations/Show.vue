<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import ReservationsShowPage from '@trade/pages/reservations/Show.vue';
import type { TradeReservationShowProps } from '@trade/types';

const props = defineProps<TradeReservationShowProps>();
</script>

<template>
    <ReservationsShowPage v-bind="props" />
</template>

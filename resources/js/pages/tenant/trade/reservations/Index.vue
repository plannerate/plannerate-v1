<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import ReservationsIndexPage from '@trade/pages/reservations/Index.vue';
import type { TradeReservationsIndexProps } from '@trade/types';

const props = defineProps<TradeReservationsIndexProps>();
</script>

<template>
    <ReservationsIndexPage v-bind="props" />
</template>

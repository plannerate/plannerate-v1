<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import ReservationsFormPage from '@trade/pages/reservations/Form.vue';
import type { TradeReservationFormProps } from '@trade/types';

const props = defineProps<TradeReservationFormProps>();
</script>

<template>
    <ReservationsFormPage v-bind="props" />
</template>

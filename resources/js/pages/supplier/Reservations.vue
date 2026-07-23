<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui.
 */
import SupplierReservationsPage from '@trade/pages/supplier/Reservations.vue';
import type { TradeSupplierReservationsProps } from '@trade/types';

const props = defineProps<TradeSupplierReservationsProps>();
</script>

<template>
    <SupplierReservationsPage v-bind="props" />
</template>

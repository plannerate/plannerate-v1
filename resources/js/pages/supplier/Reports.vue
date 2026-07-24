<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui.
 */
import SupplierReportsPage from '@trade/pages/supplier/Reports.vue';
import type { TradeSupplierReportsProps } from '@trade/types';

const props = defineProps<TradeSupplierReportsProps>();
</script>

<template>
    <SupplierReportsPage v-bind="props" />
</template>

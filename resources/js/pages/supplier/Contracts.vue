<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui.
 */
import SupplierContractsPage from '@trade/pages/supplier/Contracts.vue';
import type { TradeSupplierContractsProps } from '@trade/types';

const props = defineProps<TradeSupplierContractsProps>();
</script>

<template>
    <SupplierContractsPage v-bind="props" />
</template>

<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui.
 */
import SupplierMapPage from '@trade/pages/supplier/Map.vue';
import type { TradeSupplierMapProps } from '@trade/types';

const props = defineProps<TradeSupplierMapProps>();
</script>

<template>
    <SupplierMapPage v-bind="props" />
</template>

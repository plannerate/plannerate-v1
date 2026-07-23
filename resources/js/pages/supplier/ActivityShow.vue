<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui.
 */
import SupplierActivityShowPage from '@trade/pages/supplier/ActivityShow.vue';
import type { TradeSupplierActivityShowProps } from '@trade/types';

const props = defineProps<TradeSupplierActivityShowProps>();
</script>

<template>
    <SupplierActivityShowPage v-bind="props" />
</template>

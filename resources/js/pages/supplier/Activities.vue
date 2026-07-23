<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui.
 */
import SupplierActivitiesPage from '@trade/pages/supplier/Activities.vue';
import type { TradeSupplierActivitiesProps } from '@trade/types';

const props = defineProps<TradeSupplierActivitiesProps>();
</script>

<template>
    <SupplierActivitiesPage v-bind="props" />
</template>

<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui.
 */
import SupplierIntentionsPage from '@trade/pages/supplier/Intentions.vue';
import type { TradeSupplierIntentionsProps } from '@trade/types';

const props = defineProps<TradeSupplierIntentionsProps>();
</script>

<template>
    <SupplierIntentionsPage v-bind="props" />
</template>

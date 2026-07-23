<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import PurchaseIntentionsIndexPage from '@trade/pages/purchase-intentions/Index.vue';
import type { TradePurchaseIntentionsIndexProps } from '@trade/types';

const props = defineProps<TradePurchaseIntentionsIndexProps>();
</script>

<template>
    <PurchaseIntentionsIndexPage v-bind="props" />
</template>

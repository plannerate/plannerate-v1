<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import SpaceAnalyticsPage from '@trade/pages/spaces/Analytics.vue';
import type { TradeSpaceAnalyticsProps } from '@trade/types';

const props = defineProps<TradeSpaceAnalyticsProps>();
</script>

<template>
    <SpaceAnalyticsPage v-bind="props" />
</template>

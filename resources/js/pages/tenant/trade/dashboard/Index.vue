<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import TradeDashboardPage from '@trade/pages/dashboard/Index.vue';
import type { TradeDashboardProps } from '@trade/types';

const props = defineProps<TradeDashboardProps>();
</script>

<template>
    <TradeDashboardPage v-bind="props" />
</template>

<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import ContractsShowPage from '@trade/pages/contracts/Show.vue';
import type { TradeContractShowProps } from '@trade/types';

const props = defineProps<TradeContractShowProps>();
</script>

<template>
    <ContractsShowPage v-bind="props" />
</template>

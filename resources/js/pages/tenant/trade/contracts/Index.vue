<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import ContractsIndexPage from '@trade/pages/contracts/Index.vue';
import type { TradeContractsIndexProps } from '@trade/types';

const props = defineProps<TradeContractsIndexProps>();
</script>

<template>
    <ContractsIndexPage v-bind="props" />
</template>

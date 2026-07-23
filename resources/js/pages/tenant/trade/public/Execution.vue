<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import PublicExecutionPage from '@trade/pages/public/Execution.vue';
import type { TradePublicExecutionProps } from '@trade/types';

const props = defineProps<TradePublicExecutionProps>();
</script>

<template>
    <PublicExecutionPage v-bind="props" />
</template>

<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import ContractsFormPage from '@trade/pages/contracts/Form.vue';
import type { TradeContractFormProps } from '@trade/types';

const props = defineProps<TradeContractFormProps>();
</script>

<template>
    <ContractsFormPage v-bind="props" />
</template>

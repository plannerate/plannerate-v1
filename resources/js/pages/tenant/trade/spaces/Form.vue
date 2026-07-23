<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import SpacesFormPage from '@trade/pages/spaces/Form.vue';
import type { TradeSpaceFormProps } from '@trade/types';

const props = defineProps<TradeSpaceFormProps>();
</script>

<template>
    <SpacesFormPage v-bind="props" />
</template>

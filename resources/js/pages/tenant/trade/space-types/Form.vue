<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import SpaceTypesFormPage from '@trade/pages/space-types/Form.vue';
import type { TradeSpaceTypeFormProps } from '@trade/types';

const props = defineProps<TradeSpaceTypeFormProps>();
</script>

<template>
    <SpaceTypesFormPage v-bind="props" />
</template>

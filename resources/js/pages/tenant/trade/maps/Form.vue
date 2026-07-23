<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import MapsFormPage from '@trade/pages/maps/Form.vue';
import type { TradeMapFormProps } from '@trade/types';

const props = defineProps<TradeMapFormProps>();
</script>

<template>
    <MapsFormPage v-bind="props" />
</template>

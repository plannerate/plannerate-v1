<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import SpaceShowPage from '@trade/pages/spaces/Show.vue';
import type { TradeSpaceShowProps } from '@trade/types';

const props = defineProps<TradeSpaceShowProps>();
</script>

<template>
    <SpaceShowPage v-bind="props" />
</template>

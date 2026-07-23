<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import SpacesIndexPage from '@trade/pages/spaces/Index.vue';
import type { TradeSpacesIndexProps } from '@trade/types';

const props = defineProps<TradeSpacesIndexProps>();
</script>

<template>
    <SpacesIndexPage v-bind="props" />
</template>

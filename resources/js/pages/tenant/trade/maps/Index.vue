<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import MapsIndexPage from '@trade/pages/maps/Index.vue';
import type { TradeMapsIndexProps } from '@trade/types';

const props = defineProps<TradeMapsIndexProps>();
</script>

<template>
    <MapsIndexPage v-bind="props" />
</template>

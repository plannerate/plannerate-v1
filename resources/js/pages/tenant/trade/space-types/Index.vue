<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import SpaceTypesIndexPage from '@trade/pages/space-types/Index.vue';
import type { TradeSpaceTypesIndexProps } from '@trade/types';

const props = defineProps<TradeSpaceTypesIndexProps>();
</script>

<template>
    <SpaceTypesIndexPage v-bind="props" />
</template>

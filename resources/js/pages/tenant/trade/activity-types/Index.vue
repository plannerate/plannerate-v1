<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import ActivityTypesIndexPage from '@trade/pages/activity-types/Index.vue';
import type { TradeActivityTypesIndexProps } from '@trade/types';

const props = defineProps<TradeActivityTypesIndexProps>();
</script>

<template>
    <ActivityTypesIndexPage v-bind="props" />
</template>

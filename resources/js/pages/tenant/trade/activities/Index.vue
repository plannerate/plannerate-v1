<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import ActivitiesIndexPage from '@trade/pages/activities/Index.vue';
import type { TradeActivitiesIndexProps } from '@trade/types';

const props = defineProps<TradeActivitiesIndexProps>();
</script>

<template>
    <ActivitiesIndexPage v-bind="props" />
</template>

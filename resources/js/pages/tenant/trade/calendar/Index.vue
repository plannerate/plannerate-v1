<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import CalendarIndexPage from '@trade/pages/calendar/Index.vue';
import type { TradeCalendarProps } from '@trade/types';

const props = defineProps<TradeCalendarProps>();
</script>

<template>
    <CalendarIndexPage v-bind="props" />
</template>

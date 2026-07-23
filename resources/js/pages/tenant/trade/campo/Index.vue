<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import CampoIndexPage from '@trade/pages/campo/Index.vue';
import type { TradeCampoIndexProps } from '@trade/types';

const props = defineProps<TradeCampoIndexProps>();
</script>

<template>
    <CampoIndexPage v-bind="props" />
</template>

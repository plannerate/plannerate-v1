<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui.
 */
import ProviderLinksPage from '@trade/pages/providers/Links.vue';
import type { TradeProviderLinksProps } from '@trade/types';

const props = defineProps<TradeProviderLinksProps>();
</script>

<template>
    <ProviderLinksPage v-bind="props" />
</template>

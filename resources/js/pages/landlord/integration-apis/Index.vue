<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan integrations:publish-pages`.
 */
import IntegrationApisIndexPage from '@integrations/pages/IntegrationApisIndex.vue';
import type { IntegrationApisIndexProps } from '@integrations/types';

const props = defineProps<IntegrationApisIndexProps>();
</script>

<template>
    <IntegrationApisIndexPage v-bind="props" />
</template>

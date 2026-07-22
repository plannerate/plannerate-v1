<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan integrations:publish-pages`.
 */
import IntegrationApisFormPage from '@integrations/pages/IntegrationApisForm.vue';
import type { IntegrationApisFormProps } from '@integrations/types';

const props = defineProps<IntegrationApisFormProps>();
</script>

<template>
    <IntegrationApisFormPage v-bind="props" />
</template>

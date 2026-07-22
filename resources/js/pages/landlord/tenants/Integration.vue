<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan integrations:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import TenantIntegrationPage from '@integrations/pages/TenantIntegration.vue';
import type { TenantIntegrationProps } from '@integrations/types';

const props = defineProps<TenantIntegrationProps>();
</script>

<template>
    <TenantIntegrationPage v-bind="props" />
</template>

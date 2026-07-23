<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui.
 */
import SupplierPanelPage from '@trade/pages/supplier/Panel.vue';
import type { TradeSupplierPanelProps } from '@trade/types';

const props = defineProps<TradeSupplierPanelProps>();
</script>

<template>
    <SupplierPanelPage v-bind="props" />
</template>

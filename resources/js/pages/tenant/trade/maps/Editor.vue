<script setup lang="ts">
/**
 * Casca: o código real vive no pacote e chega por `composer update`.
 * Publicada por `php artisan trade:publish-pages`. Não edite aqui —
 * edite no pacote, ou use `--full` para assumir as telas completas.
 */
import MapsEditorPage from '@trade/pages/maps/Editor.vue';
import type { TradeMapEditorProps } from '@trade/types';

const props = defineProps<TradeMapEditorProps>();
</script>

<template>
    <MapsEditorPage v-bind="props" />
</template>

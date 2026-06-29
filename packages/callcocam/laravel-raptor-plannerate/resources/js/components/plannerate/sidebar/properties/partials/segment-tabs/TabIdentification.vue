<template>
    <div class="space-y-4">
        <!-- Cabeçalho da aba -->
        <div>
            <h3 class="text-xl font-bold leading-tight text-foreground">
                {{ t('plannerate.sidebar.segment_details.headers.identification_title') }}
            </h3>
            <p class="text-sm text-muted-foreground">
                {{ t('plannerate.sidebar.segment_details.headers.identification_subtitle') }}
            </p>
        </div>

        <!-- Card: imagem + nome + códigos -->
        <div class="space-y-4 rounded-xl border border-border bg-card p-4">
            <div class="flex justify-center">
                <img
                    v-if="!isFallback"
                    :src="product?.image_url"
                    :alt="product?.name"
                    class="h-32 w-32 rounded-md border object-contain"
                />
                <div
                    v-else
                    class="flex h-32 w-32 items-center justify-center rounded-md border bg-muted"
                >
                    <span class="text-xs text-muted-foreground">
                        {{ t('plannerate.sidebar.product_image_card.no_image') }}
                    </span>
                </div>
            </div>

            <p class="text-center text-base font-bold leading-snug text-foreground">
                {{ product?.name || '—' }}
            </p>

            <div class="grid grid-cols-2 gap-3 rounded-lg border border-border p-3">
                <div class="space-y-1">
                    <p class="text-xs text-muted-foreground">
                        {{ t('plannerate.sidebar.segment_details.identification.internal_code') }}
                    </p>
                    <p class="font-mono text-base font-semibold text-foreground">
                        {{ product?.codigo_erp || '—' }}
                    </p>
                </div>
                <div class="space-y-1">
                    <p class="text-xs text-muted-foreground">
                        {{ t('plannerate.sidebar.segment_details.identification.ean') }}
                    </p>
                    <p class="font-mono text-base font-semibold text-foreground">
                        {{ product?.ean || '—' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Card: Resumo Rápido -->
        <SegmentCard
            :icon="Tag"
            color="purple"
            :title="t('plannerate.sidebar.segment_details.cards.quick_summary')"
        >
            <div class="grid grid-cols-2 gap-2">
                <div
                    v-for="field in summaryFields"
                    :key="field.key"
                    class="space-y-1 rounded-lg border border-border p-3"
                >
                    <p class="text-xs text-muted-foreground">{{ field.label }}</p>
                    <p class="truncate text-sm font-semibold text-foreground">
                        {{ field.value || '—' }}
                    </p>
                </div>
            </div>
        </SegmentCard>

        <!-- Card: Dados Adicionais -->
        <SegmentCard
            :icon="List"
            color="blue"
            :title="t('plannerate.sidebar.segment_details.additional_data.title')"
        >
            <div class="divide-y divide-border/60">
                <div
                    v-for="field in additionalFields"
                    :key="field.key"
                    class="flex items-center justify-between gap-2 py-2 text-sm"
                >
                    <span class="text-muted-foreground">{{ field.label }}</span>
                    <span class="text-right font-medium text-foreground">
                        {{ field.value || '—' }}
                    </span>
                </div>
            </div>
        </SegmentCard>
    </div>
</template>

<script setup lang="ts">
import { List, Tag } from 'lucide-vue-next';
import { computed } from 'vue';
import { useT } from '@/composables/useT';
import type { Product } from '@/types/planogram';
import SegmentCard from './SegmentCard.vue';

interface Props {
    /** Produto do segmento selecionado */
    product?: Product | null;
}

const props = defineProps<Props>();
const { t } = useT();

/**
 * Indica se o produto não tem imagem própria (usa fallback).
 */
const isFallback = computed(() =>
    !props.product?.image_url || props.product.image_url.includes('fall4.jpg'),
);

/**
 * Campos do resumo rápido — atributos principais exibidos em destaque.
 */
const summaryFields = computed(() => [
    { key: 'brand', label: t('plannerate.sidebar.segment_details.additional_data.brand'), value: props.product?.brand },
    { key: 'subbrand', label: t('plannerate.sidebar.segment_details.additional_data.subbrand'), value: props.product?.subbrand },
    { key: 'packaging_type', label: t('plannerate.sidebar.segment_details.additional_data.packaging_type'), value: props.product?.packaging_type },
    { key: 'measurement_unit', label: t('plannerate.sidebar.segment_details.additional_data.measurement_unit'), value: props.product?.measurement_unit },
]);

/**
 * Campos de dados adicionais mapeados para exibição.
 * Campos não preenchidos exibem "—".
 */
const additionalFields = computed(() => [
    { key: 'type', label: t('plannerate.sidebar.segment_details.additional_data.type'), value: props.product?.type },
    { key: 'reference', label: t('plannerate.sidebar.segment_details.additional_data.reference'), value: props.product?.reference },
    { key: 'color', label: t('plannerate.sidebar.segment_details.additional_data.color'), value: props.product?.color },
    { key: 'flavor', label: t('plannerate.sidebar.segment_details.additional_data.flavor'), value: props.product?.flavor },
    { key: 'fragrance', label: t('plannerate.sidebar.segment_details.additional_data.fragrance'), value: props.product?.fragrance },
    { key: 'packaging_content', label: t('plannerate.sidebar.segment_details.additional_data.packaging_content'), value: props.product?.packaging_content },
]);
</script>

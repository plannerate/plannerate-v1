<template>
    <div class="space-y-4">
        <!-- Imagem principal do produto -->
        <div class="flex justify-center">
            <img v-if="!isFallback" :src="product?.image_url" :alt="product?.name"
                class="h-28 w-28 rounded-md border object-contain" />
            <div v-else class="flex h-28 w-28 items-center justify-center rounded-md border bg-muted">
                <span class="text-xs text-muted-foreground">
                    {{ t('plannerate.sidebar.product_image_card.no_image') }}
                </span>
            </div>
        </div>

        <Separator />

        <!-- Campos de identificação -->
        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <!-- Código Interno -->
                <div class="space-y-1">
                    <p class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                        {{ t('plannerate.sidebar.segment_details.identification.internal_code') }}
                    </p>
                    <p class="font-mono text-sm">{{ product?.codigo_erp || '—' }}</p>
                </div>
                <!-- EAN -->
                <div class="space-y-1">
                    <p class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                        {{ t('plannerate.sidebar.segment_details.identification.ean') }}
                    </p>
                    <p class="font-mono text-sm">{{ product?.ean || '—' }}</p>
                </div>
            </div>
            <!-- Descrição / Nome -->
            <div class="space-y-1">
                <p class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                    {{ t('plannerate.sidebar.segment_details.identification.description') }}
                </p>
                <p class="text-sm font-medium leading-snug">{{ product?.name || '—' }}</p>
            </div>
        </div>

        <Separator />

        <!-- Seção: Dados Adicionais (colapsável) -->
        <Collapsible v-model:open="additionalOpen">
            <CollapsibleTrigger class="flex w-full items-center justify-between text-sm font-semibold text-foreground">
                {{ t('plannerate.sidebar.segment_details.additional_data.title') }}
                <ChevronDown
                    class="size-4 text-muted-foreground transition-transform duration-200"
                    :class="{ 'rotate-180': additionalOpen }"
                />
            </CollapsibleTrigger>
            <CollapsibleContent class="mt-3 space-y-2">
                <div
                    v-for="field in additionalFields"
                    :key="field.key"
                    class="flex items-center justify-between rounded-md bg-muted/30 px-3 py-1.5"
                >
                    <span class="text-xs text-muted-foreground">{{ field.label }}</span>
                    <span class="text-xs font-medium text-foreground">
                        {{ field.value || '—' }}
                    </span>
                </div>
            </CollapsibleContent>
        </Collapsible>
    </div>
</template>

<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Separator } from '@/components/ui/separator';
import { useT } from '@/composables/useT';
import type { Product } from '@/types/planogram';

interface Props {
    /** Produto do segmento selecionado */
    product?: Product | null;
}

const props = defineProps<Props>();
const { t } = useT();

const additionalOpen = ref(false);

/**
 * Indica se o produto não tem imagem própria (usa fallback).
 */
const isFallback = computed(() =>
    !props.product?.image_url || props.product.image_url.includes('fall4.jpg'),
);

/**
 * Campos de dados adicionais mapeados para exibição.
 * Campos não preenchidos exibem "—".
 */
const additionalFields = computed(() => [
    { key: 'type', label: t('plannerate.sidebar.segment_details.additional_data.type'), value: props.product?.type },
    { key: 'reference', label: t('plannerate.sidebar.segment_details.additional_data.reference'), value: props.product?.reference },
    { key: 'brand', label: t('plannerate.sidebar.segment_details.additional_data.brand'), value: props.product?.brand },
    { key: 'subbrand', label: t('plannerate.sidebar.segment_details.additional_data.subbrand'), value: props.product?.subbrand },
    { key: 'color', label: t('plannerate.sidebar.segment_details.additional_data.color'), value: props.product?.color },
    { key: 'flavor', label: t('plannerate.sidebar.segment_details.additional_data.flavor'), value: props.product?.flavor },
    { key: 'fragrance', label: t('plannerate.sidebar.segment_details.additional_data.fragrance'), value: props.product?.fragrance },
    { key: 'packaging_type', label: t('plannerate.sidebar.segment_details.additional_data.packaging_type'), value: props.product?.packaging_type },
    { key: 'packaging_content', label: t('plannerate.sidebar.segment_details.additional_data.packaging_content'), value: props.product?.packaging_content },
    { key: 'measurement_unit', label: t('plannerate.sidebar.segment_details.additional_data.measurement_unit'), value: props.product?.measurement_unit },
]);
</script>

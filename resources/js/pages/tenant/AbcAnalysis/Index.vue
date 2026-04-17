<script setup lang="ts">
import AbcAnalysisComponent from '@/components/plannerate/AbcAnalysis.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { AbcResult } from '@/components/plannerate/analysis/abc/types';

interface InitialData {
    results?: AbcResult[];
    category?: {
        id: string;
        name: string;
    } | null;
    filters?: {
        table_type: string;
        client_id?: string;
        store_id?: string;
        date_from?: string;
        date_to?: string;
        month_from?: string;
        month_to?: string;
    };
    weights?: {
        peso_qtde: number;
        peso_valor: number;
        peso_margem: number;
    };
    cuts?: {
        corte_a: number;
        corte_b: number;
    };
}

interface Props {
    initialData?: InitialData | null;
}

withDefaults(defineProps<Props>(), {
    initialData: null,
});

const page = usePage();
const errors = computed(() => page.props.errors || {});
</script>

<template>
    <Head title="Análise ABC" />

    <AppLayout>
        <div class="container mx-auto py-6">
            <AbcAnalysisComponent :initial-data="initialData" :errors="errors" />
        </div>
    </AppLayout>
</template>


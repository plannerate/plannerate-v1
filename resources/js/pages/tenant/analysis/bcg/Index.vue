<script setup lang="ts">
import BcgMatrixComponent from '@/components/plannerate/analysis/BcgMatrix.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { BcgResult, BcgSummary } from '@/components/plannerate/analysis/bcg/types';

interface InitialData {
    results?: BcgResult[];
    summary?: BcgSummary | null;
    category?: {
        id: string;
        name: string;
    } | null;
    category_id?: string;
    eans?: string[];
    filters?: {
        table_type: string;
        client_id?: string;
        store_id?: string;
        date_from?: string;
        date_to?: string;
        month_from?: string;
        month_to?: string;
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
    <Head title="Matriz BCG" />

    <AppLayout>
        <div class="container mx-auto py-6">
            <BcgMatrixComponent :initial-data="initialData" :errors="errors" />
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import {
    AlertCircle,
    Calendar,
    Settings,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type { BcgResult } from '@/components/plannerate/analysis/bcg/types';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useT } from '@/composables/useT';
import BcgParamsModal from './BcgParamsModal.vue';
import BcgResultsList from './BcgResultsList.vue';

interface InitialData {
    results?: BcgResult[];
    gondola?: {
        id: string;
        name: string;
        slug: string;
    };
    planogram?: {
        id: string;
        name: string;
        tenant_id?: string;
        start_date?: string;
        end_date?: string;
    };
    filters?: {
        table_type: string;
        date_from?: string;
        date_to?: string;
        start_month?: string;
        end_month?: string;
    };
    parameters?: {
        growth_threshold: number;
    };
}

interface Props {
    initialData?: InitialData | null;
    errors?: Record<string, string>;
}

const props = withDefaults(defineProps<Props>(), {
    initialData: null,
    errors: () => ({}),
});

const { t } = useT();

const form = ref({
    table_type: (props.initialData?.filters?.table_type || 'sales') as 'sales' | 'monthly_summaries',
    date_from: props.initialData?.filters?.date_from || props.initialData?.planogram?.start_date || '',
    date_to: props.initialData?.filters?.date_to || props.initialData?.planogram?.end_date || '',
    start_month: props.initialData?.filters?.start_month || '',
    end_month: props.initialData?.filters?.end_month || '',
    prev_date_from: '',
    prev_date_to: '',
    prev_start_month: '',
    prev_end_month: '',
    growth_threshold: props.initialData?.parameters?.growth_threshold ?? 0,
});

const gondolaId = computed(() => props.initialData?.gondola?.id);
const showParametersModal = ref(false);
const results = computed(() => props.initialData?.results || []);

const formatDate = (dateString: string | null | undefined): string => {
    if (!dateString) {
        return t('plannerate.performance.common.not_defined_feminine');
    }

    try {
        const date = new Date(dateString);

        if (isNaN(date.getTime())) {
            return dateString;
        }

        const day = date.getDate().toString().padStart(2, '0');
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const year = date.getFullYear();

        return `${day}/${month}/${year}`;
    } catch {
        return dateString;
    }
};

const handleParamsSubmit = (data: typeof form.value) => {
    if (!gondolaId.value) {
        console.error('ID da gôndola não encontrado');

        return;
    }

    form.value = { ...data };
};
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="space-y-2">
            <h1 class="text-3xl font-bold tracking-tight">{{ t('plannerate.analysis.bcg_page.title') }}</h1>
            <p class="text-muted-foreground">
                {{ t('plannerate.analysis.bcg_page.subtitle') }}
            </p>
        </div>

        <!-- Configuração -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <Settings class="size-5" />
                            {{ t('plannerate.analysis.bcg_page.parameters_title') }}
                        </CardTitle>
                        <CardDescription>
                            {{ t('plannerate.analysis.bcg_page.parameters_description') }}
                        </CardDescription>
                    </div>
                    <Button type="button" @click="showParametersModal = true">
                        <Settings class="mr-2 size-4" />
                        {{ t('plannerate.analysis.bcg_page.configure_parameters') }}
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-3 gap-4 text-sm">
                    <div class="flex items-center gap-2">
                        <Calendar class="size-4 text-muted-foreground" />
                        <span class="text-muted-foreground">{{ t('plannerate.analysis.period.start_date') }}:</span>
                        <span class="font-medium">{{ formatDate(form.date_from) }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Calendar class="size-4 text-muted-foreground" />
                        <span class="text-muted-foreground">{{ t('plannerate.analysis.period.end_date') }}:</span>
                        <span class="font-medium">{{ formatDate(form.date_to) }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-muted-foreground">{{ t('plannerate.analysis.bcg_page.current_period') }}:</span>
                        <span class="font-medium">
                            {{ form.table_type === 'sales' ? t('plannerate.analysis.period.sales') : t('plannerate.performance.common.monthly_summary') }}
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Modal de Parâmetros -->
        <BcgParamsModal
            v-model:open="showParametersModal"
            :initial-data="form"
            @submit="handleParamsSubmit"
        />

        <!-- Resultados -->
        <BcgResultsList v-if="results.length > 0" :results="results" />

        <!-- Empty State -->
        <Card v-else>
            <CardContent class="pt-6">
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <AlertCircle class="mb-4 size-12 text-muted-foreground" />
                    <h3 class="mb-2 text-lg font-semibold">{{ t('plannerate.analysis.bcg_page.no_results') }}</h3>
                    <p class="text-sm text-muted-foreground">
                        {{ t('plannerate.analysis.bcg_page.no_results_description') }}
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

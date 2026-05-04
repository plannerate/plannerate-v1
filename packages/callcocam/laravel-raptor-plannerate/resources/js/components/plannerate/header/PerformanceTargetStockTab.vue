<template>
    <div class="space-y-2">
        <!-- Modal de Parâmetros -->
        <TargetStockParamsModal
            v-model:open="showParametersModal"
            :initial-data="form"
            @submit="handleParamsSubmit"
        />

        <!-- Loading State -->
        <Card v-if="loading">
            <CardContent class="py-12 text-center">
                <div class="flex flex-col items-center gap-3">
                    <div
                        class="size-8 animate-spin rounded-full border-4 border-primary border-t-transparent"
                    ></div>
                    <p class="text-sm text-muted-foreground">
                        Calculando estoque alvo...
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- Resultados -->
        <TargetStockResultsList
            v-else-if="results.length > 0"
            :results="results"
            :loading="loading"
        >
            <template #top>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex items-center gap-1.5 rounded-md border border-border bg-accent/30 px-2 py-1 text-[11px] font-medium text-foreground">
                        <Settings class="size-3.5 text-muted-foreground" />
                        Parâmetros de Estoque Alvo
                    </div>

                    <div class="inline-flex min-w-32 items-center justify-between rounded-md bg-accent/40 px-2 py-1 text-[11px]">
                        <span class="text-muted-foreground">Tipo</span>
                        <span class="font-medium">
                            {{
                                form.table_type === 'sales'
                                    ? 'Vendas'
                                    : 'Resumo Mensal'
                            }}
                        </span>
                    </div>

                    <div
                        v-if="form.table_type === 'sales'"
                        class="inline-flex min-w-40 items-center justify-between rounded-md bg-accent/40 px-2 py-1 text-[11px]"
                    >
                        <span class="inline-flex items-center gap-1 text-muted-foreground">
                            <Calendar class="size-3.5" />
                            Inicial
                        </span>
                        <span class="font-medium">{{ formatDate(form.date_from) }}</span>
                    </div>

                    <div
                        v-if="form.table_type === 'sales'"
                        class="inline-flex min-w-40 items-center justify-between rounded-md bg-accent/40 px-2 py-1 text-[11px]"
                    >
                        <span class="inline-flex items-center gap-1 text-muted-foreground">
                            <Calendar class="size-3.5" />
                            Final
                        </span>
                        <span class="font-medium">{{ formatDate(form.date_to) }}</span>
                    </div>

                    <div
                        v-if="form.table_type === 'monthly_summaries'"
                        class="inline-flex min-w-40 items-center justify-between rounded-md bg-accent/40 px-2 py-1 text-[11px]"
                    >
                        <span class="inline-flex items-center gap-1 text-muted-foreground">
                            <Calendar class="size-3.5" />
                            Mês inicial
                        </span>
                        <span class="font-medium">{{ formatMonth(form.start_month) }}</span>
                    </div>

                    <div
                        v-if="form.table_type === 'monthly_summaries'"
                        class="inline-flex min-w-40 items-center justify-between rounded-md bg-accent/40 px-2 py-1 text-[11px]"
                    >
                        <span class="inline-flex items-center gap-1 text-muted-foreground">
                            <Calendar class="size-3.5" />
                            Mês final
                        </span>
                        <span class="font-medium">{{ formatMonth(form.end_month) }}</span>
                    </div>

                    <Button
                        type="button"
                        size="sm"
                        class="ml-auto h-7 px-2.5 gap-2"
                        @click="openParametersModal"
                    > 
                            <Settings /> 
                        <span class="text-[11px]">Configurar</span>
                    </Button>
                </div>
            </template>
        </TargetStockResultsList>

        <!-- Mensagem quando não há resultados -->
        <Card v-else-if="!loading && hasCalculated">
            <CardContent class="py-6 text-center text-muted-foreground">
                <p class="text-sm">Nenhum resultado encontrado</p>
                <p class="mt-1 text-xs">
                    Configure os parâmetros e execute o cálculo para ver os
                    resultados.
                </p>
            </CardContent>
        </Card>

        <!-- Estado inicial - ainda não calculou -->
        <Card v-else>
            <CardContent class="py-10 text-center">
                <div class="flex flex-col items-center gap-3">
                    <Package class="size-10 text-muted-foreground/50" />
                    <div>
                        <p class="text-sm font-medium">Nenhum cálculo realizado</p>
                        <p class="mt-1 text-xs text-muted-foreground">Configure os parâmetros e execute o cálculo de estoque alvo.</p>
                    </div>
                    <Button type="button" size="sm" class="gap-2" @click="openParametersModal">
                        <ActionIconBox variant="outline" class="[&_svg]:size-3.5">
                            <Settings />
                        </ActionIconBox>
                        Configurar e Calcular
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template> 

<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Calendar, Package, Settings } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { calculateTargetStockApi } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/GondolaAnalysisController';
import TargetStockParamsModal from '@/components/plannerate/analysis/TargetStockParamsModal.vue';
import TargetStockResultsList from '@/components/plannerate/analysis/TargetStockResultsList.vue';
import { Button } from '@/components/ui/button'; 
import {
    Card,
    CardContent,
} from '@/components/ui/card';
import { useTargetStockAnalysis } from '@/composables/plannerate/useTargetStockAnalysis';
import { wayfinderPath } from '../../../libs/wayfinderPath';

interface Planogram {
    id: string;
    name: string;
    tenant_id?: string;
    start_date?: string;
    end_date?: string;
    start_month?: string;
    end_month?: string;
}

interface Props {
    gondolaId?: string | null;
    planogram?: Planogram | null;
    loading?: boolean;
    results?: any[];
}

interface Emits {
    (e: 'calculate', params: any): void;
}

const props = withDefaults(defineProps<Props>(), {
    gondolaId: null,
    planogram: null,
    loading: false,
    results: () => [],
});

const emit = defineEmits<Emits>();
const page = usePage<{ subdomain?: string }>();
const isBrowser = typeof window !== 'undefined';

const resolvedSubdomain = computed(() => {
    const subdomainFromPage = page.props.subdomain?.toString().trim();

    if (subdomainFromPage) {
        return subdomainFromPage;
    }

    if (!isBrowser) {
        return '';
    }

    return window.location.hostname.split('.')[0] || '';
});

const loading = ref(props.loading);
const results = ref(props.results);
const hasCalculated = ref(false);
const showParametersModal = ref(false);

function openParametersModal(event: MouseEvent): void {
    (event.currentTarget as HTMLElement).blur();
    showParametersModal.value = true;
}

// Composable para gerenciar dados de target stock
const { setTargetStockDataBatch } = useTargetStockAnalysis();

const form = ref({
    table_type: 'sales' as 'sales' | 'monthly_summaries',
    date_from: props.planogram?.start_date || '',
    date_to: props.planogram?.end_date || '',
    start_month: props.planogram?.start_month || '',
    end_month: props.planogram?.end_month || '',
    nivel_servico_a: 0.7,
    nivel_servico_b: 0.8,
    nivel_servico_c: 0.9,
    cobertura_dias_a: 2,
    cobertura_dias_b: 5,
    cobertura_dias_c: 7,
});

// Watch para atualizar form quando planogram mudar
watch(
    () => props.planogram,
    (newPlanogram: Planogram | null) => {
        if (newPlanogram) {
            form.value.date_from = newPlanogram.start_date || '';
            form.value.date_to = newPlanogram.end_date || '';
            form.value.start_month = newPlanogram.start_month || '';
            form.value.end_month = newPlanogram.end_month || '';
        }
    },
    { deep: true },
);

const formatDate = (dateString: string | null | undefined): string => {
    if (!dateString) {
return 'Não definida';
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

const formatMonth = (monthString: string | null | undefined): string => {
    if (!monthString) {
return 'Não definida';
}

    try {
        const [year, month] = monthString.split('-');

        if (!year || !month) {
return monthString;
}

        return `${month}/${year}`;
    } catch {
        return monthString;
    }
};

const handleParamsSubmit = (data: typeof form.value): void => {
    const subdomain = resolvedSubdomain.value;

    if (!props.gondolaId || !subdomain) {
        return;
    }

    form.value = { ...data };

    loading.value = true;
    hasCalculated.value = true;

    router.post(
        wayfinderPath(calculateTargetStockApi.url({ subdomain, gondola: props.gondolaId })),
        form.value,
        {
            preserveState: true,
            only: ['analysis'],
            onSuccess: (page) => {
                const pageProps = page.props as Record<string, any>;

                if (pageProps.flash?.error) {
                    results.value = [];
                } else {
                    results.value = pageProps.analysis?.stock?.results ?? [];

                    if (results.value.length > 0) {
                        setTargetStockDataBatch(results.value);
                    }

                    emit('calculate', form.value);
                }
            },
            onError: () => {
                results.value = [];
            },
            onFinish: () => {
                loading.value = false;
            },
        },
    );
};
</script>

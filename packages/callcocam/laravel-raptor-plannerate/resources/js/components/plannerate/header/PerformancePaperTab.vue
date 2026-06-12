<template>
    <div class="space-y-2">
        <!-- Modal de Parâmetros -->
        <PaperParamsModal v-model:open="showParametersModal" :initial-data="form" @submit="handleParamsSubmit" />

        <!-- Loading State -->
        <Card v-if="loading">
            <CardContent class="py-12 text-center">
                <div class="flex flex-col items-center gap-3">
                    <div class="size-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
                    <p class="text-sm text-muted-foreground">{{ t('plannerate.performance.paper.loading') }}</p>
                </div>
            </CardContent>
        </Card>

        <!-- Resultados -->
        <PaperResultsList v-else-if="results.length > 0" :results="results" :loading="loading">
            <template #top>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex items-center gap-1.5 rounded-md border border-border bg-accent/30 px-2 py-1 text-[11px] font-medium text-foreground">
                        <Settings class="size-3.5 text-muted-foreground" />
                        {{ t('plannerate.performance.paper.analysis_parameters') }}
                    </div>

                    <div class="inline-flex min-w-32 items-center justify-between rounded-md bg-accent/40 px-2 py-1 text-[11px]">
                        <span class="text-muted-foreground">{{ t('plannerate.performance.common.type') }}</span>
                        <span class="font-medium">
                            {{ form.table_type === 'sales'
                                ? t('plannerate.performance.common.sales')
                                : t('plannerate.performance.common.monthly_summary') }}
                        </span>
                    </div>

                    <div v-if="form.table_type === 'sales' && form.date_from" class="inline-flex min-w-40 items-center justify-between rounded-md bg-accent/40 px-2 py-1 text-[11px]">
                        <span class="inline-flex items-center gap-1 text-muted-foreground">
                            <Calendar class="size-3.5" />
                            {{ t('plannerate.performance.common.start') }}
                        </span>
                        <span class="font-medium">{{ formatDate(form.date_from) }}</span>
                    </div>

                    <div v-if="form.table_type === 'sales' && form.date_to" class="inline-flex min-w-40 items-center justify-between rounded-md bg-accent/40 px-2 py-1 text-[11px]">
                        <span class="inline-flex items-center gap-1 text-muted-foreground">
                            <Calendar class="size-3.5" />
                            {{ t('plannerate.performance.common.end') }}
                        </span>
                        <span class="font-medium">{{ formatDate(form.date_to) }}</span>
                    </div>

                    <div v-if="form.table_type === 'monthly_summaries' && form.start_month" class="inline-flex min-w-40 items-center justify-between rounded-md bg-accent/40 px-2 py-1 text-[11px]">
                        <span class="inline-flex items-center gap-1 text-muted-foreground">
                            <Calendar class="size-3.5" />
                            {{ t('plannerate.performance.common.start_month') }}
                        </span>
                        <span class="font-medium">{{ formatMonth(form.start_month) }}</span>
                    </div>

                    <div v-if="form.table_type === 'monthly_summaries' && form.end_month" class="inline-flex min-w-40 items-center justify-between rounded-md bg-accent/40 px-2 py-1 text-[11px]">
                        <span class="inline-flex items-center gap-1 text-muted-foreground">
                            <Calendar class="size-3.5" />
                            {{ t('plannerate.performance.common.end_month') }}
                        </span>
                        <span class="font-medium">{{ formatMonth(form.end_month) }}</span>
                    </div>

                    <Button
                        type="button"
                        size="sm"
                        class="ml-auto h-9 gap-2 border border-primary/40 bg-primary/10 px-3 font-semibold text-primary shadow-sm transition-colors hover:bg-primary/20 focus-visible:ring-2 focus-visible:ring-primary/40"
                        @click="openParametersModal">
                        <Settings />
                        <span>{{ t('plannerate.performance.common.configure') }}</span>
                    </Button>
                </div>
            </template>
        </PaperResultsList>

        <!-- Sem resultados após calcular -->
        <Card v-else-if="!loading && hasCalculated">
            <CardContent class="py-6 text-center text-muted-foreground">
                <p class="text-sm">{{ t('plannerate.performance.common.no_results') }}</p>
                <p class="mt-1 text-xs">{{ t('plannerate.performance.paper.empty_description') }}</p>
            </CardContent>
        </Card>

        <!-- Estado inicial -->
        <Card v-else>
            <CardContent class="py-10 text-center">
                <div class="flex flex-col items-center gap-3">
                    <TrendingUp class="size-10 text-muted-foreground/50" />
                    <div>
                        <p class="text-sm font-medium">{{ t('plannerate.performance.paper.no_analysis') }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ t('plannerate.performance.paper.no_analysis_description') }}</p>
                    </div>
                    <Button type="button" size="sm" class="gap-2" @click="openParametersModal">
                        <Settings />
                        {{ t('plannerate.performance.paper.configure_and_calculate') }}
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Calendar, Settings, TrendingUp } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { calculatePaperApi } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/GondolaAnalysisController';
import PaperParamsModal from '@/components/plannerate/analysis/PaperParamsModal.vue';
import PaperResultsList from '@/components/plannerate/analysis/PaperResultsList.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useT } from '@/composables/useT';
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

/** Dados do formulário enviados ao endpoint de Análise de Papel. */
interface PaperFormData {
    gondola_id: string;
    table_type: 'sales' | 'monthly_summaries';
    date_from: string;
    date_to: string;
    start_month: string;
    end_month: string;
    prev_date_from: string;
    prev_date_to: string;
    prev_start_month: string;
    prev_end_month: string;
    /** Limiar fixo de crescimento — null = mediana automática por categoria */
    growth_threshold: number | null;
}

const props = withDefaults(defineProps<Props>(), {
    gondolaId: null,
    planogram: null,
    loading: false,
    results: () => [],
});

const page = usePage<{ subdomain?: string }>();
const { t } = useT();
const isBrowser = typeof window !== 'undefined';

const resolvedSubdomain = computed(() => {
    const subdomainFromPage = page.props.subdomain?.toString().trim();
    if (subdomainFromPage) return subdomainFromPage;
    if (!isBrowser) return '';
    return window.location.hostname.split('.')[0] || '';
});

const loading = ref(props.loading);
const results = ref(props.results);
const hasCalculated = ref(false);
const showParametersModal = ref(false);

/** Chave de localStorage por gôndola para persistir os parâmetros da análise. */
const getStorageKey = (gondolaId: string): string =>
    `plannerate:performance:paper:params:${gondolaId}`;

const buildDefaultForm = (): PaperFormData => ({
    gondola_id:        props.gondolaId || '',
    table_type:        'sales',
    date_from:         props.planogram?.start_date || '',
    date_to:           props.planogram?.end_date || '',
    start_month:       props.planogram?.start_month || dateToMonth(props.planogram?.start_date),
    end_month:         props.planogram?.end_month || dateToMonth(props.planogram?.end_date),
    prev_date_from:    '',
    prev_date_to:      '',
    prev_start_month:  '',
    prev_end_month:    '',
    growth_threshold:  null,
});

const loadStoredForm = (): Partial<PaperFormData> => {
    if (!isBrowser || !props.gondolaId) return {};

    const raw = window.localStorage.getItem(getStorageKey(props.gondolaId));
    if (!raw) return {};

    try {
        const stored = JSON.parse(raw) as Partial<PaperFormData>;

        // Migração: formulários antigos persistiam growth_threshold = 0 como default;
        // 0 agora significa limiar fixo — restaura para null (mediana automática)
        if (stored.growth_threshold === 0) {
            stored.growth_threshold = null;
        }

        return stored;
    } catch {
        window.localStorage.removeItem(getStorageKey(props.gondolaId));
        return {};
    }
};

const saveStoredForm = (data: PaperFormData): void => {
    if (!isBrowser || !props.gondolaId) return;
    window.localStorage.setItem(getStorageKey(props.gondolaId), JSON.stringify(data));
};

function openParametersModal(event: MouseEvent): void {
    (event.currentTarget as HTMLElement).blur();
    showParametersModal.value = true;
}

function dateToMonth(dateString: string | null | undefined): string {
    if (!dateString) return '';

    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return '';
        return `${date.getFullYear()}-${(date.getMonth() + 1).toString().padStart(2, '0')}`;
    } catch {
        return '';
    }
}

const form = ref<PaperFormData>({
    ...buildDefaultForm(),
    ...loadStoredForm(),
});

watch(
    () => props.planogram,
    (newPlanogram: Planogram | null) => {
        if (newPlanogram) {
            if (newPlanogram.start_date)  form.value.date_from    = newPlanogram.start_date;
            if (newPlanogram.end_date)    form.value.date_to      = newPlanogram.end_date;
            if (newPlanogram.start_month) form.value.start_month  = newPlanogram.start_month;
            else if (newPlanogram.start_date) form.value.start_month = dateToMonth(newPlanogram.start_date);
            if (newPlanogram.end_month)   form.value.end_month    = newPlanogram.end_month;
            else if (newPlanogram.end_date)   form.value.end_month   = dateToMonth(newPlanogram.end_date);
        }
    },
    { deep: true },
);

watch(() => props.gondolaId, () => {
    form.value = { ...buildDefaultForm(), ...loadStoredForm() };
});

const formatDate = (dateString: string | null | undefined): string => {
    if (!dateString) return t('plannerate.performance.common.not_defined_feminine');

    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return dateString;
        const day   = date.getDate().toString().padStart(2, '0');
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        return `${day}/${month}/${date.getFullYear()}`;
    } catch {
        return dateString;
    }
};

const formatMonth = (monthString: string | null | undefined): string => {
    if (!monthString) return t('plannerate.performance.common.not_defined');

    try {
        const [year, month] = monthString.split('-');
        if (!year || !month) return monthString;
        return `${month}/${year}`;
    } catch {
        return monthString;
    }
};

const handleParamsSubmit = (data: Partial<PaperFormData>): void => {
    const subdomain = resolvedSubdomain.value;

    if (!props.gondolaId || !subdomain) return;

    form.value = { ...form.value, ...data };
    saveStoredForm(form.value);

    loading.value     = true;
    hasCalculated.value = true;

    router.post(
        wayfinderPath(calculatePaperApi.url({ subdomain, gondola: props.gondolaId })),
        form.value,
        {
            preserveState: true,
            only: ['analysis'],
            onSuccess: (page) => {
                const pageProps = page.props as Record<string, any>;

                if (pageProps.flash?.error) {
                    results.value = [];
                } else {
                    // O servidor salva os resultados sob a chave 'paper'
                    results.value = pageProps.analysis?.paper?.results ?? [];
                }
            },
            onError: () => { results.value = []; },
            onFinish: () => { loading.value = false; },
        },
    );
};
</script>

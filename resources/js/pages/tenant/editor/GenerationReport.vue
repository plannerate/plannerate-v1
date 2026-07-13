<script setup lang="ts">
/**
 * Página do relatório da geração da gôndola.
 *
 * Recebe do backend a execução selecionada (última por padrão, ou a pedida via
 * `?run=`) e reaproveita os mesmos componentes que antes eram renderizados embaixo
 * do canvas do editor — capacidade/rejeitados, sugestões e validação.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';
import PlanogramCapacityBanner from '@/components/PlanogramCapacityBanner.vue';
import PlanogramSuggestions from '@/components/PlanogramSuggestions.vue';
import PlanogramValidationReport from '@/components/PlanogramValidationReport.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

interface GenerationRunSummary {
    id: string;
    status: 'queued' | 'running' | 'completed' | 'failed';
    status_label: string;
    is_pending: boolean;
    mode: string;
    occupancy_avg: number | null;
    duration_ms: number | null;
    error_message: string | null;
    created_at: string | null;
}

interface GenerationRunDetail extends GenerationRunSummary {
    capacity_report: Record<string, any> | null;
    validation_report: Record<string, any> | null;
}

const props = defineProps<{
    gondola: {
        id: string;
        name: string | null;
        planogram_id: string | null;
        planogram_name: string | null;
        generation_mode: string | null;
    };
    run: GenerationRunDetail | null;
    runs: GenerationRunSummary[];
    editorUrl: string;
}>();

const { t } = useT();

// Relatórios vêm do backend como JSON livre (o formato varia por modo de geração);
// os componentes que os consomem é que declaram o formato que esperam.
const capacityReport = computed<any>(() => props.run?.capacity_report ?? null);
const validationReport = computed<any>(() => props.run?.validation_report ?? null);

/** Sugestões só fazem sentido com template — é nele que o usuário edita o slot. */
const suggestionsReport = computed(() => {
    const report = capacityReport.value;

    if (!report?.suggestions?.length || !report?.template_id) {
        return null;
    }

    return report;
});

const placed = computed<number>(() => capacityReport.value?.posicionados ?? 0);
const total = computed<number>(() => capacityReport.value?.total_produtos ?? 0);
const coverage = computed<number>(() => Math.round((capacityReport.value?.taxa_cobertura ?? 0) * 100));
const noSpace = computed<number>(() => capacityReport.value?.rejeitados_espaco ?? 0);
const noDimensions = computed<number>(() => capacityReport.value?.rejeitados_sem_dimensao ?? 0);
const heightExceeds = computed<number>(() => capacityReport.value?.rejeitados_altura ?? 0);

const statusVariant = computed<'default' | 'secondary' | 'destructive'>(() => {
    if (props.run?.status === 'failed') {
        return 'destructive';
    }

    return props.run?.is_pending ? 'secondary' : 'default';
});

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
}

function formatDuration(ms: number | null): string {
    if (ms === null) {
        return '—';
    }

    return ms >= 1000 ? `${(ms / 1000).toFixed(1)}s` : `${ms}ms`;
}

function formatOccupancy(value: number | null): string {
    if (value === null) {
        return '—';
    }

    return `${Math.round(value * 100)}%`;
}

/** Troca a execução exibida sem sair da página. */
function selectRun(runId: string): void {
    router.get(
        `/editor/gondolas/${props.gondola.id}/generation-report`,
        { run: runId },
        { preserveScroll: true, preserveState: true },
    );
}

const breadcrumbs = [
    { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
    { title: props.gondola.name ?? '', href: props.editorUrl },
    { title: t('plannerate.generation.report.title'), href: '' },
];
</script>

<template>
    <Head :title="t('plannerate.generation.report.head_title', { gondola: gondola.name ?? '' })" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <!-- Cabeçalho -->
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-1">
                    <h1 class="text-xl font-semibold">{{ t('plannerate.generation.report.title') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ gondola.name }}
                        <span v-if="gondola.planogram_name"> · {{ gondola.planogram_name }}</span>
                    </p>
                    <div v-if="run" class="flex items-center gap-2 pt-1">
                        <Badge :variant="statusVariant">{{ run.status_label }}</Badge>
                        <span class="text-xs text-muted-foreground">
                            {{ t('plannerate.generation.report.generated_at', { date: formatDate(run.created_at) }) }}
                        </span>
                    </div>
                </div>

                <Button as-child variant="outline" size="sm">
                    <Link :href="editorUrl" class="gap-2">
                        <ArrowLeft class="size-4" />
                        {{ t('plannerate.generation.report.back_to_editor') }}
                    </Link>
                </Button>
            </div>

            <!-- Nunca gerada -->
            <div
                v-if="!run"
                class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted-foreground"
            >
                {{ t('plannerate.generation.report.empty') }}
            </div>

            <!-- Execução que falhou: não há relatório, só o motivo -->
            <div
                v-else-if="run.status === 'failed'"
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm dark:border-red-900 dark:bg-red-950/40"
            >
                <p class="font-medium text-red-800 dark:text-red-200">
                    {{ t('plannerate.generation.report.failed') }}
                </p>
                <p v-if="run.error_message" class="mt-1 text-red-700 dark:text-red-300">
                    {{ run.error_message }}
                </p>
            </div>

            <template v-else>
                <!-- Números da execução -->
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg border border-border bg-background p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">
                            {{ t('plannerate.generation.report.metrics.positioned') }}
                        </p>
                        <p class="mt-1 text-2xl font-bold">
                            {{ placed }}<span class="text-base font-normal text-muted-foreground">/{{ total }}</span>
                        </p>
                    </div>
                    <div class="rounded-lg border border-border bg-background p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">
                            {{ t('plannerate.generation.report.metrics.coverage') }}
                        </p>
                        <p class="mt-1 text-2xl font-bold">{{ coverage }}%</p>
                    </div>
                    <div class="rounded-lg border border-border bg-background p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">
                            {{ t('plannerate.generation.report.metrics.occupancy') }}
                        </p>
                        <p class="mt-1 text-2xl font-bold">{{ formatOccupancy(run.occupancy_avg) }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-background p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">
                            {{ t('plannerate.generation.report.metrics.duration') }}
                        </p>
                        <p class="mt-1 text-2xl font-bold">{{ formatDuration(run.duration_ms) }}</p>
                    </div>
                </div>

                <!-- Pendências (só as que existem) -->
                <div v-if="noSpace || noDimensions || heightExceeds" class="flex flex-wrap gap-2 text-xs">
                    <span v-if="noSpace" class="rounded-full bg-amber-100 px-3 py-1 font-medium text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                        {{ t('plannerate.generation.report.metrics.no_space') }}: {{ noSpace }}
                    </span>
                    <span v-if="noDimensions" class="rounded-full bg-purple-100 px-3 py-1 font-medium text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                        {{ t('plannerate.generation.report.metrics.no_dimensions') }}: {{ noDimensions }}
                    </span>
                    <span v-if="heightExceeds" class="rounded-full bg-blue-100 px-3 py-1 font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                        {{ t('plannerate.generation.report.metrics.height_exceeds') }}: {{ heightExceeds }}
                    </span>
                </div>

                <!-- Relatórios detalhados (mesmos componentes que antes ficavam no editor) -->
                <PlanogramCapacityBanner :report="capacityReport" />

                <PlanogramSuggestions
                    v-if="suggestionsReport"
                    :suggestions="suggestionsReport.suggestions"
                    :template-id="suggestionsReport.template_id"
                />

                <PlanogramValidationReport v-if="validationReport" :report="validationReport" />
            </template>

            <!-- Histórico de execuções desta gôndola -->
            <div v-if="runs.length > 1" class="rounded-lg border border-border bg-background">
                <p class="border-b border-border px-4 py-3 text-sm font-semibold">
                    {{ t('plannerate.generation.report.runs.title') }}
                </p>
                <ul class="divide-y divide-border">
                    <li
                        v-for="item in runs"
                        :key="item.id"
                        class="flex flex-wrap items-center gap-3 px-4 py-2 text-sm"
                        :class="{ 'bg-muted/50': item.id === run?.id }"
                    >
                        <Badge variant="outline">{{ item.status_label }}</Badge>
                        <span class="text-muted-foreground">{{ formatDate(item.created_at) }}</span>
                        <span class="text-muted-foreground">{{ formatOccupancy(item.occupancy_avg) }}</span>
                        <span v-if="item.id === run?.id" class="ml-auto text-xs text-muted-foreground">
                            {{ t('plannerate.generation.report.runs.viewing') }}
                        </span>
                        <Button
                            v-else
                            variant="ghost"
                            size="sm"
                            class="ml-auto text-xs"
                            @click="selectRun(item.id)"
                        >
                            {{ t('plannerate.generation.report.link') }}
                        </Button>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>

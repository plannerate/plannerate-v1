<script setup lang="ts">
import { computed } from 'vue';
import FlowReportChart from '@flow/components/charts/FlowReportChart.vue';
import { Button } from '~/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { WorkflowReport } from '@/types/dashboard';
import { useDashboardWorkflowFilters } from '@/composables/dashboard/useDashboardWorkflowFilters';

interface Props {
    workflowReport: WorkflowReport;
}

const props = defineProps<Props>();

const {
    flowSlug,
    dateFrom,
    dateTo,
    responsibleId,
    applyWorkflowFilters,
    clearWorkflowFilters,
} = useDashboardWorkflowFilters(props.workflowReport.filters.values);

const workflowStatusChart = computed(() => props.workflowReport.charts.status.data);
const workflowResponsibleChart = computed(() => props.workflowReport.charts.responsible.data);
const workflowSlaChart = computed(() => props.workflowReport.charts.sla.data);
const workflowStepAverageChart = computed(() => props.workflowReport.charts.step_avg_effective_minutes.data);
const workflowSummary = computed(() => props.workflowReport.summary);
const workflowResponsibleActivity = computed(() => props.workflowReport.tables.responsible_activity?.data ?? []);
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">Relatórios de Workflow</CardTitle>
            <CardDescription>
                Andamento por status, responsável, SLA e tempo médio por etapa
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                <label class="space-y-1 text-sm">
                    <span class="text-muted-foreground">Fluxo</span>
                    <select
                        v-model="flowSlug"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">Todos os fluxos</option>
                        <option
                            v-for="flow in workflowReport.filters.options.flows"
                            :key="flow.value"
                            :value="flow.value"
                        >
                            {{ flow.label }}
                        </option>
                    </select>
                </label>

                <label class="space-y-1 text-sm">
                    <span class="text-muted-foreground">Responsável</span>
                    <select
                        v-model="responsibleId"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">Todos</option>
                        <option
                            v-for="responsible in workflowReport.filters.options.responsibles"
                            :key="responsible.value"
                            :value="responsible.value"
                        >
                            {{ responsible.label }}
                        </option>
                    </select>
                </label>

                <label class="space-y-1 text-sm">
                    <span class="text-muted-foreground">De</span>
                    <input
                        v-model="dateFrom"
                        type="date"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                    >
                </label>

                <label class="space-y-1 text-sm">
                    <span class="text-muted-foreground">Até</span>
                    <input
                        v-model="dateTo"
                        type="date"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                    >
                </label>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button size="sm" @click="applyWorkflowFilters">Aplicar filtros</Button>
                <Button size="sm" variant="outline" @click="clearWorkflowFilters">Limpar</Button>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <Card class="border border-border/80">
                    <CardContent class="p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Execuções</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums">{{ workflowSummary.total_executions }}</p>
                    </CardContent>
                </Card>
                <Card class="border border-border/80">
                    <CardContent class="p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Métricas</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums">{{ workflowSummary.total_metrics }}</p>
                    </CardContent>
                </Card>
                <Card class="border border-border/80">
                    <CardContent class="p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Eventos de histórico</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums">{{ workflowSummary.total_history_events }}</p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <FlowReportChart
                    title="Andamento por status"
                    description="Estado atual das execuções"
                    :chart-type="workflowReport.charts.status.type"
                    :chart-data="workflowStatusChart"
                    empty-message="Sem execuções para os filtros"
                />

                <FlowReportChart
                    title="Andamento por responsável"
                    description="Distribuição por responsável atual"
                    :chart-type="workflowReport.charts.responsible.type"
                    :chart-data="workflowResponsibleChart"
                    empty-message="Sem responsáveis para os filtros"
                />

                <FlowReportChart
                    title="SLA no prazo vs atrasado"
                    description="Conformidade de prazo pelas métricas"
                    :chart-type="workflowReport.charts.sla.type"
                    :chart-data="workflowSlaChart"
                    empty-message="Sem métricas no período"
                />

                <FlowReportChart
                    title="Tempo médio por etapa"
                    description="Média de minutos efetivos por etapa"
                    :chart-type="workflowReport.charts.step_avg_effective_minutes.type"
                    :chart-data="workflowStepAverageChart"
                    empty-message="Sem dados por etapa no período"
                />
            </div>

            <div class="rounded-lg border border-border/80">
                <div class="border-b border-border/80 px-4 py-3 text-sm font-medium">
                    Detalhamento por responsável
                </div>
                <div class="divide-y divide-border/60">
                    <div
                        v-for="row in workflowResponsibleActivity"
                        :key="row.responsible_id"
                        class="grid gap-2 px-4 py-3 text-sm md:grid-cols-3"
                    >
                        <span class="font-medium text-foreground">{{ row.name }}</span>
                        <span class="text-muted-foreground">Ações: {{ row.actions_count }}</span>
                        <span class="text-muted-foreground">Tempo médio: {{ row.avg_duration_minutes ?? 0 }} min</span>
                    </div>
                    <div
                        v-if="workflowResponsibleActivity.length === 0"
                        class="px-4 py-6 text-sm text-muted-foreground"
                    >
                        Sem detalhamento de responsáveis para os filtros atuais.
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>

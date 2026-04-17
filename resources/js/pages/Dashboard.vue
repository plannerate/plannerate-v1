<script setup lang="ts">
import DashboardClientAccess from '@/components/dashboard/DashboardClientAccess.vue';
import DashboardPlanogramCharts from '@/components/dashboard/DashboardPlanogramCharts.vue';
import DashboardProductAnalytics from '@/components/dashboard/DashboardProductAnalytics.vue';
import DashboardRecentPlanograms from '@/components/dashboard/DashboardRecentPlanograms.vue';
import DashboardStatCards from '@/components/dashboard/DashboardStatCards.vue';
import DashboardWorkflowReports from '@/components/dashboard/DashboardWorkflowReports.vue';
import ResourceLayout from '~/layouts/ResourceLayout.vue';
import { dashboard } from '@/routes';
import type { ClientWithDomain, PlanogramStats, WorkflowReport } from '@/types/dashboard';

interface Props {
    clientsWithDomains?: ClientWithDomain[];
    isTenantPrincipal: boolean;
    planogramStats: PlanogramStats;
    workflowReport: WorkflowReport;
}

defineProps<Props>();

const resourceBreadcrumbs = [
    {
        label: 'Painel de controle',
        url: dashboard().url,
    },
];
</script>

<template>
    <ResourceLayout
        title="Painel de controle"
        message="Visão geral dos indicadores da operação"
        :breadcrumbs="resourceBreadcrumbs"
        :max-width="'7xl'"
    >
        <div class="flex h-full w-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <DashboardStatCards :planogram-stats="planogramStats" />
            <DashboardPlanogramCharts :planogram-stats="planogramStats" />
            <DashboardProductAnalytics :planogram-stats="planogramStats" />
            <DashboardRecentPlanograms :recent-planograms="planogramStats.recent_planograms" />
            <DashboardWorkflowReports :workflow-report="workflowReport" />
            <DashboardClientAccess
                :is-tenant-principal="isTenantPrincipal"
                :clients-with-domains="clientsWithDomains"
            />
        </div>
    </ResourceLayout>
</template>

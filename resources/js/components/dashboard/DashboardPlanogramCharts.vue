<script setup lang="ts">
import { BarChart3, TrendingUp } from 'lucide-vue-next';
import {
    Chart as ChartJS,
    ArcElement,
    Tooltip,
    Legend,
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
} from 'chart.js';
import { Doughnut, Bar } from 'vue-chartjs';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { PlanogramStats } from '@/types/dashboard';
import {
    useDashboardCharts,
    statusChartOptions,
    planogramsByMonthOptions,
} from '@/composables/dashboard/useDashboardCharts';

ChartJS.register(
    ArcElement,
    Tooltip,
    Legend,
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
);

interface Props {
    planogramStats: PlanogramStats;
}

const props = defineProps<Props>();

const { statusChartData, planogramsByMonthData } = useDashboardCharts(() => props.planogramStats);
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-3">
        <Card class="flex flex-col">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <BarChart3 class="size-4 text-muted-foreground" />
                    Status dos planogramas
                </CardTitle>
                <CardDescription>
                    Distribuição por estado
                </CardDescription>
            </CardHeader>
            <CardContent class="flex-1 pt-0">
                <div class="h-[220px] w-full">
                    <Doughnut
                        v-if="planogramStats.total_planograms > 0"
                        :data="statusChartData"
                        :options="statusChartOptions"
                        class="!max-h-[220px]"
                    />
                    <div
                        v-else
                        class="flex h-full items-center justify-center rounded-lg bg-muted/30 text-sm text-muted-foreground"
                    >
                        Nenhum planograma
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card class="flex flex-col lg:col-span-2">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <TrendingUp class="size-4 text-muted-foreground" />
                    Planogramas construídos
                </CardTitle>
                <CardDescription>
                    Últimos 6 meses
                </CardDescription>
            </CardHeader>
            <CardContent class="flex-1 pt-0">
                <div class="h-[220px] w-full">
                    <Bar
                        v-if="planogramsByMonthData.labels.length"
                        :data="planogramsByMonthData"
                        :options="planogramsByMonthOptions"
                        class="!max-h-[220px]"
                    />
                    <div
                        v-else
                        class="flex h-full items-center justify-center rounded-lg bg-muted/30 text-sm text-muted-foreground"
                    >
                        Sem dados no período
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

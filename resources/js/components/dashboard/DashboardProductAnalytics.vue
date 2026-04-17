<script setup lang="ts">
import { Layers, Package } from 'lucide-vue-next';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
    Tooltip,
    Legend,
} from 'chart.js';
import { Bar } from 'vue-chartjs';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { PlanogramStats } from '@/types/dashboard';
import {
    useDashboardCharts,
    topCategoriesChartOptions,
    topProductsChartOptions,
} from '@/composables/dashboard/useDashboardCharts';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

interface Props {
    planogramStats: PlanogramStats;
}

const props = defineProps<Props>();

const { topCategoriesChartData, topProductsChartData } = useDashboardCharts(() => props.planogramStats);
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-2">
        <Card class="flex flex-col">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <Layers class="size-4 text-muted-foreground" />
                    Categorias mais usadas
                </CardTitle>
                <CardDescription>
                    Por quantidade de produtos
                </CardDescription>
            </CardHeader>
            <CardContent class="flex-1 pt-0">
                <div class="h-[240px] w-full">
                    <Bar
                        v-if="planogramStats.top_categories.length > 0"
                        :data="topCategoriesChartData"
                        :options="topCategoriesChartOptions"
                        class="!max-h-[240px]"
                    />
                    <div
                        v-else
                        class="flex h-full items-center justify-center rounded-lg bg-muted/30 text-sm text-muted-foreground"
                    >
                        Nenhuma categoria
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card class="flex flex-col">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <Package class="size-4 text-muted-foreground" />
                    Produtos mais usados nas gôndolas
                </CardTitle>
                <CardDescription>
                    Por quantidade alocada
                </CardDescription>
            </CardHeader>
            <CardContent class="flex-1 pt-0">
                <div class="h-[240px] w-full">
                    <Bar
                        v-if="planogramStats.top_products_in_gondolas.length > 0"
                        :data="topProductsChartData"
                        :options="topProductsChartOptions"
                        class="!max-h-[240px]"
                    />
                    <div
                        v-else
                        class="flex h-full items-center justify-center rounded-lg bg-muted/30 text-sm text-muted-foreground"
                    >
                        Nenhum produto em gôndolas
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

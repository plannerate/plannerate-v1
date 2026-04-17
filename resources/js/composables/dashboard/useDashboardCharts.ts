import { computed } from 'vue';
import type { PlanogramStats } from '@/types/dashboard';

export const chartColors = {
    primary: '#22c55e',
    secondary: '#14b8a6',
    tertiary: '#3b82f6',
    quaternary: '#eab308',
    quinary: '#f97316',
    draft: '#64748b',
    published: '#22c55e',
    archived: '#475569',
};

export const statusChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '68%',
    plugins: {
        legend: {
            position: 'bottom' as const,
            labels: {
                padding: 16,
                usePointStyle: true,
            },
        },
    },
};

export const planogramsByMonthOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: { stepSize: 1 },
            grid: { color: 'rgba(0,0,0,0.06)' },
        },
        x: {
            grid: { display: false },
        },
    },
};

export const topCategoriesChartOptions = {
    indexAxis: 'y' as const,
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
    },
    scales: {
        x: {
            beginAtZero: true,
            grid: { color: 'rgba(0,0,0,0.06)' },
        },
        y: {
            grid: { display: false },
        },
    },
};

export const topProductsChartOptions = {
    indexAxis: 'y' as const,
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
    },
    scales: {
        x: {
            beginAtZero: true,
            grid: { color: 'rgba(0,0,0,0.06)' },
        },
        y: {
            grid: { display: false },
        },
    },
};

export function useDashboardCharts(planogramStats: () => PlanogramStats) {
    const statusChartData = computed(() => ({
        labels: ['Rascunho', 'Publicado', 'Arquivado'],
        datasets: [
            {
                data: [
                    planogramStats().status_stats.draft,
                    planogramStats().status_stats.published,
                    planogramStats().status_stats.archived,
                ],
                backgroundColor: [chartColors.draft, chartColors.published, chartColors.archived],
                borderWidth: 0,
                hoverOffset: 6,
            },
        ],
    }));

    const planogramsByMonthData = computed(() => ({
        labels: planogramStats().planograms_by_month.map((monthData) => monthData.month),
        datasets: [
            {
                label: 'Planogramas criados',
                data: planogramStats().planograms_by_month.map((monthData) => monthData.count),
                backgroundColor: chartColors.primary,
                borderRadius: 6,
                borderSkipped: false,
            },
        ],
    }));

    const topCategoriesChartData = computed(() => {
        const categories = planogramStats().top_categories;

        return {
            labels: categories.map((category) => category.name),
            datasets: [
                {
                    label: 'Produtos',
                    data: categories.map((category) => category.count),
                    backgroundColor: [
                        chartColors.primary,
                        chartColors.secondary,
                        chartColors.tertiary,
                        chartColors.quaternary,
                        chartColors.quinary,
                    ],
                    borderRadius: 6,
                    borderSkipped: false,
                },
            ],
        };
    });

    const topProductsChartData = computed(() => {
        const products = planogramStats().top_products_in_gondolas;

        return {
            labels: products.map((product) =>
                product.name.length > 28 ? `${product.name.slice(0, 26)}...` : product.name,
            ),
            datasets: [
                {
                    label: 'Quantidade',
                    data: products.map((product) => product.count),
                    backgroundColor: [
                        chartColors.secondary,
                        chartColors.tertiary,
                        chartColors.quaternary,
                        chartColors.quinary,
                        chartColors.primary,
                    ],
                    borderRadius: 6,
                    borderSkipped: false,
                },
            ],
        };
    });

    return {
        statusChartData,
        planogramsByMonthData,
        topCategoriesChartData,
        topProductsChartData,
    };
}

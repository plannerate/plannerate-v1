<script setup lang="ts">
import {
    LayoutGrid,
    Columns3,
    Package,
    FileEdit,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { PlanogramStats } from '@/types/dashboard';
import {
    trendToPaths,
    trendStrokeClass,
    trendFillClass,
} from '@/composables/dashboard/useDashboardFormatters';

interface Props {
    planogramStats: PlanogramStats;
}

const props = defineProps<Props>();

const statCards = computed(() => {
    const trends = props.planogramStats.card_trends ?? {
        planograms: [0, 0, 0, 0, 0, 0],
        gondolas: [0, 0, 0, 0, 0, 0],
        products: [0, 0, 0, 0, 0, 0],
        drafts: [0, 0, 0, 0, 0, 0],
    };

    return [
        {
            title: 'Total de Planogramas',
            value: props.planogramStats.total_planograms,
            subtitle: `${props.planogramStats.status_stats.published} publicados`,
            icon: LayoutGrid,
            accent: 'emerald',
            trend: trends.planograms,
            iconBg:
                'bg-emerald-500/20 text-emerald-700 dark:bg-emerald-400/20 dark:text-emerald-300 ring-2 ring-emerald-400/30 dark:ring-emerald-500/30',
        },
        {
            title: 'Total de Gôndolas',
            value: props.planogramStats.total_gondolas,
            subtitle: 'Gôndolas configuradas',
            icon: Columns3,
            accent: 'sky',
            trend: trends.gondolas,
            iconBg:
                'bg-sky-500/20 text-sky-700 dark:bg-sky-400/20 dark:text-sky-300 ring-2 ring-sky-400/30 dark:ring-sky-500/30',
        },
        {
            title: 'Total de Produtos',
            value: props.planogramStats.total_products,
            subtitle: 'Produtos cadastrados',
            icon: Package,
            accent: 'amber',
            trend: trends.products,
            iconBg:
                'bg-amber-500/20 text-amber-700 dark:bg-amber-400/20 dark:text-amber-300 ring-2 ring-amber-400/30 dark:ring-amber-500/30',
        },
        {
            title: 'Rascunhos',
            value: props.planogramStats.status_stats.draft,
            subtitle: 'Aguardando publicação',
            icon: FileEdit,
            accent: 'slate',
            trend: trends.drafts,
            iconBg:
                'bg-slate-500/20 text-slate-700 dark:bg-slate-400/20 dark:text-slate-300 ring-2 ring-slate-400/30 dark:ring-slate-500/30',
        },
    ];
});
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card
            v-for="(stat, idx) in statCards"
            :key="idx"
            class="group relative overflow-hidden border border-border/80 bg-card shadow-sm transition-all duration-200 hover:border-border hover:shadow-lg"
        >
            <div
                class="absolute left-0 top-0 h-full w-1 shrink-0 rounded-l-xl"
                :class="{
                    'bg-emerald-500 dark:bg-emerald-400': stat.accent === 'emerald',
                    'bg-sky-500 dark:bg-sky-400': stat.accent === 'sky',
                    'bg-amber-500 dark:bg-amber-400': stat.accent === 'amber',
                    'bg-slate-500 dark:bg-slate-400': stat.accent === 'slate',
                }"
                aria-hidden="true"
            />
            <CardHeader class="flex flex-row items-start justify-between space-y-0 pb-2 pl-5">
                <CardTitle
                    class="text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                >
                    {{ stat.title }}
                </CardTitle>
                <div
                    class="flex size-11 items-center justify-center rounded-xl shadow-sm transition-transform duration-200 group-hover:scale-105"
                    :class="stat.iconBg"
                >
                    <component :is="stat.icon" class="size-5" stroke-width="2.25" />
                </div>
            </CardHeader>
            <CardContent class="pl-5 pb-3 pt-2">
                <div class="text-3xl font-bold tracking-tight text-foreground tabular-nums">
                    {{ stat.value.toLocaleString('pt-BR') }}
                </div>
                <p class="mt-1.5 text-sm text-muted-foreground">
                    {{ stat.subtitle }}
                </p>
                <div class="mt-3 h-10 w-full overflow-hidden rounded-b-md">
                    <svg
                        viewBox="0 0 120 24"
                        preserveAspectRatio="none"
                        class="h-full w-full"
                        aria-hidden="true"
                    >
                        <path
                            :d="trendToPaths(stat.trend, 22, 120).area"
                            :class="trendFillClass(stat.accent)"
                        />
                        <path
                            :d="trendToPaths(stat.trend, 22, 120).line"
                            fill="none"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            :class="trendStrokeClass(stat.accent)"
                        />
                    </svg>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

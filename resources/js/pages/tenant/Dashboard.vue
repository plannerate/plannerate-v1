<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, setLayoutProps, usePage } from '@inertiajs/vue3';
import { LayoutTemplate, Shapes, Package } from 'lucide-vue-next';
import CategoryController from '@/actions/App/Http/Controllers/Tenant/CategoryController';
import PlanogramController from '@/actions/App/Http/Controllers/Tenant/PlanogramController';
import ProductController from '@/actions/App/Http/Controllers/Tenant/ProductController';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

type DashboardEntity = {
    id: string;
    name: string | null;
    slug: string | null;
    status: string;
    created_at: string | null;
};

const props = defineProps<{
    totals: {
        planograms: number;
        categories: number;
        products: number;
    };
    status_chart: {
        planograms: Array<{ status: string; total: number }>;
        categories: Array<{ status: string; total: number }>;
        products: Array<{ status: string; total: number }>;
    };
    recent: {
        planograms: DashboardEntity[];
        categories: DashboardEntity[];
        products: DashboardEntity[];
    };
}>();

const { t } = useT();
const page = usePage();
const dashboardPath = dashboard.url().replace(/^\/\/[^/]+/, '');
const subdomain = computed(() => {
    const tenant = page.props.tenant as { slug?: string } | undefined;

    return tenant?.slug ?? '';
});

setLayoutProps({
    breadcrumbs: [
        {
            title: t('app.navigation.dashboard'),
            href: dashboardPath,
        },
    ],
});

const statusLabel = (status: string): string => {
    if (status === 'published') {
        return 'Publicado';
    }

    if (status === 'archived') {
        return 'Arquivado';
    }

    return 'Rascunho';
};

const statusBadgeClass = (status: string): string => {
    if (status === 'published') {
        return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (status === 'archived') {
        return 'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300';
    }

    return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
};

const maxStatusTotal = computed(() => {
    const all = [
        ...props.status_chart.planograms,
        ...props.status_chart.categories,
        ...props.status_chart.products,
    ];

    return Math.max(...all.map((item) => item.total), 1);
});
</script>

<template>
    <Head :title="t('app.navigation.dashboard')" />
    <AppLayout
        :breadcrumbs="[
            {
                title: t('app.navigation.dashboard'),
                href: dashboardPath,
            },
        ]"
        :page-header="{ title: t('app.navigation.dashboard'), description: t('app.navigation.dashboard_description') }"
    >
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
            <section class="grid gap-4 md:grid-cols-3">
                <article class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between text-muted-foreground">
                        <span class="text-sm">Planogramas</span>
                        <LayoutTemplate class="size-4" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold">{{ props.totals.planograms }}</p>
                </article>
                <article class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between text-muted-foreground">
                        <span class="text-sm">Categorias</span>
                        <Shapes class="size-4" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold">{{ props.totals.categories }}</p>
                </article>
                <article class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between text-muted-foreground">
                        <span class="text-sm">Produtos</span>
                        <Package class="size-4" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold">{{ props.totals.products }}</p>
                </article>
            </section>

            <section class="grid gap-4 xl:grid-cols-3">
                <article class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <header class="mb-4 flex items-center justify-between gap-2">
                        <h2 class="text-base font-semibold">Status de planogramas</h2>
                        <Link :href="PlanogramController.index.url(subdomain)" class="text-xs text-primary underline-offset-2 hover:underline">Ver lista</Link>
                    </header>
                    <div class="space-y-3">
                        <div v-for="item in props.status_chart.planograms" :key="`plan-${item.status}`" class="grid grid-cols-[90px_1fr_auto] items-center gap-3">
                            <span class="text-xs text-muted-foreground">{{ statusLabel(item.status) }}</span>
                            <div class="h-2 rounded-full bg-muted">
                                <div class="h-full rounded-full bg-primary" :style="{ width: `${(item.total / maxStatusTotal) * 100}%` }" />
                            </div>
                            <span class="text-xs font-medium">{{ item.total }}</span>
                        </div>
                    </div>
                </article>

                <article class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <header class="mb-4 flex items-center justify-between gap-2">
                        <h2 class="text-base font-semibold">Status de categorias</h2>
                        <Link :href="CategoryController.index.url(subdomain)" class="text-xs text-primary underline-offset-2 hover:underline">Ver lista</Link>
                    </header>
                    <div class="space-y-3">
                        <div v-for="item in props.status_chart.categories" :key="`cat-${item.status}`" class="grid grid-cols-[90px_1fr_auto] items-center gap-3">
                            <span class="text-xs text-muted-foreground">{{ statusLabel(item.status) }}</span>
                            <div class="h-2 rounded-full bg-muted">
                                <div class="h-full rounded-full bg-primary" :style="{ width: `${(item.total / maxStatusTotal) * 100}%` }" />
                            </div>
                            <span class="text-xs font-medium">{{ item.total }}</span>
                        </div>
                    </div>
                </article>

                <article class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <header class="mb-4 flex items-center justify-between gap-2">
                        <h2 class="text-base font-semibold">Status de produtos</h2>
                        <Link :href="ProductController.index.url(subdomain)" class="text-xs text-primary underline-offset-2 hover:underline">Ver lista</Link>
                    </header>
                    <div class="space-y-3">
                        <div v-for="item in props.status_chart.products" :key="`prod-${item.status}`" class="grid grid-cols-[90px_1fr_auto] items-center gap-3">
                            <span class="text-xs text-muted-foreground">{{ statusLabel(item.status) }}</span>
                            <div class="h-2 rounded-full bg-muted">
                                <div class="h-full rounded-full bg-primary" :style="{ width: `${(item.total / maxStatusTotal) * 100}%` }" />
                            </div>
                            <span class="text-xs font-medium">{{ item.total }}</span>
                        </div>
                    </div>
                </article>
            </section>

            <section class="grid gap-4 xl:grid-cols-3">
                <article class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <header class="mb-3 text-sm font-semibold">Planogramas recentes</header>
                    <div class="space-y-2">
                        <div v-if="props.recent.planograms.length === 0" class="text-xs text-muted-foreground">Nenhum planograma recente.</div>
                        <div v-for="item in props.recent.planograms" :key="item.id" class="rounded-md border border-border/70 p-2">
                            <div class="text-sm font-medium">{{ item.name ?? '-' }}</div>
                            <div class="text-xs text-muted-foreground">{{ item.slug ?? '-' }}</div>
                            <span class="mt-1 inline-flex rounded-full border px-2 py-0.5 text-[11px]" :class="statusBadgeClass(item.status)">{{ statusLabel(item.status) }}</span>
                        </div>
                    </div>
                </article>

                <article class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <header class="mb-3 text-sm font-semibold">Categorias recentes</header>
                    <div class="space-y-2">
                        <div v-if="props.recent.categories.length === 0" class="text-xs text-muted-foreground">Nenhuma categoria recente.</div>
                        <div v-for="item in props.recent.categories" :key="item.id" class="rounded-md border border-border/70 p-2">
                            <div class="text-sm font-medium">{{ item.name ?? '-' }}</div>
                            <div class="text-xs text-muted-foreground">{{ item.slug ?? '-' }}</div>
                            <span class="mt-1 inline-flex rounded-full border px-2 py-0.5 text-[11px]" :class="statusBadgeClass(item.status)">{{ statusLabel(item.status) }}</span>
                        </div>
                    </div>
                </article>

                <article class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <header class="mb-3 text-sm font-semibold">Produtos recentes</header>
                    <div class="space-y-2">
                        <div v-if="props.recent.products.length === 0" class="text-xs text-muted-foreground">Nenhum produto recente.</div>
                        <div v-for="item in props.recent.products" :key="item.id" class="rounded-md border border-border/70 p-2">
                            <div class="text-sm font-medium">{{ item.name ?? '-' }}</div>
                            <div class="text-xs text-muted-foreground">{{ item.slug ?? '-' }}</div>
                            <span class="mt-1 inline-flex rounded-full border px-2 py-0.5 text-[11px]" :class="statusBadgeClass(item.status)">{{ statusLabel(item.status) }}</span>
                        </div>
                    </div>
                </article>
            </section>
        </div>
    </AppLayout>
</template>

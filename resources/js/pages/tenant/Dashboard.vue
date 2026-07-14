<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { LayoutTemplate, Shapes, Package } from 'lucide-vue-next';
import { computed } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/Tenant/CategoryController';
import PlanogramController from '@/actions/App/Http/Controllers/Tenant/PlanogramController';
import ProductController from '@/actions/App/Http/Controllers/Tenant/ProductController';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

type DashboardEntity = {
    id: string;
    name: string | null;
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
    useful_links: Array<{
        id: string;
        name: string;
        url: string;
        logo: string | null;
        description: string | null;
    }>;
}>();

const { t } = useT();
const dashboardPath = dashboard.url().replace(/^\/\/[^/]+/, '');

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
        return t('app.tenant.dashboard.status_published');
    }

    if (status === 'archived') {
        return t('app.tenant.dashboard.status_archived');
    }

    return t('app.tenant.dashboard.status_draft');
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
                        <span class="text-sm">{{ t('app.tenant.dashboard.planograms') }}</span>
                        <LayoutTemplate class="size-4" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold">{{ props.totals.planograms }}</p>
                </article>
                <article class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between text-muted-foreground">
                        <span class="text-sm">{{ t('app.tenant.dashboard.categories') }}</span>
                        <Shapes class="size-4" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold">{{ props.totals.categories }}</p>
                </article>
                <article class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between text-muted-foreground">
                        <span class="text-sm">{{ t('app.tenant.dashboard.products') }}</span>
                        <Package class="size-4" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold">{{ props.totals.products }}</p>
                </article>
            </section>

            <section class="grid gap-4 xl:grid-cols-3">
                <article class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <header class="mb-4 flex items-center justify-between gap-2">
                        <h2 class="text-base font-semibold">{{ t('app.tenant.dashboard.planogram_status') }}</h2>
                        <Link :href="PlanogramController.index.url()" class="text-xs text-primary underline-offset-2 hover:underline">{{ t('app.tenant.dashboard.view_list') }}</Link>
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
                        <h2 class="text-base font-semibold">{{ t('app.tenant.dashboard.category_status') }}</h2>
                        <Link :href="CategoryController.index.url()" class="text-xs text-primary underline-offset-2 hover:underline">{{ t('app.tenant.dashboard.view_list') }}</Link>
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
                        <h2 class="text-base font-semibold">{{ t('app.tenant.dashboard.product_status') }}</h2>
                        <Link :href="ProductController.index.url()" class="text-xs text-primary underline-offset-2 hover:underline">{{ t('app.tenant.dashboard.view_list') }}</Link>
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
                    <header class="mb-3 text-sm font-semibold">{{ t('app.tenant.dashboard.recent_planograms') }}</header>
                    <div class="space-y-2">
                        <div v-if="props.recent.planograms.length === 0" class="text-xs text-muted-foreground">{{ t('app.tenant.dashboard.no_recent_planograms') }}</div>
                        <div v-for="item in props.recent.planograms" :key="item.id" class="rounded-md border border-border/70 p-2">
                            <div class="text-sm font-medium">{{ item.name ?? '-' }}</div>
                            <div class="text-xs text-muted-foreground">{{ item.slug ?? '-' }}</div>
                            <span class="mt-1 inline-flex rounded-full border px-2 py-0.5 text-[11px]" :class="statusBadgeClass(item.status)">{{ statusLabel(item.status) }}</span>
                        </div>
                    </div>
                </article>

                <article class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <header class="mb-3 text-sm font-semibold">{{ t('app.tenant.dashboard.recent_categories') }}</header>
                    <div class="space-y-2">
                        <div v-if="props.recent.categories.length === 0" class="text-xs text-muted-foreground">{{ t('app.tenant.dashboard.no_recent_categories') }}</div>
                        <div v-for="item in props.recent.categories" :key="item.id" class="rounded-md border border-border/70 p-2">
                            <div class="text-sm font-medium">{{ item.name ?? '-' }}</div>
                            <div class="text-xs text-muted-foreground">{{ item.slug ?? '-' }}</div>
                            <span class="mt-1 inline-flex rounded-full border px-2 py-0.5 text-[11px]" :class="statusBadgeClass(item.status)">{{ statusLabel(item.status) }}</span>
                        </div>
                    </div>
                </article>

                <article class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <header class="mb-3 text-sm font-semibold">{{ t('app.tenant.dashboard.recent_products') }}</header>
                    <div class="space-y-2">
                        <div v-if="props.recent.products.length === 0" class="text-xs text-muted-foreground">{{ t('app.tenant.dashboard.no_recent_products') }}</div>
                        <div v-for="item in props.recent.products" :key="item.id" class="rounded-md border border-border/70 p-2">
                            <div class="text-sm font-medium">{{ item.name ?? '-' }}</div>
                            <div class="text-xs text-muted-foreground">{{ item.slug ?? '-' }}</div>
                            <span class="mt-1 inline-flex rounded-full border px-2 py-0.5 text-[11px]" :class="statusBadgeClass(item.status)">{{ statusLabel(item.status) }}</span>
                        </div>
                    </div>
                </article>
            </section>

            <section class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                <header class="mb-4">
                    <h2 class="text-base font-semibold">{{ t('app.tenant.dashboard.useful_links') }}</h2>
                </header>
                <div v-if="props.useful_links.length === 0" class="text-xs text-muted-foreground">
                    {{ t('app.tenant.dashboard.no_links') }}
                </div>
                <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <a
                        v-for="link in props.useful_links"
                        :key="link.id"
                        :href="link.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="rounded-lg border border-border/70 p-3 transition hover:bg-muted/30"
                    >
                        <div class="mb-2 flex items-center gap-2">
                            <img
                                v-if="link.logo"
                                :src="link.logo"
                                :alt="link.name"
                                class="size-8 rounded object-cover"
                            />
                            <div class="text-sm font-medium">{{ link.name }}</div>
                        </div>
                        <p v-if="link.description" class="text-xs text-muted-foreground">{{ link.description }}</p>
                    </a>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

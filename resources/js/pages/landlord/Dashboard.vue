<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { ExternalLink, Building2, CircleCheck, LoaderCircle, CircleOff } from 'lucide-vue-next';
import TenantController from '@/actions/App/Http/Controllers/Landlord/TenantController';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

const props = defineProps<{
    totals: {
        all: number;
        active: number;
        provisioning: number;
        inactive: number;
    };
    status_chart: Array<{
        status: string;
        total: number;
    }>;
    tenants_by_month: Array<{
        month: string;
        total: number;
    }>;
    recent_tenants: Array<{
        id: string;
        name: string;
        slug: string;
        status: string;
        plan: string | null;
        plan_user_limit: number | null;
        users_count: number;
        kanban_enabled: boolean;
        workflow_in_use: boolean;
        workflow_usage_count: number;
        integration_active: boolean;
        host: string | null;
        client_since_human: string | null;
        created_at: string | null;
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

const maxStatusTotal = computed(() => Math.max(...props.status_chart.map((item) => item.total), 1));
const maxMonthlyTotal = computed(() => Math.max(...props.tenants_by_month.map((item) => item.total), 1));
const monthlyPoints = computed(() => {
    if (props.tenants_by_month.length === 0) {
        return '';
    }

    return props.tenants_by_month
        .map((item, index) => {
            const x = props.tenants_by_month.length === 1 ? 0 : (index / (props.tenants_by_month.length - 1)) * 100;
            const y = 100 - (item.total / maxMonthlyTotal.value) * 100;

            return `${x},${Math.max(0, Math.min(100, y))}`;
        })
        .join(' ');
});

const statusLabel = (status: string): string => {
    return t(`app.landlord.tenant_statuses.${status}`);
};

const statusColorClass = (status: string): string => {
    if (status === 'active') {
        return 'bg-emerald-500';
    }

    if (status === 'provisioning') {
        return 'bg-amber-500';
    }

    if (status === 'suspended') {
        return 'bg-rose-500';
    }

    return 'bg-slate-500';
};

const statusBadgeClass = (status: string): string => {
    if (status === 'active') {
        return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (status === 'provisioning') {
        return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    if (status === 'suspended') {
        return 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300';
    }

    return 'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300';
};
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
            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between text-muted-foreground">
                        <span class="text-sm">Total de tenants</span>
                        <Building2 class="size-4" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold">{{ props.totals.all }}</p>
                </article>
                <article class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between text-muted-foreground">
                        <span class="text-sm">Ativos</span>
                        <CircleCheck class="size-4" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold">{{ props.totals.active }}</p>
                </article>
                <article class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between text-muted-foreground">
                        <span class="text-sm">Provisionando</span>
                        <LoaderCircle class="size-4" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold">{{ props.totals.provisioning }}</p>
                </article>
                <article class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between text-muted-foreground">
                        <span class="text-sm">Inativos / suspensos</span>
                        <CircleOff class="size-4" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold">{{ props.totals.inactive }}</p>
                </article>
            </section>

            <section class="grid gap-4 xl:grid-cols-2">
                <article class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <header class="mb-4">
                        <h2 class="text-base font-semibold">Tenants por status</h2>
                        <p class="text-sm text-muted-foreground">Distribuição atual dos ambientes</p>
                    </header>

                    <div class="space-y-3">
                        <div
                            v-for="item in props.status_chart"
                            :key="item.status"
                            class="grid grid-cols-[130px_1fr_auto] items-center gap-3"
                        >
                            <span class="truncate text-sm text-muted-foreground">{{ statusLabel(item.status) }}</span>
                            <div class="h-2 rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="statusColorClass(item.status)"
                                    :style="{ width: `${(item.total / maxStatusTotal) * 100}%` }"
                                />
                            </div>
                            <span class="text-sm font-medium">{{ item.total }}</span>
                        </div>
                    </div>
                </article>

                <article class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <header class="mb-4">
                        <h2 class="text-base font-semibold">Novos tenants (6 meses)</h2>
                        <p class="text-sm text-muted-foreground">Evolução recente de criação</p>
                    </header>

                    <div class="rounded-lg border border-border/60 bg-background/70 p-3">
                        <svg viewBox="0 0 100 100" class="h-40 w-full" preserveAspectRatio="none">
                            <polyline
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                class="text-primary"
                                :points="monthlyPoints"
                            />
                            <circle
                                v-for="(item, index) in props.tenants_by_month"
                                :key="item.month"
                                :cx="props.tenants_by_month.length === 1 ? 0 : (index / (props.tenants_by_month.length - 1)) * 100"
                                :cy="100 - (item.total / maxMonthlyTotal) * 100"
                                r="1.8"
                                class="fill-primary"
                            />
                        </svg>
                        <div class="mt-2 grid grid-cols-6 text-center text-xs text-muted-foreground">
                            <span v-for="item in props.tenants_by_month" :key="item.month">{{ item.month }}</span>
                        </div>
                    </div>
                </article>
            </section>

            <section class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                <header class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold">Tenants recentes</h2>
                        <p class="text-sm text-muted-foreground">Acesso rápido para edição e setup</p>
                    </div>
                    <Link
                        :href="TenantController.index.url()"
                        class="rounded-md border border-border px-3 py-1.5 text-sm font-medium transition hover:bg-muted"
                    >
                        Ver todos
                    </Link>
                </header>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-border text-left text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2 font-medium">Tenant</th>
                                <th class="px-3 py-2 font-medium">Status</th>
                                <th class="px-3 py-2 font-medium">Plano</th>
                                <th class="px-3 py-2 font-medium">Usuários</th>
                                <th class="px-3 py-2 font-medium">Módulos</th>
                                <th class="px-3 py-2 font-medium">Integração</th>
                                <th class="px-3 py-2 font-medium">Cliente há</th>
                                <th class="px-3 py-2 font-medium">Host</th>
                                <th class="px-3 py-2 text-right font-medium">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="props.recent_tenants.length === 0">
                                <td colspan="10" class="px-3 py-6 text-center text-muted-foreground">Nenhum tenant encontrado.</td>
                            </tr>
                            <tr
                                v-for="tenant in props.recent_tenants"
                                :key="tenant.id"
                                class="border-b border-border/60 last:border-b-0"
                            >
                                <td class="px-3 py-3">
                                    <div class="font-medium">{{ tenant.name }}</div>
                                    <div class="text-xs text-muted-foreground">{{ tenant.slug }}</div>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium" :class="statusBadgeClass(tenant.status)">
                                        {{ statusLabel(tenant.status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-3">{{ tenant.plan ?? '-' }}</td>
                                <td class="px-3 py-3">
                                    <span class="font-medium">{{ tenant.users_count }}</span>
                                    <span class="text-muted-foreground"> / {{ tenant.plan_user_limit ?? 'sem limite' }}</span>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-col gap-1 text-xs">
                                        <span class="inline-flex w-fit items-center rounded-full border px-2 py-0.5" :class="tenant.kanban_enabled ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300'">
                                            Kanban: {{ tenant.kanban_enabled ? 'ativo' : 'inativo' }}
                                        </span>
                                        <span class="inline-flex w-fit items-center rounded-full border px-2 py-0.5" :class="tenant.workflow_in_use ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300'">
                                            Workflow: {{ tenant.workflow_in_use ? `em uso (${tenant.workflow_usage_count})` : 'sem uso' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium" :class="tenant.integration_active ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300'">
                                        {{ tenant.integration_active ? 'ativa' : 'inativa' }}
                                    </span>
                                </td>
                                <td class="px-3 py-3">{{ tenant.client_since_human ?? '-' }}</td>
                                <td class="px-3 py-3">{{ tenant.host ?? '-' }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link
                                            :href="TenantController.edit.url({ tenant: tenant.id })"
                                            class="rounded-md border border-border px-2.5 py-1.5 text-xs font-medium transition hover:bg-muted"
                                        >
                                            Editar
                                        </Link>
                                        <a
                                            :href="TenantController.setup.url({ tenant: tenant.id })"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1 rounded-md border border-border px-2.5 py-1.5 text-xs font-medium transition hover:bg-muted"
                                        >
                                            Abrir em nova aba
                                            <ExternalLink class="size-3.5" />
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

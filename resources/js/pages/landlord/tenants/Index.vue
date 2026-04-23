<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import TenantController from '@/actions/App/Http/Controllers/Landlord/TenantController';
import TenantUserAccessController from '@/actions/App/Http/Controllers/Landlord/TenantUserAccessController';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import type { Paginator } from '@/types';

type TenantRow = {
    id: string;
    name: string;
    slug: string;
    database: string;
    status: string;
    user_limit: number | null;
    plan: { id: string; name: string } | null;
    primary_domain: { id: string; host: string; is_active: boolean } | null;
};

const props = defineProps<{
    tenants: Paginator<TenantRow>;
    filters: {
        search: string;
        status: string;
        plan_id: string;
    };
    filter_options: {
        statuses: Array<{ value: string; label: string }>;
        plans: Array<{ id: string; name: string }>;
    };
}>();

const { t } = useT();
const tenantsIndexPath = TenantController.index.url().replace(/^\/\/[^/]+/, '');

setLayoutProps({
    breadcrumbs: [
        {
            title: t('app.landlord.tenants.navigation'),
            href: tenantsIndexPath,
        },
    ],
});
</script>

<template>
    <Head :title="t('app.landlord.tenants.title')" />

    <ListPage
        :title="t('app.landlord.tenants.title')"
        :description="t('app.landlord.tenants.description')"
        :meta="props.tenants"
        label="tenant"
        :action="tenantsIndexPath"
        :clear-href="tenantsIndexPath"
        :search-value="props.filters.search"
        :search-placeholder="t('app.landlord.common.search')"
        :filter-label="t('app.landlord.common.filter')"
        :clear-label="t('app.landlord.common.clear_filters')"
    >
        <template #action>
            <NewActionButton :href="TenantController.create.url()">
                {{ t('app.landlord.tenants.actions.new') }}
            </NewActionButton>
        </template>

        <template #filters>
            <select
                name="status"
                :value="props.filters.status"
                class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
            >
                <option value="">{{ t('app.landlord.common.all') }}</option>
                <option v-for="status in props.filter_options.statuses" :key="status.value" :value="status.value">
                    {{ status.label }}
                </option>
            </select>

            <select
                name="plan_id"
                :value="props.filters.plan_id"
                class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
            >
                <option value="">{{ t('app.landlord.common.all') }}</option>
                <option v-for="plan in props.filter_options.plans" :key="plan.id" :value="plan.id">
                    {{ plan.name }}
                </option>
            </select>
        </template>

        <table class="w-full text-sm">
            <thead class="bg-muted/30 text-left text-muted-foreground">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ t('app.landlord.tenants.fields.name') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.landlord.tenants.fields.status') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.landlord.tenants.fields.database') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.landlord.tenants.fields.host') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.landlord.tenants.fields.plan') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ t('app.landlord.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="props.tenants.data.length === 0">
                    <td class="px-4 py-6 text-muted-foreground" colspan="6">
                        {{ t('app.landlord.common.empty') }}
                    </td>
                </tr>
                <tr
                    v-for="tenant in props.tenants.data"
                    :key="tenant.id"
                    class="border-t border-sidebar-border/60 dark:border-sidebar-border"
                >
                    <td class="px-4 py-3">
                        <div class="font-medium">{{ tenant.name }}</div>
                        <div class="text-xs text-muted-foreground">{{ tenant.slug }}</div>
                    </td>
                    <td class="px-4 py-3">{{ t(`app.landlord.tenant_statuses.${tenant.status}`) }}</td>
                    <td class="px-4 py-3">{{ tenant.database }}</td>
                    <td class="px-4 py-3">
                        <div>{{ tenant.primary_domain?.host ?? '-' }}</div>
                        <div class="text-xs text-muted-foreground">
                            {{ tenant.primary_domain?.is_active ? t('app.landlord.common.active') : t('app.landlord.common.inactive') }}
                        </div>
                    </td>
                    <td class="px-4 py-3">{{ tenant.plan?.name ?? '-' }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex items-center gap-2">
                            <Button variant="secondary" size="sm" as-child>
                                <Link :href="TenantUserAccessController.edit.url(tenant.id)">
                                    {{ t('app.landlord.common.access') }}
                                </Link>
                            </Button>
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="TenantController.edit.url(tenant.id)">
                                    {{ t('app.landlord.common.edit') }}
                                </Link>
                            </Button>
                            <Button variant="destructive" size="sm" as-child>
                                <Link
                                    :href="TenantController.destroy.url(tenant.id)"
                                    method="delete"
                                    as="button"
                                >
                                    {{ t('app.landlord.common.delete') }}
                                </Link>
                            </Button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </ListPage>
</template>

<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import TenantController from '@/actions/App/Http/Controllers/Landlord/TenantController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';

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

type Paginator<T> = {
    data: T[];
};

defineProps<{
    tenants: Paginator<TenantRow>;
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

    <div class="space-y-6 p-4">
        <div class="flex items-center justify-between gap-4">
            <Heading :title="t('app.landlord.tenants.title')" :description="t('app.landlord.tenants.description')" />

            <Button as-child>
                <Link :href="TenantController.create.url()">
                    {{ t('app.landlord.tenants.actions.new') }}
                </Link>
            </Button>
        </div>

        <div class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
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
                    <tr v-if="tenants.data.length === 0">
                        <td class="px-4 py-6 text-muted-foreground" colspan="6">
                            {{ t('app.landlord.common.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="tenant in tenants.data"
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
        </div>
    </div>
</template>

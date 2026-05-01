<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PlanController from '@/actions/App/Http/Controllers/Landlord/PlanController';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
import { Button } from '@/components/ui/button';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useDeferredPaginator } from '@/composables/useDeferredPaginator';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Paginator } from '@/types';

type PlanRow = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    price_cents: number;
    user_limit: number | null;
    is_active: boolean;
    tenants_count: number;
};

const props = defineProps<{
    plans?: Paginator<PlanRow>;
    filters: {
        search: string;
        is_active: string;
    };
}>();

const { t } = useT();
const plansIndexPath = PlanController.index.url().replace(/^\/\/[^/]+/, '');
const { meta: plansMeta, rows: plansRows, loading: plansLoading } = useDeferredPaginator(() => props.plans, 10);
const pageMeta = useCrudPageMeta({
    headTitle: t('app.landlord.plans.title'),
    title: t('app.landlord.plans.title'),
    description: t('app.landlord.plans.description'),
    breadcrumbs: [
        {
            title: t('app.landlord.plans.navigation'),
            href: plansIndexPath,
        },
    ],
});

function formatPrice(cents: number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(cents / 100);
}
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex items-center justify-end gap-2">
                <NewActionButton :href="PlanController.create.url()">
                    {{ t('app.landlord.plans.actions.new') }}
                </NewActionButton>
            </div>
        </template>

        <ListPage
            :meta="plansMeta"
        label="plano"
        :action="plansIndexPath"
        :clear-href="plansIndexPath"
        :search-value="props.filters.search"
        :search-placeholder="t('app.landlord.common.search')"
        :filter-label="t('app.landlord.common.filter')"
        :clear-label="t('app.landlord.common.clear_filters')"
        :show-trashed-filter="false"
    >
        <template #filters>
            <select
                name="is_active"
                :value="props.filters.is_active"
                class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
            >
                <option value="">{{ t('app.landlord.common.all') }}</option>
                <option value="1">{{ t('app.landlord.common.active') }}</option>
                <option value="0">{{ t('app.landlord.common.inactive') }}</option>
            </select>
        </template>

        <table class="w-full text-sm">
            <thead class="bg-muted/30 text-left text-muted-foreground">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ t('app.landlord.plans.fields.name') }}</th>
                    <th class="px-4 py-3 font-medium">Slug</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.landlord.plans.fields.price_cents') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.landlord.plans.fields.user_limit') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.landlord.plans.fields.is_active') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.landlord.plans.fields.tenants_count') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ t('app.landlord.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <template v-if="plansLoading">
                    <TableLoadingSkeleton :columns="7" :rows="6" />
                </template>
                <tr v-else-if="plansRows.length === 0">
                    <td class="px-4 py-6 text-muted-foreground" colspan="7">
                        {{ t('app.landlord.common.empty') }}
                    </td>
                </tr>
                <tr
                    v-for="plan in plansRows"
                    :key="plan.id"
                    class="border-t border-sidebar-border/60 dark:border-sidebar-border"
                >
                    <td class="px-4 py-3">
                        <div class="font-medium">{{ plan.name }}</div>
                        <div v-if="plan.description" class="text-xs text-muted-foreground">{{ plan.description }}</div>
                    </td>
                    <td class="px-4 py-3">{{ plan.slug }}</td>
                    <td class="px-4 py-3">{{ formatPrice(plan.price_cents) }}</td>
                    <td class="px-4 py-3">{{ plan.user_limit ?? '-' }}</td>
                    <td class="px-4 py-3">{{ plan.is_active ? t('app.landlord.common.yes') : t('app.landlord.common.no') }}</td>
                    <td class="px-4 py-3">{{ plan.tenants_count }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex items-center gap-2">
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="PlanController.edit.url(plan.id)">
                                    {{ t('app.landlord.common.edit') }}
                                </Link>
                            </Button>
                            <Button variant="destructive" size="sm" as-child>
                                <Link
                                    :href="PlanController.destroy.url(plan.id)"
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
    </AppLayout>
</template>

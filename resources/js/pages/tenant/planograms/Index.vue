<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, LayoutTemplate, Store } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import GondolaController from '@/actions/App/Http/Controllers/Tenant/GondolaController';
import PlanogramController from '@/actions/App/Http/Controllers/Tenant/PlanogramController';
import DeleteButton from '@/components/DeleteButton.vue';
import EditButton from '@/components/EditButton.vue';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type PlanogramRow = {
    id: string;
    name: string | null;
    slug: string | null;
    type: 'realograma' | 'planograma';
    store: string | null;
    cluster: string | null;
    category: string | null;
    start_date: string | null;
    end_date: string | null;
    status: 'draft' | 'published';
};

const props = defineProps<{
    subdomain: string;
    planograms: Paginator<PlanogramRow>;
    filters: {
        search: string;
        status: string;
        type: string;
        store_id: string;
    };
    filter_options: {
        stores: Array<{ id: string; name: string }>;
    };
}>();

const { t } = useT();
const planogramsIndexPath = PlanogramController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');

function formatDate(date: string | null): string {
    if (!date) return '-';
    return new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(date + 'T00:00:00'));
}

function statusVariant(status: PlanogramRow['status']): 'default' | 'outline' {
    return status === 'published' ? 'default' : 'outline';
}

const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.planograms.title'),
    title: t('app.tenant.planograms.title'),
    description: t('app.tenant.planograms.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.planograms.navigation'), href: planogramsIndexPath },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex items-center justify-end gap-2">
                <NewActionButton :href="PlanogramController.create.url(props.subdomain)">
                    {{ t('app.tenant.planograms.actions.new') }}
                </NewActionButton>
            </div>
        </template>

        <ListPage
            :meta="props.planograms"
        label="planograma"
        :action="planogramsIndexPath"
        :clear-href="planogramsIndexPath"
        :search-value="props.filters.search"
        :search-placeholder="t('app.tenant.common.search')"
        :filter-label="t('app.tenant.common.filter')"
        :clear-label="t('app.tenant.common.clear_filters')"
    >
        <template #filters>
            <select name="status" :value="props.filters.status" class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20">
                <option value="">{{ t('app.tenant.common.all') }}</option>
                <option value="draft">Draft</option>
                <option value="published">Published</option>
            </select>

            <select name="type" :value="props.filters.type" class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20">
                <option value="">{{ t('app.tenant.common.all') }}</option>
                <option value="planograma">Planograma</option>
                <option value="realograma">Realograma</option>
            </select>

            <select name="store_id" :value="props.filters.store_id" class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20">
                <option value="">{{ t('app.tenant.common.all') }}</option>
                <option v-for="store in props.filter_options.stores" :key="store.id" :value="store.id">
                    {{ store.name }}
                </option>
            </select>
        </template>

        <table class="w-full text-sm">
            <thead class="bg-muted/30 text-left text-muted-foreground">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ t('app.tenant.planograms.fields.name') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.tenant.planograms.fields.type') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.tenant.planograms.fields.store') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.tenant.planograms.fields.period') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.tenant.planograms.fields.status') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ t('app.tenant.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="props.planograms.data.length === 0">
                    <td class="px-4 py-10 text-center text-muted-foreground" colspan="6">
                        {{ t('app.tenant.common.empty') }}
                    </td>
                </tr>
                <tr
                    v-for="planogram in props.planograms.data"
                    :key="planogram.id"
                    class="border-t border-sidebar-border/60 transition-colors hover:bg-muted/20 dark:border-sidebar-border"
                >
                    <!-- Nome + slug -->
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <LayoutTemplate class="size-4 shrink-0 text-muted-foreground" />
                            <div>
                                <p class="font-medium leading-tight">{{ planogram.name ?? '-' }}</p>
                                <p v-if="planogram.category" class="mt-0.5 text-xs text-muted-foreground">{{ planogram.category }}</p>
                            </div>
                        </div>
                    </td>

                    <!-- Tipo -->
                    <td class="px-4 py-3">
                        <Badge variant="secondary" class="capitalize">
                            {{ planogram.type }}
                        </Badge>
                    </td>

                    <!-- Loja -->
                    <td class="px-4 py-3">
                        <div v-if="planogram.store" class="flex items-center gap-1.5 text-muted-foreground">
                            <Store class="size-3.5 shrink-0" />
                            <span>{{ planogram.store }}</span>
                        </div>
                        <span v-else class="text-muted-foreground">—</span>
                    </td>

                    <!-- Período -->
                    <td class="px-4 py-3">
                        <div class="flex items-start gap-1.5 text-muted-foreground">
                            <CalendarDays class="mt-0.5 size-3.5 shrink-0" />
                            <div class="leading-snug">
                                <span>{{ formatDate(planogram.start_date) }}</span>
                                <span class="mx-1 text-muted-foreground/50">→</span>
                                <span>{{ formatDate(planogram.end_date) }}</span>
                            </div>
                        </div>
                    </td>

                    <!-- Status -->
                    <td class="px-4 py-3">
                        <Badge :variant="statusVariant(planogram.status)" class="capitalize">
                            {{ planogram.status }}
                        </Badge>
                    </td>

                    <!-- Ações -->
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex items-center gap-2">
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="GondolaController.index.url({ subdomain: props.subdomain, planogram: planogram.id })">
                                    {{ t('app.tenant.planograms.actions.view_gondolas') }}
                                </Link>
                            </Button>
                            <EditButton :href="PlanogramController.edit.url({ subdomain: props.subdomain, planogram: planogram.id })" />
                            <DeleteButton
                                :href="PlanogramController.destroy.url({ subdomain: props.subdomain, planogram: planogram.id })"
                                :label="planogram.name ?? undefined"
                                require-confirm-word
                            />
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        </ListPage>
    </AppLayout>
</template>

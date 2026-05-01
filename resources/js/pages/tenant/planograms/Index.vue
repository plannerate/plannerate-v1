<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, LayoutTemplate, SlidersHorizontal, Store, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import GondolaController from '@/actions/App/Http/Controllers/Tenant/GondolaController';
import PlanogramController from '@/actions/App/Http/Controllers/Tenant/PlanogramController';
import WorkflowKanbanController from '@/actions/App/Http/Controllers/Tenant/WorkflowKanbanController';
import KankanNavigationLinks from '@/components/KankanNavigationLinks.vue';
import ListTablePage from '@/components/ListPage.vue';
import type ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { ColumnActions, ColumnDate, ColumnLabel, ColumnStatusBadge } from '@/components/table/columns';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
import CategoryCascadeSelect from '@/components/tenant/CategoryCascadeSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import Popover from '@/components/ui/popover/Popover.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useDeferredPaginator } from '@/composables/useDeferredPaginator';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
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
    planograms?: Paginator<PlanogramRow>;
    filters: {
        search: string;
        status: string;
        type: string;
        store_id: string;
        category_id: string;
        trashed: 'without' | 'only' | 'with';
    };
    filter_options: {
        stores: Array<{ id: string; name: string }>;
    };
}>();

const { t } = useT();
const page = usePage();
const { meta: planogramsMeta, rows: planogramsRows, loading: planogramsLoading } = useDeferredPaginator(() => props.planograms, 10);

const listPageRef = ref<InstanceType<typeof ListPage> | null>(null);
const categoryId = ref<string | null>(props.filters.category_id ?? null);
const categoryPopoverOpen = ref(false);
const activeModules = computed<string[]>(() => {
    const tenant = (page.props.tenant ?? null) as { active_modules?: string[] } | null;

    return Array.isArray(tenant?.active_modules) ? tenant.active_modules : [];
});
const canUseKanban = computed(() => activeModules.value.includes('kanban'));

watch(categoryId, (value, prev) => {
    if (value !== prev) {
        categoryPopoverOpen.value = false;
        nextTick(() => listPageRef.value?.submitForm());
    }
});

const categoryLabel = computed(() => {
    if (!categoryId.value) {
        return t('app.tenant.products.fields.category');
    }

    return t('app.tenant.products.fields.category') + ' ✓';
});
const planogramsIndexPath = PlanogramController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');

function planogramWorkflowHref(planogramId: string): string {
    if (canUseKanban.value) {
        return WorkflowKanbanController.index.url(props.subdomain, {
            query: { planogram_id: planogramId },
        }).replace(/^\/\/[^/]+/, '');
    }

    return GondolaController.index.url({ subdomain: props.subdomain, planogram: planogramId });
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
        <KankanNavigationLinks :subdomain="props.subdomain" />
        <template #header-actions>
            <div class="flex items-center justify-end gap-2">
                <Button variant="outline" as-child>
                    <Link :href="PlanogramController.orphanLayers.url(props.subdomain)">
                        Layers órfãs
                    </Link>
                </Button>
                <NewActionButton :href="PlanogramController.create.url(props.subdomain)">
                    {{ t('app.tenant.planograms.actions.new') }}
                </NewActionButton>
            </div>
        </template>

        <ListTablePage
            ref="listPageRef"
            :meta="planogramsMeta"
            label="planograma"
            :action="planogramsIndexPath"
            :clear-href="planogramsIndexPath"
            :search-value="props.filters.search"
            :search-placeholder="t('app.tenant.common.search')"
            :filter-label="t('app.tenant.common.filter')"
            :clear-label="t('app.tenant.common.clear_filters')"
            :trashed-value="props.filters.trashed"
        >
            <template #filters>
                <input type="hidden" name="category_id" :value="categoryId ?? ''" />

                <Popover v-model:open="categoryPopoverOpen">
                    <PopoverTrigger as-child>
                        <button
                            type="button"
                            class="flex h-9 items-center gap-1.5 rounded-lg border border-border bg-background px-3 text-sm text-foreground transition hover:bg-muted"
                            :class="categoryId ? 'border-primary/60 text-primary' : ''"
                        >
                            <SlidersHorizontal class="size-3.5 shrink-0" />
                            <span>{{ categoryLabel }}</span>
                            <button
                                v-if="categoryId"
                                type="button"
                                class="ml-1 rounded-sm opacity-60 hover:opacity-100"
                                @click.stop="categoryId = null"
                            >
                                <X class="size-3" />
                            </button>
                            <ChevronDown v-else class="size-3.5 shrink-0 opacity-50" />
                        </button>
                    </PopoverTrigger>
                    <PopoverContent class="w-170 p-4" align="start">
                        <p class="mb-3 text-sm font-medium">{{ t('app.tenant.products.form.sections.category') }}</p>
                        <CategoryCascadeSelect
                            v-model="categoryId"
                        />
                        <div class="mt-4 flex justify-end gap-2">
                            <button
                                type="button"
                                class="rounded-md px-3 py-1.5 text-sm hover:bg-muted"
                                @click="categoryId = null; categoryPopoverOpen = false"
                            >
                                {{ t('app.tenant.common.clear_filters') }}
                            </button>
                            <button
                                type="button"
                                class="rounded-md bg-primary px-3 py-1.5 text-sm text-primary-foreground hover:bg-primary/90"
                                @click="categoryPopoverOpen = false"
                            >
                                {{ t('app.tenant.common.filter') }}
                            </button>
                        </div>
                    </PopoverContent>
                </Popover>
                <select name="status" :value="props.filters.status" class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20">
                    <option value="">{{ t('app.tenant.common.status') }}</option>
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                </select>

                <select name="type" :value="props.filters.type" class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20">
                    <option value="">{{ t('app.tenant.common.type') }}</option>
                    <option value="planograma">Planograma</option>
                    <option value="realograma">Realograma</option>
                </select>

                <select name="store_id" :value="props.filters.store_id" class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20">
                    <option value="">{{ t('app.tenant.common.store') }}</option>
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
                    <template v-if="planogramsLoading">
                        <TableLoadingSkeleton :columns="6" :rows="6" />
                    </template>
                    <tr v-else-if="planogramsRows.length === 0">
                        <td class="px-4 py-10 text-center text-muted-foreground" colspan="6">
                            {{ t('app.tenant.common.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="planogram in planogramsRows"
                        :key="planogram.id"
                        class="border-t border-sidebar-border/60 transition-colors hover:bg-muted/20 dark:border-sidebar-border"
                    >
                        <!-- Nome + categoria -->
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <LayoutTemplate class="size-4 shrink-0 text-muted-foreground" />
                                <ColumnLabel :label="planogram.name ?? '-'" :description="planogram.category" />
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
                            <ColumnDate :from="planogram.start_date" :to="planogram.end_date" />
                        </td>

                        <!-- Status -->
                        <td class="px-4 py-3">
                            <ColumnStatusBadge :status="planogram.status" />
                        </td>

                        <!-- Ações -->
                        <td class="px-4 py-3 text-right">
                            <ColumnActions
                                :edit-href="PlanogramController.edit.url({ subdomain: props.subdomain, planogram: planogram.id })"
                                :delete-href="PlanogramController.destroy.url({ subdomain: props.subdomain, planogram: planogram.id })"
                                :delete-label="planogram.name ?? undefined"
                                :require-confirm-word="true"
                            >
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="planogramWorkflowHref(planogram.id)">
                                        {{ t('app.tenant.planograms.actions.view_gondolas') }}
                                    </Link>
                                </Button>
                            </ColumnActions>
                        </td>
                    </tr>
                </tbody>
            </table>
        </ListTablePage>
    </AppLayout>
</template>

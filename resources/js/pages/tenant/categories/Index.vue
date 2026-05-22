<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ChevronDown, SlidersHorizontal, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/Tenant/CategoryController';
import ImportFileButton from '@/components/imports/ImportFileButton.vue';
import PlanLimitAlert from '@/components/PlanLimitAlert.vue';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { ColumnActions, ColumnHeader, ColumnLabel, ColumnStatusBadge } from '@/components/table/columns';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
import CategoryCascadeSelect from '@/components/tenant/CategoryCascadeSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useDeferredPaginator } from '@/composables/useDeferredPaginator';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type CategoryRow = {
    id: string;
    name: string;
    full_path: string | null;
    level_name: string | null;
    status: 'draft' | 'published' | 'importer';
    codigo: number | null;
    is_placeholder: boolean;
};

const props = defineProps<{
    categories?: Paginator<CategoryRow>;
    filters: {
        search: string;
        status: string;
        level_name: string;
        category_id: string;
        trashed: 'without' | 'only' | 'with';
    };
    filter_options: {
        level_names: string[];
    };
    can: {
        create: boolean;
        limit_reached: boolean;
        limit_message: string | null;
        upgrade_url: string | null;
    };
}>();

const { t } = useT();
const { meta: categoriesMeta, rows: categoriesRows, loading: categoriesLoading } = useDeferredPaginator(() => props.categories, 10);
const listPageRef = ref<InstanceType<typeof ListPage> | null>(null);
const categoriesIndexPath = CategoryController.index
    .url()
    .replace(/^\/\/[^/]+/, '');
const categoryId = ref<string | null>(props.filters.category_id ?? null);
const categoryPopoverOpen = ref(false);

watch(categoryId, (value, prev) => {
    if (value !== prev) {
        categoryPopoverOpen.value = false;
        nextTick(() => listPageRef.value?.submitForm());
    }
});

const categoryLabel = computed(() => {
    if (!categoryId.value) {
        return t('app.tenant.categories.fields.category');
    }

    return `${t('app.tenant.categories.fields.category')} ✓`;
});

const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.categories.title'),
    title: t('app.tenant.categories.title'),
    description: t('app.tenant.categories.description'),
    breadcrumbs: [
        {
            title: t('app.navigation.dashboard'),
            href: dashboard.url().replace(/^\/\/[^/]+/, ''),
        },
        {
            title: t('app.tenant.categories.navigation'),
            href: categoriesIndexPath,
        },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex items-center justify-end gap-2">
                <Button variant="outline" size="pill-sm" as-child>
                    <a :href="CategoryController.exportTemplate.url()">
                        {{ t('app.tenant.categories.actions.download_template') }}
                    </a>
                </Button>
                <Button variant="outline" size="pill-sm" as-child>
                    <a :href="CategoryController.exportData.url()">
                        {{ t('app.tenant.categories.actions.export_data') }}
                    </a>
                </Button>
                <ImportFileButton
                    :action="CategoryController.importMethod.url()"
                    :button-label="t('app.tenant.categories.actions.import')"
                    :title="t('app.tenant.categories.import.title')"
                    :description="t('app.tenant.categories.import.description')"
                    :file-label="t('app.tenant.categories.import.file_label')"
                    :submit-label="t('app.tenant.categories.import.submit')"
                    :submitting-label="t('app.tenant.categories.import.submitting')"
                    :cancel-label="t('app.tenant.categories.import.cancel')"
                    show-truncate-option
                    :truncate-label="t('app.tenant.categories.import.truncate_label')"
                    :truncate-warning="t('app.tenant.categories.import.truncate_warning')"
                />
                <NewActionButton v-if="can.create" :href="CategoryController.create.url()">
                    {{ t('app.tenant.categories.actions.new') }}
                </NewActionButton>
            </div>
        </template>

        <PlanLimitAlert v-if="can.limit_reached" :message="can.limit_message!" :upgrade-url="can.upgrade_url" />

        <ListPage
            ref="listPageRef"
            :title="pageMeta.title"
            :description="pageMeta.description"
            :meta="categoriesMeta"
            label="categoria"
            :action="categoriesIndexPath"
            :clear-href="categoriesIndexPath"
            :search-value="props.filters.search"
            :search-placeholder="t('app.tenant.common.search')"
            :filter-label="t('app.tenant.common.filter')"
            :clear-label="t('app.tenant.common.clear_filters')"
            :trashed-value="props.filters.trashed"
        >
            <template #filters>
                <select
                    name="status"
                    :value="props.filters.status"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                >
                    <option value="">{{ t('app.tenant.common.all') }}</option>
                    <option value="draft">{{ t('app.tenant.categories.status_options.draft') }}</option>
                    <option value="published">{{ t('app.tenant.categories.status_options.published') }}</option>
                    <option value="importer">{{ t('app.tenant.categories.status_options.importer') }}</option>
                </select>
                <select
                    name="level_name"
                    :value="props.filters.level_name"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                >
                    <option value="">{{ t('app.tenant.categories.fields.level_name') }}</option>
                    <option v-for="level in props.filter_options.level_names" :key="level" :value="level">
                        {{ level }}
                    </option>
                </select>
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
                        <p class="mb-3 text-sm font-medium">{{ t('app.tenant.categories.fields.category') }}</p>
                        <CategoryCascadeSelect v-model="categoryId" />
                        <div class="mt-4 flex justify-end gap-2">
                            <button
                                type="button"
                                class="rounded-md px-3 py-1.5 text-sm hover:bg-muted"
                                @click="categoryId = null; categoryPopoverOpen = false"
                            >
                                {{ t('app.tenant.common.clear_filters') }}
                            </button>
                            <button
                                type="submit"
                                class="rounded-md bg-primary px-3 py-1.5 text-sm text-primary-foreground hover:bg-primary/90"
                                @click="categoryPopoverOpen = false"
                            >
                                {{ t('app.tenant.common.filter') }}
                            </button>
                        </div>
                    </PopoverContent>
                </Popover>
            </template>

            <table class="w-full text-sm">
                <thead class="bg-muted/30 text-left text-muted-foreground">
                    <tr>
                        <ColumnHeader field="name">{{ t('app.tenant.categories.fields.name') }}</ColumnHeader>
                        <ColumnHeader field="full_path">{{ t('app.tenant.categories.fields.full_path') }}</ColumnHeader>
                        <ColumnHeader field="status">{{ t('app.tenant.categories.fields.status') }}</ColumnHeader>
                        <ColumnHeader field="level_name">{{ t('app.tenant.categories.fields.level_name') }}</ColumnHeader>
                        <th class="px-4 py-3  font-medium">{{ t('app.tenant.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="categoriesLoading">
                        <TableLoadingSkeleton :columns="5" :rows="6" />
                    </template>
                    <tr v-else-if="categoriesRows.length === 0">
                        <td class="px-4 py-8 text-center text-muted-foreground" colspan="5">
                            {{ t('app.tenant.common.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="category in categoriesRows"
                        :key="category.id"
                        class="border-t border-sidebar-border/60 transition-colors odd:bg-transparent even:bg-muted/30 hover:bg-muted/50 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3">
                            <ColumnLabel :label="category.name" :description="category.slug" />
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ category.full_path }}</td>
                        <td class="px-4 py-3">
                            <ColumnStatusBadge :status="category.status" />
                        </td>
                        <td class="px-4 py-3">
                            <Badge v-if="category.level_name" variant="outline">
                                {{ category.level_name }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 ">
                            <ColumnActions
                                :edit-href="CategoryController.edit.url({ category: category.id })"
                                :delete-href="CategoryController.destroy.url({ category: category.id })"
                                :delete-label="category.name"
                                :require-confirm-word="true"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </ListPage>
    </AppLayout>
</template>

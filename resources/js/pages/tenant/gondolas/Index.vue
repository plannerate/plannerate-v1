<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { PanelTop } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import GondolaController from '@/actions/App/Http/Controllers/Tenant/GondolaController';
import PlanogramController from '@/actions/App/Http/Controllers/Tenant/PlanogramController';
import DeleteButton from '@/components/DeleteButton.vue';
import EditButton from '@/components/EditButton.vue';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { Button } from '@/components/ui/button';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';
import { editor as tenantEditorPlanogramGondolas } from '@/routes/tenant/planograms/gondolas';
import type { Paginator } from '@/types';

type GondolaRow = {
    id: string;
    name: string;
    slug: string | null;
    num_modulos: number;
    location: string | null;
    side: string | null;
    flow: 'left_to_right' | 'right_to_left';
    alignment: 'left' | 'right' | 'center' | 'justify';
    scale_factor: number;
    status: 'draft' | 'published';
};

const props = defineProps<{
    subdomain: string;
    planogram: {
        id: string;
        name: string | null;
    };
    gondolas: Paginator<GondolaRow>;
    filters: {
        search: string;
        status: string;
    };
}>();

const { t } = useT();
const gondolasIndexPath = GondolaController.index.url({
    subdomain: props.subdomain,
    planogram: props.planogram.id,
}).replace(/^\/\/[^/]+/, '');
const planogramsIndexPath = PlanogramController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.gondolas.title'),
    title: t('app.tenant.gondolas.title'),
    description: t('app.tenant.gondolas.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.planograms.navigation'), href: planogramsIndexPath },
        { title: props.planogram.name ?? '-', href: planogramsIndexPath },
        { title: t('app.tenant.gondolas.navigation'), href: gondolasIndexPath },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="planogramsIndexPath">{{ t('app.actions.back') }}</Link>
                </Button>
                <NewActionButton
                    :href="GondolaController.create.url({
                        subdomain: props.subdomain,
                        planogram: props.planogram.id,
                    })"
                >
                    {{ t('app.tenant.gondolas.actions.new') }}
                </NewActionButton>
            </div>
        </template>

        <ListPage
            :meta="props.gondolas"
        label="gôndola"
        :action="gondolasIndexPath"
        :clear-href="gondolasIndexPath"
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
        </template>

        <table class="w-full text-sm">
            <thead class="bg-muted/30 text-left text-muted-foreground">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ t('app.tenant.gondolas.fields.name') }}</th>
                    <th class="px-4 py-3 font-medium">Slug</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.tenant.gondolas.fields.modules') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.tenant.gondolas.fields.flow') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.tenant.gondolas.fields.alignment') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.tenant.gondolas.fields.status') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ t('app.tenant.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="props.gondolas.data.length === 0">
                    <td class="px-4 py-6 text-muted-foreground" colspan="7">
                        {{ t('app.tenant.common.empty') }}
                    </td>
                </tr>
                <tr v-for="gondola in props.gondolas.data" :key="gondola.id" class="border-t border-sidebar-border/60 dark:border-sidebar-border">
                    <td class="px-4 py-3 font-medium">
                        <div class="inline-flex items-center gap-2">
                            <PanelTop class="size-4 text-muted-foreground" />
                            {{ gondola.name }}
                        </div>
                    </td>
                    <td class="px-4 py-3">{{ gondola.slug ?? '-' }}</td>
                    <td class="px-4 py-3">{{ gondola.num_modulos }}</td>
                    <td class="px-4 py-3">{{ gondola.flow }}</td>
                    <td class="px-4 py-3">{{ gondola.alignment }}</td>
                    <td class="px-4 py-3">{{ gondola.status }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex items-center gap-2">
                            <!-- editor.planograms.gondolas -->
                            <Button variant="outline" size="sm" as-child>
                                <a
                                    target="_blank"
                                    :href="tenantEditorPlanogramGondolas.url({
                                        subdomain: props.subdomain,
                                        record:gondola.id,
                                    })"
                                >
                                    {{ t('app.tenant.planograms.actions.view_gondolas') }}
                                </a>
                            </Button>
                            <EditButton
                                :href="GondolaController.edit.url({
                                    subdomain: props.subdomain,
                                    planogram: props.planogram.id,
                                    gondola: gondola.id,
                                })"
                            />
                            <DeleteButton
                                :href="GondolaController.destroy.url({
                                    subdomain: props.subdomain,
                                    planogram: props.planogram.id,
                                    gondola: gondola.id,
                                })"
                                :label="gondola.name"
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

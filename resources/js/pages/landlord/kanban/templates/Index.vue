<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Layers } from 'lucide-vue-next';
import TenantController from '@/actions/App/Http/Controllers/Landlord/TenantController';
import WorkflowTemplateController from '@/actions/App/Http/Controllers/Landlord/WorkflowTemplateController';
import AppLayout from '@/layouts/AppLayout.vue';
import ListPagination from '@/components/ListPagination.vue';
import { Button } from '@/components/ui/button';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import type { Paginator } from '@/types';
import TemplateFiltersBar from './TemplateFiltersBar.vue';
import TemplateCard from './TemplateCard.vue';
import TemplateSheet from './TemplateSheet.vue';

export type TemplateRow = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    suggested_order: number;
    estimated_duration_days: number | null;
    default_role_id: string | null;
    color: string | null;
    icon: string | null;
    is_required_by_default: boolean;
    template_next_step_id: string | null;
    template_previous_step_id: string | null;
    status: string;
    user_ids: string[];
    created_at: string | null;
};

export type UserOption = {
    id: string;
    name: string;
};

export type TemplateOption = {
    id: string;
    name: string;
};

type TenantPayload = {
    id: string;
    name: string;
    slug: string;
};

const props = defineProps<{
    tenant: TenantPayload;
    templates: Paginator<TemplateRow>;
    users: UserOption[];
    existing_templates: TemplateOption[];
    filters: {
        search: string;
        status: string;
    };
}>();

const { t } = useT();
const tenantsIndexPath = TenantController.index.url().replace(/^\/\/[^/]+/, '');

const pageMeta = useCrudPageMeta({
    headTitle: `${t('app.landlord.kanban.templates.title')} - ${props.tenant.name}`,
    title: `${t('app.landlord.kanban.templates.title')} - ${props.tenant.name}`,
    description: t('app.landlord.kanban.templates.description'),
    breadcrumbs: [
        { title: t('app.landlord.tenants.navigation'), href: tenantsIndexPath },
        {
            title: t('app.landlord.kanban.templates.navigation'),
            href: WorkflowTemplateController.index.url(props.tenant.id),
        },
    ],
});

const isDrawerOpen = ref(false);
const drawerMode = ref<'create' | 'edit'>('create');
const selectedTemplate = ref<TemplateRow | null>(null);

function openCreateDrawer(): void {
    drawerMode.value = 'create';
    selectedTemplate.value = null;
    isDrawerOpen.value = true;
}

function openEditDrawer(template: TemplateRow): void {
    drawerMode.value = 'edit';
    selectedTemplate.value = template;
    isDrawerOpen.value = true;
}
</script>

<template>
    <Head :title="`${t('app.landlord.kanban.templates.title')} - ${props.tenant.name}`" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <template #header-actions>
            <Button variant="gradient" size="pill-sm" class="shrink-0" @click="openCreateDrawer">
                <Plus class="size-4" />
                {{ t('app.landlord.kanban.templates.create_template') }}
            </Button>
        </template>

        <div class="space-y-6 p-4">
            <TemplateFiltersBar
                :tenant-id="props.tenant.id"
                :filters="props.filters"
                :filter-label="t('app.landlord.common.filter')"
                :clear-label="t('app.landlord.common.clear_filters')"
                :total="props.templates.total"
            />

            <!-- Empty state -->
            <div
                v-if="props.templates.data.length === 0"
                class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border p-16 text-center"
            >
                <div class="mb-4 flex size-16 items-center justify-center rounded-full bg-muted">
                    <Layers class="size-8 text-muted-foreground" />
                </div>
                <p class="font-semibold text-muted-foreground">{{ t('app.landlord.kanban.templates.no_template') }}</p>
                <p class="mt-1 text-sm text-muted-foreground/70">Crie a primeira etapa do workflow para este tenant.</p>
            </div>

            <!-- Cards grid -->
            <div v-else class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                <TemplateCard
                    v-for="template in props.templates.data"
                    :key="template.id"
                    :template="template"
                    :tenant-id="props.tenant.id"
                    @edit="openEditDrawer"
                />

                <button
                    class="group flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-border p-10 text-muted-foreground/50 transition-all hover:border-muted-foreground/40 hover:text-muted-foreground"
                    @click="openCreateDrawer"
                >
                    <div
                        class="mb-4 flex size-16 items-center justify-center rounded-full bg-muted/50 transition-transform group-hover:scale-110"
                    >
                        <Plus class="size-7" />
                    </div>
                    <p class="text-sm font-bold">{{ t('app.landlord.kanban.templates.create_template') }}</p>
                </button>
            </div>

            <ListPagination :meta="props.templates" label="etapa" />
        </div>

        <TemplateSheet
            v-model:open="isDrawerOpen"
            :mode="drawerMode"
            :template="selectedTemplate"
            :tenant="props.tenant"
            :users="props.users"
            :existing-templates="props.existing_templates"
        />
    </AppLayout>
</template>

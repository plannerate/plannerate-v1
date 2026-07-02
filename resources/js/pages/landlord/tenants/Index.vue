<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { KeyRound, Layers, Link2, Ruler, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import TenantController from '@/actions/App/Http/Controllers/Landlord/TenantController';
import TenantGondolaDefaultsController from '@/actions/App/Http/Controllers/Landlord/TenantGondolaDefaultsController';
import TenantIntegrationController from '@/actions/App/Http/Controllers/Landlord/TenantIntegrationController';
import TenantUserAccessController from '@/actions/App/Http/Controllers/Landlord/TenantUserAccessController';
import WorkflowTemplateController from '@/actions/App/Http/Controllers/Landlord/WorkflowTemplateController';
import ImportFileButton from '@/components/imports/ImportFileButton.vue';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import ColumnActions from '@/components/table/columns/ColumnActions.vue';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
import { Button } from '@/components/ui/button';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useDeferredPaginator } from '@/composables/useDeferredPaginator';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';
import type { Paginator } from '@/types';
import TenantSocialiteSheet from './TenantSocialiteSheet.vue';

type SsoProvider = {
    id: string;
    provider: string;
    label: string | null;
    client_id: string;
    azure_tenant: string | null;
    is_active: boolean;
};

type TenantRow = {
    active_modules: string[];
    has_kanban: boolean;
    id: string;
    name: string;
    slug: string;
    database: string;
    status: string;
    plan: { id: string; name: string } | null;
    primary_domain: { id: string; host: string; is_active: boolean } | null;
    sso_provider: SsoProvider | null;
};

const props = defineProps<{
    tenants?: Paginator<TenantRow>;
    filters: {
        search: string;
        status: string;
        plan_id: string;
        module: string;
    };
    filter_options: {
        statuses: Array<{ value: string; label: string }>;
        plans: Array<{ id: string; name: string }>;
        modules: Array<{ slug: string; name: string }>;
    };
}>();

const { t } = useT();
const tenantsIndexPath = TenantController.index.url().replace(/^\/\/[^/]+/, '');
const { meta: tenantsMeta, rows: tenantsRows, loading: tenantsLoading } = useDeferredPaginator(() => props.tenants, 10);
const pageMeta = useCrudPageMeta({
    headTitle: t('app.landlord.tenants.title'),
    title: t('app.landlord.tenants.title'),
    description: t('app.landlord.tenants.description'),
    breadcrumbs: [
        {
            title: t('app.landlord.tenants.navigation'),
            href: tenantsIndexPath,
        },
    ],
});

const ssoSheetOpen = ref(false);
const ssoSheetTenant = ref<TenantRow | null>(null);
const tenantExportPath = computed(() => {
    const params = new URLSearchParams();

    if (props.filters.search !== '') {
        params.set('search', props.filters.search);
    }

    if (props.filters.status !== '') {
        params.set('status', props.filters.status);
    }

    if (props.filters.plan_id !== '') {
        params.set('plan_id', props.filters.plan_id);
    }

    if (props.filters.module !== '') {
        params.set('module', props.filters.module);
    }

    const basePath = TenantController.exportConfigurations
        .url()
        .replace(/^\/\/[^/]+/, '');

    return params.size > 0 ? `${basePath}?${params.toString()}` : basePath;
});

function openSsoSheet(tenant: TenantRow): void {
    ssoSheetTenant.value = tenant;
    ssoSheetOpen.value = true;
}
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex items-center justify-end gap-2">
                <Button variant="outline" size="pill-sm" as-child>
                    <a :href="tenantExportPath">
                        {{ t('app.landlord.tenants.actions.export') }}
                    </a>
                </Button>
                <ImportFileButton
                    :action="TenantController.importConfigurations.url()"
                    :button-label="t('app.landlord.tenants.actions.import')"
                    :title="t('app.landlord.tenants.import.title')"
                    :description="t('app.landlord.tenants.import.description')"
                    :file-label="t('app.landlord.tenants.import.file_label')"
                    :submit-label="t('app.landlord.tenants.import.submit')"
                    :submitting-label="t('app.landlord.tenants.import.submitting')"
                    :cancel-label="t('app.landlord.tenants.import.cancel')"
                    accept=".json,application/json,text/plain"
                    drop-label="Arraste e solte o arquivo JSON aqui"
                    drop-hint="ou clique para escolher um arquivo .json"
                />
                <NewActionButton :href="tenantWayfinderPath(TenantController.create.url())">
                    {{ t('app.landlord.tenants.actions.new') }}
                </NewActionButton>
            </div>
        </template>

        <ListPage
            :meta="tenantsMeta"
            label="tenant"
            :action="tenantsIndexPath"
            :clear-href="tenantsIndexPath"
            :search-value="props.filters.search"
            :search-placeholder="t('app.landlord.common.search')"
            :filter-label="t('app.landlord.common.filter')"
            :clear-label="t('app.landlord.common.clear_filters')"
            :show-trashed-filter="false"
        >
            <template #filters>
                <select
                    name="status"
                    :value="props.filters.status"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground transition outline-none focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                >
                    <option value="">{{ t('app.landlord.common.all') }}</option>
                    <option
                        v-for="status in props.filter_options.statuses"
                        :key="status.value"
                        :value="status.value"
                    >
                        {{ status.label }}
                    </option>
                </select>

                <select
                    name="plan_id"
                    :value="props.filters.plan_id"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground transition outline-none focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                >
                    <option value="">{{ t('app.landlord.common.all') }}</option>
                    <option
                        v-for="plan in props.filter_options.plans"
                        :key="plan.id"
                        :value="plan.id"
                    >
                        {{ plan.name }}
                    </option>
                </select>

                <select
                    name="module"
                    :value="props.filters.module"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground transition outline-none focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                >
                    <option value="">{{ t('app.landlord.common.all') }}</option>
                    <option
                        v-for="module in props.filter_options.modules"
                        :key="module.slug"
                        :value="module.slug"
                    >
                        {{ module.name }}
                    </option>
                </select>
            </template>

            <table class="w-full text-sm">
                <thead class="bg-muted/30 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">
                            {{ t('app.landlord.tenants.fields.name') }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ t('app.landlord.tenants.fields.status') }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ t('app.landlord.tenants.fields.database') }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ t('app.landlord.tenants.fields.host') }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ t('app.landlord.tenants.fields.plan') }}
                        </th>
                        <th class="px-4 py-3  font-medium">
                            {{ t('app.landlord.common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="tenantsLoading">
                        <TableLoadingSkeleton :columns="6" :rows="6" />
                    </template>
                    <tr v-else-if="tenantsRows.length === 0">
                        <td class="px-4 py-6 text-muted-foreground" colspan="6">
                            {{ t('app.landlord.common.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="tenant in tenantsRows"
                        :key="tenant.id"
                        class="border-t border-sidebar-border/60 transition-colors odd:bg-transparent even:bg-muted/30 hover:bg-muted/50 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ tenant.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ tenant.slug }}
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            {{
                                t(
                                    `app.landlord.tenant_statuses.${tenant.status}`,
                                )
                            }}
                        </td>
                        <td class="px-4 py-3">{{ tenant.database }}</td>
                        <td class="px-4 py-3">
                            <div>{{ tenant.primary_domain?.host ?? '-' }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{
                                    tenant.primary_domain?.is_active
                                        ? t('app.landlord.common.active')
                                        : t('app.landlord.common.inactive')
                                }}
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            {{ tenant.plan?.name ?? '-' }}
                        </td>
                        <td class="px-4 py-3 ">
                            <ColumnActions
                                :edit-href="
                                    tenantWayfinderPath(TenantController.edit.url({
                                        tenant: tenant.id,
                                    }))
                                "
                                :delete-href="
                                    tenantWayfinderPath(TenantController.destroy.url({
                                        tenant: tenant.id,
                                    }))
                                "
                                :delete-label="tenant.name ?? undefined"
                                :require-confirm-word="true"
                            >
                                <div class="flex items-center gap-2">
                                    <Button
                                        v-if="tenant.status !== 'active'"
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                tenantWayfinderPath(TenantController.setup.url(
                                                    tenant.id,
                                                ))
                                            "
                                        >
                                            {{
                                                t(
                                                    'app.landlord.tenants.setup.title',
                                                )
                                            }}
                                        </Link>
                                    </Button>

                                    <!-- Acessos — gestão de usuários do tenant (primeiro passo pós-provisionamento) -->
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                tenantWayfinderPath(TenantUserAccessController.edit.url(
                                                    tenant.id,
                                                ))
                                            "
                                        >
                                            <Users class="size-4" />
                                            {{
                                                t('app.landlord.common.access')
                                            }}
                                        </Link>
                                    </Button>

                                    <!-- Integração API — importação dos dados (produtos/vendas) do cliente -->
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                tenantWayfinderPath(TenantIntegrationController.edit.url(
                                                    tenant.id,
                                                ))
                                            "
                                        >
                                            <Link2 class="size-4" />
                                            {{
                                                t(
                                                    'app.landlord.tenant_integrations.navigation',
                                                )
                                            }}
                                        </Link>
                                    </Button>

                                    <!-- Padrão de gôndola — defaults usados na montagem dos planogramas -->
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                tenantWayfinderPath(TenantGondolaDefaultsController.edit.url(
                                                    tenant.id,
                                                ))
                                            "
                                        >
                                            <Ruler class="size-4" />
                                            {{
                                                t('app.landlord.tenants.gondola_defaults.navigation')
                                            }}
                                        </Link>
                                    </Button>

                                    <!-- Kanban — módulo de workflow (só se o tenant tiver o módulo ativo) -->
                                    <Button
                                        v-if="tenant.has_kanban"
                                        variant="secondary"
                                        size="sm"
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                tenantWayfinderPath(
                                                    WorkflowTemplateController.index.url(tenant.id),
                                                )
                                            "
                                        >
                                            <Layers class="size-4" />
                                            Kanban
                                        </Link>
                                    </Button>

                                    <!-- SSO — login único, configuração opcional (abre sheet lateral) -->
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        :class="tenant.sso_provider ? 'text-primary' : ''"
                                        @click="openSsoSheet(tenant)"
                                    >
                                        <KeyRound class="size-4" />
                                        SSO
                                    </Button>
                                </div>
                            </ColumnActions>
                        </td>
                    </tr>
                </tbody>
            </table>
        </ListPage>

        <!-- SSO Sheet — shared across all rows -->
        <TenantSocialiteSheet
            v-if="ssoSheetTenant"
            :open="ssoSheetOpen"
            :tenant="ssoSheetTenant"
            :sso-provider="ssoSheetTenant.sso_provider"
            @update:open="ssoSheetOpen = $event"
        />
    </AppLayout>
</template>

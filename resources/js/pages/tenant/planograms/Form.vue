<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { LayoutGrid } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import PlanogramController from '@/actions/App/Http/Controllers/Tenant/PlanogramController';
import FormKanbanSettings from '@/components/form/FormKanbanSettings.vue';
import FormMonthYearRangePicker from '@/components/form/FormMonthYearRangePicker.vue';
import FormSelectField from '@/components/form/FormSelectField.vue';
import FormStatusToggleField from '@/components/form/FormStatusToggleField.vue';
import FormTabsBar from '@/components/form/FormTabsBar.vue';
import FormTextareaField from '@/components/form/FormTextareaField.vue';
import FormTextField from '@/components/form/FormTextField.vue';
import FormCard from '@/components/FormCard.vue';
import CategoryCascadeSelect from '@/components/tenant/CategoryCascadeSelect.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

type PlanogramPayload = {
    id: string;
    template_id: string | null;
    store_id: string | null;
    cluster_id: string | null;
    name: string | null;
    type: 'realograma' | 'planograma';
    category_id: string | null;
    start_date: string | null;
    end_date: string | null;
    order: number;
    description: string | null;
    status: 'draft' | 'published';
};

type TabKey = 'identificacao' | 'mercadologico' | 'workflow';

const props = defineProps<{
    planogram: PlanogramPayload | null;
    stores: Array<{ id: string; name: string }>;
    clusters: Array<{ id: string; name: string }>;
}>();

const { t } = useT();
const page = usePage();
const isEdit = computed(() => props.planogram !== null);
const planogramsIndexPath = PlanogramController.index
    .url()
    .replace(/^\/\/[^/]+/, '');
const categoryId = ref<string | null>(props.planogram?.category_id ?? null);

const tabKeys: TabKey[] = ['identificacao', 'mercadologico', 'workflow'];

/**
 * Resolve a aba a partir do parâmetro `?tab=` da URL atual. Após criar, o backend
 * redireciona com `?tab=workflow` para forçar a configuração do workflow; nas
 * demais aberturas cai em "identificacao".
 */
function resolveTabFromUrl(): TabKey {
    const query = page.url.split('?')[1] ?? '';
    const requestedTab = new URLSearchParams(query).get('tab');

    return tabKeys.includes(requestedTab as TabKey)
        ? (requestedTab as TabKey)
        : 'identificacao';
}

const activeTab = ref<TabKey>(resolveTabFromUrl());

// O Inertia reutiliza este componente ao navegar de "create" para "edit" (mesmo
// componente), então o setup não roda de novo. Observa a URL para reativar a aba
// correta quando o backend redireciona para `?tab=workflow` após a criação.
watch(
    () => page.url,
    () => {
        activeTab.value = resolveTabFromUrl();
    },
);

/** Campos exibidos na aba de identificação (usados para focar a aba ao falhar a validação). */
const identificacaoFields = [
    'type',
    'name',
    'cluster_id',
    'store_id',
    'start_date',
    'end_date',
    'status',
    'description',
];

/**
 * Ao falhar a validação, ativa a aba que contém o primeiro erro para que a
 * mensagem fique visível (os campos obrigatórios estão espalhados entre abas).
 */
function focusTabWithError(errors: Record<string, string>): void {
    if (identificacaoFields.some((field) => errors[field])) {
        activeTab.value = 'identificacao';

        return;
    }

    if (errors.category_id) {
        activeTab.value = 'mercadologico';
    }
}

const tabs = computed(() => [
    {
        key: 'identificacao' as const,
        label: t('app.tenant.planograms.tabs.identificacao'),
    },
    {
        key: 'mercadologico' as const,
        label: t('app.tenant.planograms.tabs.mercadologico'),
    },
    {
        key: 'workflow' as const,
        label: t('app.tenant.planograms.tabs.workflow'),
    },
]);

// Reativo: o componente é reutilizado entre "create" e "edit", então o título e
// os breadcrumbs precisam recalcular quando `props.planogram` muda (isEdit).
const pageMetaOverride = computed(() => ({
    headTitle: isEdit.value
        ? t('app.tenant.planograms.actions.edit')
        : t('app.tenant.planograms.actions.new'),
    title: isEdit.value
        ? t('app.tenant.planograms.actions.edit')
        : t('app.tenant.planograms.actions.new'),
    description: t('app.tenant.planograms.description'),
    breadcrumbs: [
        {
            title: t('app.navigation.dashboard'),
            href: dashboard.url().replace(/^\/\/[^/]+/, ''),
        },
        {
            title: t('app.tenant.planograms.navigation'),
            href: planogramsIndexPath,
        },
        {
            title: isEdit.value
                ? t('app.tenant.planograms.actions.edit')
                : t('app.tenant.planograms.actions.new'),
            href: isEdit.value
                ? PlanogramController.edit.url({
                      planogram: props.planogram!.id,
                  })
                : PlanogramController.create.url(),
        },
    ],
}));

const pageMeta = useCrudPageMeta(
    {
        headTitle: t('app.tenant.planograms.actions.new'),
        title: t('app.tenant.planograms.actions.new'),
        description: t('app.tenant.planograms.description'),
        breadcrumbs: [],
    },
    pageMetaOverride,
);
</script>

<template>
    <Head :title="pageMeta.headTitle" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="p-4">
            <Form
                v-bind="
                    isEdit
                        ? PlanogramController.update?.form({
                              planogram: props.planogram!.id,
                          })
                        : PlanogramController.store?.form()
                "
                v-slot="{ errors, processing }"
                @error="focusTabWithError"
            >
                <FormCard
                    :processing="processing"
                    :cancel-href="planogramsIndexPath"
                    :title="pageMeta.title"
                    :description="pageMeta.description"
                >
                    <template #icon>
                        <LayoutGrid class="size-5" />
                    </template>
                    <FormTabsBar v-model="activeTab" :tabs="tabs" />

                    <!-- Tab: Identificação -->
                    <div
                        v-show="activeTab === 'identificacao'"
                        class="grid grid-cols-1 gap-4 md:grid-cols-12"
                    >
                        <FormSelectField
                            id="type"
                            name="type"
                            :label="t('app.tenant.planograms.fields.type')"
                            :default-value="
                                props.planogram?.type ?? 'planograma'
                            "
                            :error="errors.type"
                            class="md:col-span-3"
                            required
                        >
                            <option value="planograma">Planograma</option>
                            <option value="realograma">Realograma</option>
                        </FormSelectField>

                        <FormTextField
                            id="name"
                            name="name"
                            :label="t('app.tenant.planograms.fields.name')"
                            :default-value="props.planogram?.name ?? ''"
                            :error="errors.name"
                            class="md:col-span-9"
                            required
                        />

                        <FormSelectField
                            id="cluster_id"
                            name="cluster_id"
                            :label="t('app.tenant.planograms.fields.cluster')"
                            :default-value="props.planogram?.cluster_id ?? ''"
                            :error="errors.cluster_id"
                            class="md:col-span-4"
                        >
                            <option value="">
                                {{ t('app.tenant.common.all') }}
                            </option>
                            <option
                                v-for="cluster in props.clusters"
                                :key="cluster.id"
                                :value="cluster.id"
                            >
                                {{ cluster.name }}
                            </option>
                        </FormSelectField>

                        <FormSelectField
                            id="store_id"
                            name="store_id"
                            :label="t('app.tenant.planograms.fields.store')"
                            :default-value="props.planogram?.store_id ?? ''"
                            :error="errors.store_id"
                            class="md:col-span-4"
                            required
                        >
                            <option value="" disabled>
                                {{
                                    t(
                                        'app.tenant.planograms.fields.store_placeholder',
                                    )
                                }}
                            </option>
                            <option
                                v-for="store in props.stores"
                                :key="store.id"
                                :value="store.id"
                            >
                                {{ store.name }}
                            </option>
                        </FormSelectField>

                        <FormMonthYearRangePicker
                            start-name="start_date"
                            end-name="end_date"
                            :label="
                                t('app.tenant.planograms.fields.start_date') +
                                ' / ' +
                                t('app.tenant.planograms.fields.end_date') +
                                ' *'
                            "
                            :start-value="props.planogram?.start_date ?? null"
                            :end-value="props.planogram?.end_date ?? null"
                            :start-error="errors.start_date"
                            :end-error="errors.end_date"
                            class="md:col-span-4"
                        />
                        <FormStatusToggleField
                            id="status"
                            name="status"
                            :label="t('app.tenant.planograms.fields.status')"
                            :default-value="props.planogram?.status ?? 'draft'"
                            :error="errors.status"
                            class="md:col-span-12"
                            :checked-label="
                                t('app.tenant.planograms.status_published')
                            "
                            :unchecked-label="
                                t('app.tenant.planograms.status_draft')
                            "
                        />

                        <FormTextareaField
                            id="description"
                            name="description"
                            :label="
                                t('app.tenant.planograms.fields.description')
                            "
                            :default-value="props.planogram?.description ?? ''"
                            :error="errors.description"
                            class="md:col-span-12"
                            :rows="3"
                        />
                    </div>

                    <!-- Tab: Mercadológico -->
                    <div v-show="activeTab === 'mercadologico'" class="space-y-2">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-sm font-medium text-foreground">
                                {{ t('app.tenant.planograms.fields.category') }}
                                <span class="text-destructive">*</span>
                            </span>
                            <span class="text-xs text-muted-foreground">
                                {{
                                    t(
                                        'app.tenant.planograms.fields.category_required_hint',
                                    )
                                }}
                            </span>
                        </div>
                        <CategoryCascadeSelect
                            v-model="categoryId"
                            :error="errors.category_id"
                        />
                    </div>

                    <!-- Tab: Workflow -->
                    <div
                        v-show="activeTab === 'workflow'"
                        class="grid grid-cols-1 gap-4 md:grid-cols-12"
                    >
                        <FormKanbanSettings
                            v-if="props.planogram"
                            :planogram="props.planogram"
                        />

                        <!-- Na criação o planograma ainda não existe: orienta a
                             preencher as demais abas e salvar antes de configurar. -->
                        <div
                            v-else
                            class="rounded-lg border border-dashed border-border bg-muted/30 p-5 md:col-span-12"
                        >
                            <h3 class="text-sm font-semibold text-foreground">
                                {{
                                    t(
                                        'app.tenant.planograms.workflow_intro.title',
                                    )
                                }}
                            </h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{
                                    t(
                                        'app.tenant.planograms.workflow_intro.description',
                                    )
                                }}
                            </p>
                            <ol
                                class="mt-3 list-decimal space-y-1.5 pl-5 text-sm text-muted-foreground marker:text-primary marker:font-semibold"
                            >
                                <li>
                                    {{
                                        t(
                                            'app.tenant.planograms.workflow_intro.step_identificacao',
                                        )
                                    }}
                                </li>
                                <li>
                                    {{
                                        t(
                                            'app.tenant.planograms.workflow_intro.step_mercadologico',
                                        )
                                    }}
                                </li>
                                <li>
                                    {{
                                        t(
                                            'app.tenant.planograms.workflow_intro.step_salvar',
                                        )
                                    }}
                                </li>
                                <li>
                                    {{
                                        t(
                                            'app.tenant.planograms.workflow_intro.step_configurar',
                                        )
                                    }}
                                </li>
                            </ol>
                        </div>
                    </div>
                </FormCard>
            </Form>
        </div>
    </AppLayout>
</template>

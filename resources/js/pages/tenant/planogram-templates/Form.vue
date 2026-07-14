<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Download, Layers, PlusSquare, Upload } from 'lucide-vue-next';
import { computed } from 'vue';
import PlanogramTemplateController from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/Templates/PlanogramTemplateController';
import FormCard from '@/components/FormCard.vue';
import PlanogramTemplateFormFields from '@/components/planogram-templates/PlanogramTemplateFormFields.vue';
import type { WizardStep } from '@/components/planogram-templates/types';
import WizardProgress from '@/components/planogram-templates/WizardProgress.vue';
import { Button } from '@/components/ui/button';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

const props = defineProps<{
    template?: {
        id: string;
        code: string;
        name: string;
        department: string;
        category_id: string | null;
        category_name?: string | null;
        description: string | null;
        is_active: boolean;
    } | null;
}>();

const { t } = useT();
const isEdit = computed(
    () => props.template !== null && props.template !== undefined,
);
const indexPath = PlanogramTemplateController.index
    .url()
    .replace(/^\/\/[^/]+/, '');
const importPath = PlanogramTemplateController.importPage
    .url()
    .replace(/^\/\/[^/]+/, '');
const exportAllPath = PlanogramTemplateController.exportAll
    ?.url()
    ?.replace(/^\/\/[^/]+/, '') ?? null;
const exportTemplatePath = computed(() => {
    if (!isEdit.value) {
return null;
}

    return PlanogramTemplateController.export
        .url({
            planogramTemplate: props.template!.id,
        })
        .replace(/^\/\/[^/]+/, '');
});
const slotsPath = computed(() =>
    isEdit.value
        ? PlanogramTemplateController.show
              .url({
                  planogramTemplate: props.template!.id,
              })
              .replace(/^\/\/[^/]+/, '') + '/slots'
        : null,
);

const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value
        ? t('app.tenant.planogram_templates.actions.edit')
        : t('app.tenant.planogram_templates.actions.create'),
    title: isEdit.value
        ? t('app.tenant.planogram_templates.actions.edit')
        : t('app.tenant.planogram_templates.actions.create'),
    description: t('app.tenant.planogram_templates.create.description'),
    breadcrumbs: [
        {
            title: t('app.navigation.dashboard'),
            href: dashboard.url().replace(/^\/\/[^/]+/, ''),
        },
        {
            title: t('app.tenant.planogram_templates.navigation'),
            href: indexPath,
        },
        {
            title: isEdit.value
                ? t('app.tenant.common.edit')
                : t('app.tenant.common.create'),
            href: '#',
        },
    ],
});

const wizardSteps: WizardStep[] = [
    {
        step: 1,
        label: t('planogram-templates.wizard.step1_label'),
        description: t('planogram-templates.wizard.step1_description'),
    },
    {
        step: 2,
        label: t('planogram-templates.wizard.step2_label'),
        description: t('planogram-templates.wizard.step2_description'),
    },
];
</script>

<template>
    <Head :title="pageMeta.headTitle" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="p-4">
            <div class="mx-auto mb-6 max-w-3xl">
                <WizardProgress :current-step="1" :steps="wizardSteps" />
            </div>

            <Form
                v-bind="
                    isEdit
                        ? PlanogramTemplateController.update.form({
                              planogramTemplate: props.template!.id,
                          })
                        : PlanogramTemplateController.store.form()
                "
                v-slot="{ errors, processing }"
            >
                <FormCard
                    :processing="processing"
                    :cancel-href="indexPath"
                    :title="pageMeta.title"
                    :description="pageMeta.description"
                    :max-width="'max-w-3xl'"
                >
                    <template #icon>
                        <PlusSquare class="size-5" />
                    </template>

                    <PlanogramTemplateFormFields
                        :template="props.template ?? null"
                        :errors="errors"
                        translation-scope="app.tenant.planogram_templates"
                    />

                    <template #header-extra>
                        <Button variant="outline" type="button" as-child>
                            <Link :href="importPath">
                                <Upload class="size-4" />
                                {{
                                    t(
                                        'app.tenant.planogram_templates.actions.import',
                                    )
                                }}
                            </Link>
                        </Button>
                        <Button
                            v-if="isEdit && exportTemplatePath"
                            variant="outline"
                            type="button"
                            as-child
                        >
                            <a :href="exportTemplatePath">
                                <Download class="size-4" />
                                {{
                                    t(
                                        'app.tenant.planogram_templates.actions.export',
                                    )
                                }}
                            </a>
                        </Button>
                        <Button
                            v-else
                            variant="outline"
                            type="button"
                            as-child
                        >
                            <a :href="exportAllPath">
                                <Download class="size-4" />
                                {{
                                    t(
                                        'app.tenant.planogram_templates.actions.export_all',
                                    )
                                }}
                            </a>
                        </Button>
                        <template v-if="isEdit && slotsPath">
                            <Button
                                v-if="props.template?.category_id"
                                variant="outline"
                                type="button"
                                as-child
                            >
                                <Link :href="slotsPath">
                                    <Layers class="size-4" />
                                    {{
                                        t(
                                            'planogram-templates.wizard.configure_slots_button',
                                        )
                                    }}
                                </Link>
                            </Button>
                            <Button
                                v-else
                                variant="outline"
                                type="button"
                                disabled
                                :title="t('planogram-templates.form_page.category_required_tooltip')"
                            >
                                <Layers class="size-4" />
                                {{
                                    t(
                                        'planogram-templates.wizard.configure_slots_button',
                                    )
                                }}
                            </Button>
                        </template>
                    </template>
                </FormCard>
            </Form>
        </div>
    </AppLayout>
</template>

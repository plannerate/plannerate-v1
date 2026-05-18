<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { PlusSquare, Upload } from 'lucide-vue-next';
import { computed } from 'vue';
import GlobalPlanogramTemplateController from '@/actions/App/Http/Controllers/Landlord/GlobalPlanogramTemplateController';
import FormCard from '@/components/FormCard.vue';
import PlanogramTemplateFormFields from '@/components/planogram-templates/PlanogramTemplateFormFields.vue';
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
        description: string | null;
        is_active: boolean;
    } | null;
}>();

const { t } = useT();
const isEdit = computed(() => props.template !== null && props.template !== undefined);
const indexPath = GlobalPlanogramTemplateController.index.url().replace(/^\/\/[^/]+/, '');
const importPath = GlobalPlanogramTemplateController.importPage.url().replace(/^\/\/[^/]+/, '');

const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value ? t('app.landlord.planogram_templates.actions.edit') : t('app.landlord.planogram_templates.actions.create'),
    title: isEdit.value ? t('app.landlord.planogram_templates.actions.edit') : t('app.landlord.planogram_templates.actions.create'),
    description: t('app.landlord.planogram_templates.create.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.landlord.planogram_templates.navigation'), href: indexPath },
        { title: isEdit.value ? t('app.landlord.common.edit') : t('app.landlord.common.create'), href: '#' },
    ],
});
</script>

<template>
    <Head :title="pageMeta.headTitle" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="p-4">
            <Form
                v-bind="isEdit
                    ? GlobalPlanogramTemplateController.update.form(props.template!.id)
                    : GlobalPlanogramTemplateController.store.form()"
                v-slot="{ errors, processing }"
            >
                <FormCard :processing="processing" :cancel-href="indexPath" :title="pageMeta.title" :description="pageMeta.description" :max-width="'max-w-3xl'">
                    <template #icon>
                        <PlusSquare class="size-5" />
                    </template>

                    <PlanogramTemplateFormFields :template="props.template ?? null" :errors="errors" translation-scope="app.landlord.planogram_templates" />

                    <template #header-extra>
                        <Button variant="outline" :as="'a'" :href="importPath" type="button">
                            <Upload class="size-4" />
                            {{ t('app.landlord.planogram_templates.actions.import') }}
                        </Button>
                    </template>
                </FormCard>
            </Form>
        </div>
    </AppLayout>
</template>

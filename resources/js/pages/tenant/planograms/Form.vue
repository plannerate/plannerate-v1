<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { LayoutTemplate } from 'lucide-vue-next';
import PlanogramController from '@/actions/App/Http/Controllers/Tenant/PlanogramController';
import FormCard from '@/components/FormCard.vue';
import FormSelectField from '@/components/form/FormSelectField.vue';
import FormStatusField from '@/components/form/FormStatusField.vue';
import FormTextField from '@/components/form/FormTextField.vue';
import FormTextareaField from '@/components/form/FormTextareaField.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';

type PlanogramPayload = {
    id: string;
    template_id: string | null;
    store_id: string | null;
    cluster_id: string | null;
    name: string | null;
    slug: string | null;
    type: 'realograma' | 'planograma';
    category_id: string | null;
    start_date: string | null;
    end_date: string | null;
    order: number;
    description: string | null;
    status: 'draft' | 'published';
};

const props = defineProps<{
    subdomain: string;
    planogram: PlanogramPayload | null;
    stores: Array<{ id: string; name: string }>;
    clusters: Array<{ id: string; name: string }>;
    categories: Array<{ id: string; name: string }>;
}>();

const { t } = useT();
const isEdit = computed(() => props.planogram !== null);
const planogramsIndexPath = PlanogramController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value ? t('app.tenant.planograms.actions.edit') : t('app.tenant.planograms.actions.new'),
    title: isEdit.value ? t('app.tenant.planograms.actions.edit') : t('app.tenant.planograms.actions.new'),
    description: t('app.tenant.planograms.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.planograms.navigation'), href: planogramsIndexPath },
        {
            title: isEdit.value ? t('app.tenant.planograms.actions.edit') : t('app.tenant.planograms.actions.new'),
            href: isEdit.value
                ? PlanogramController.edit.url({ subdomain: props.subdomain, planogram: props.planogram!.id })
                : PlanogramController.create.url(props.subdomain),
        },
    ],
});
</script>

<template>
    <Head :title="pageMeta.headTitle" />

    <div class="p-4">
        <Form
            v-bind="isEdit
                ? PlanogramController.update.form({ subdomain: props.subdomain, planogram: props.planogram!.id })
                : PlanogramController.store.form(props.subdomain)"
            v-slot="{ errors, processing }"
        >
            <FormCard
                :title="pageMeta.title"
                :description="pageMeta.description"
                :processing="processing"
                :cancel-href="planogramsIndexPath"
            >
                <template #icon>
                    <LayoutTemplate class="size-5" />
                </template>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                    <FormTextField
                        id="name"
                        name="name"
                        :label="t('app.tenant.planograms.fields.name')"
                        :default-value="props.planogram?.name ?? ''"
                        :error="errors.name"
                        class="md:col-span-6"
                        required
                    />

                    <FormTextField
                        id="slug"
                        name="slug"
                        label="Slug"
                        :default-value="props.planogram?.slug ?? ''"
                        :error="errors.slug"
                        class="md:col-span-3"
                    />

                    <FormSelectField
                        id="type"
                        name="type"
                        :label="t('app.tenant.planograms.fields.type')"
                        :default-value="props.planogram?.type ?? 'planograma'"
                        :error="errors.type"
                        class="md:col-span-3"
                        required
                    >
                        <option value="planograma">Planograma</option>
                        <option value="realograma">Realograma</option>
                    </FormSelectField>

                    <FormSelectField
                        id="store_id"
                        name="store_id"
                        :label="t('app.tenant.planograms.fields.store')"
                        :default-value="props.planogram?.store_id ?? ''"
                        :error="errors.store_id"
                        class="md:col-span-4"
                    >
                        <option value="">{{ t('app.tenant.common.all') }}</option>
                        <option v-for="store in props.stores" :key="store.id" :value="store.id">{{ store.name }}</option>
                    </FormSelectField>

                    <FormSelectField
                        id="cluster_id"
                        name="cluster_id"
                        :label="t('app.tenant.planograms.fields.cluster')"
                        :default-value="props.planogram?.cluster_id ?? ''"
                        :error="errors.cluster_id"
                        class="md:col-span-4"
                    >
                        <option value="">{{ t('app.tenant.common.all') }}</option>
                        <option v-for="cluster in props.clusters" :key="cluster.id" :value="cluster.id">{{ cluster.name }}</option>
                    </FormSelectField>

                    <FormSelectField
                        id="category_id"
                        name="category_id"
                        :label="t('app.tenant.planograms.fields.category')"
                        :default-value="props.planogram?.category_id ?? ''"
                        :error="errors.category_id"
                        class="md:col-span-4"
                    >
                        <option value="">{{ t('app.tenant.common.all') }}</option>
                        <option v-for="category in props.categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                    </FormSelectField>

                    <FormTextField
                        id="template_id"
                        name="template_id"
                        :label="t('app.tenant.planograms.fields.template_id')"
                        :default-value="props.planogram?.template_id ?? ''"
                        :error="errors.template_id"
                        class="md:col-span-4"
                    />

                    <FormTextField
                        id="start_date"
                        name="start_date"
                        type="date"
                        :label="t('app.tenant.planograms.fields.start_date')"
                        :default-value="props.planogram?.start_date ?? ''"
                        :error="errors.start_date"
                        class="md:col-span-3"
                    />

                    <FormTextField
                        id="end_date"
                        name="end_date"
                        type="date"
                        :label="t('app.tenant.planograms.fields.end_date')"
                        :default-value="props.planogram?.end_date ?? ''"
                        :error="errors.end_date"
                        class="md:col-span-3"
                    />

                    <FormTextField
                        id="order"
                        name="order"
                        type="number"
                        :label="t('app.tenant.planograms.fields.order')"
                        :default-value="String(props.planogram?.order ?? 0)"
                        :error="errors.order"
                        class="md:col-span-2"
                    />

                    <FormStatusField
                        id="status"
                        name="status"
                        :label="t('app.tenant.planograms.fields.status')"
                        :default-value="props.planogram?.status ?? 'draft'"
                        :error="errors.status"
                        class="md:col-span-3"
                        :options="[
                            { value: 'draft', label: 'Draft' },
                            { value: 'published', label: 'Published' },
                        ]"
                    />

                    <FormTextareaField
                        id="description"
                        name="description"
                        :label="t('app.tenant.planograms.fields.description')"
                        :default-value="props.planogram?.description ?? ''"
                        :error="errors.description"
                        class="md:col-span-12"
                        :rows="2"
                    />
                </div>
            </FormCard>
        </Form>
    </div>
</template>

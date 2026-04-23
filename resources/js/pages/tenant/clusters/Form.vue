<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Blocks } from 'lucide-vue-next';
import ClusterController from '@/actions/App/Http/Controllers/Tenant/ClusterController';
import AddressFields from '@/components/form/AddressFields.vue';
import FormCard from '@/components/FormCard.vue';
import FormSelectField from '@/components/form/FormSelectField.vue';
import FormStatusField from '@/components/form/FormStatusField.vue';
import FormTextareaField from '@/components/form/FormTextareaField.vue';
import FormTextField from '@/components/form/FormTextField.vue';
import { useT } from '@/composables/useT';

type ClusterPayload = {
    id: string;
    store_id: string;
    name: string;
    specification_1: string | null;
    specification_2: string | null;
    specification_3: string | null;
    slug: string | null;
    status: 'draft' | 'published';
    description: string | null;
};

const props = defineProps<{
    subdomain: string;
    cluster: ClusterPayload | null;
    stores: Array<{ id: string; name: string }>;
}>();

const { t } = useT();
const isEdit = computed(() => props.cluster !== null);
const clustersIndexPath = ClusterController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
</script>

<template>
    <Head :title="isEdit ? t('app.tenant.clusters.actions.edit') : t('app.tenant.clusters.actions.new')" />

    <div class="p-4">
        <Form
            v-bind="isEdit
                ? ClusterController.update.form({ subdomain: props.subdomain, cluster: props.cluster!.id })
                : ClusterController.store.form(props.subdomain)"
            v-slot="{ errors, processing }"
        >
            <FormCard
                :title="isEdit ? t('app.tenant.clusters.actions.edit') : t('app.tenant.clusters.actions.new')"
                :description="t('app.tenant.clusters.description')"
                :processing="processing"
                :cancel-href="clustersIndexPath"
            >
                <template #icon>
                    <Blocks class="size-5" />
                </template>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                    <FormSelectField
                        id="store_id"
                        name="store_id"
                        :label="t('app.tenant.clusters.fields.store')"
                        :default-value="props.cluster?.store_id ?? ''"
                        :error="errors.store_id"
                        class="md:col-span-4"
                        required
                    >
                        <option value="">{{ t('app.tenant.common.all') }}</option>
                        <option v-for="store in props.stores" :key="store.id" :value="store.id">{{ store.name }}</option>
                    </FormSelectField>

                    <FormTextField
                        id="name"
                        name="name"
                        :label="t('app.tenant.clusters.fields.name')"
                        :default-value="props.cluster?.name ?? ''"
                        :error="errors.name"
                        class="md:col-span-5"
                        required
                    />

                    <FormTextField
                        id="slug"
                        name="slug"
                        label="Slug"
                        :default-value="props.cluster?.slug ?? ''"
                        :error="errors.slug"
                        class="md:col-span-3"
                    />

                    <FormTextField
                        id="specification_1"
                        name="specification_1"
                        :label="t('app.tenant.clusters.fields.specification_1')"
                        :default-value="props.cluster?.specification_1 ?? ''"
                        :error="errors.specification_1"
                        class="md:col-span-4"
                    />

                    <FormTextField
                        id="specification_2"
                        name="specification_2"
                        :label="t('app.tenant.clusters.fields.specification_2')"
                        :default-value="props.cluster?.specification_2 ?? ''"
                        :error="errors.specification_2"
                        class="md:col-span-4"
                    />

                    <FormTextField
                        id="specification_3"
                        name="specification_3"
                        :label="t('app.tenant.clusters.fields.specification_3')"
                        :default-value="props.cluster?.specification_3 ?? ''"
                        :error="errors.specification_3"
                        class="md:col-span-4"
                    />

                    <FormStatusField
                        id="status"
                        name="status"
                        :label="t('app.tenant.clusters.fields.status')"
                        :default-value="props.cluster?.status ?? 'draft'"
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
                        :label="t('app.tenant.clusters.fields.description')"
                        :default-value="props.cluster?.description ?? ''"
                        :error="errors.description"
                        class="md:col-span-9"
                        :rows="2"
                    />
                </div>

                <AddressFields :errors="errors" />
            </FormCard>
        </Form>
    </div>
</template>

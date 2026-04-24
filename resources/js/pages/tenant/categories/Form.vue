<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/Tenant/CategoryController';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';

defineOptions({ layout: AppSidebarLayout });
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import FormSelectField from '@/components/form/FormSelectField.vue';
import FormStatusField from '@/components/form/FormStatusField.vue';
import FormTextField from '@/components/form/FormTextField.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';

type CategoryPayload = {
    id: string;
    category_id: string | null;
    name: string;
    slug: string | null;
    level_name: string | null;
    codigo: number | null;
    status: 'draft' | 'published' | 'importer';
    description: string | null;
    nivel: string | null;
    hierarchy_position: number | null;
    full_path: string | null;
    hierarchy_path: string[] | null;
    is_placeholder: boolean;
};

const props = defineProps<{
    subdomain: string;
    category: CategoryPayload | null;
    parent_categories: Array<{ id: string; name: string }>;
}>();

const { t } = useT();
const isEdit = computed(() => props.category !== null);
const categoriesIndexPath = CategoryController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');

const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value ? t('app.tenant.categories.actions.edit') : t('app.tenant.categories.actions.new'),
    title: isEdit.value ? t('app.tenant.categories.actions.edit') : t('app.tenant.categories.actions.new'),
    description: t('app.tenant.categories.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.categories.navigation'), href: categoriesIndexPath },
        {
            title: isEdit.value ? t('app.tenant.categories.actions.edit') : t('app.tenant.categories.actions.new'),
            href: isEdit.value
                ? CategoryController.edit.url({ subdomain: props.subdomain, category: props.category!.id })
                : CategoryController.create.url(props.subdomain),
        },
    ],
});

const statusOptions = computed(() => [
    { value: 'draft', label: t('app.tenant.categories.status_options.draft') },
    { value: 'published', label: t('app.tenant.categories.status_options.published') },
    { value: 'importer', label: t('app.tenant.categories.status_options.importer') },
]);
</script>

<template>
    <Head :title="pageMeta.headTitle" />

    <div class="px-6 py-6">
        <Form
            v-bind="isEdit
                ? CategoryController.update.form({ subdomain: props.subdomain, category: props.category!.id })
                : CategoryController.store.form(props.subdomain)"
            v-slot="{ errors, processing }"
        >
            <FormCard
                :processing="processing"
                :cancel-href="categoriesIndexPath"
            >
                <div class="grid gap-4 md:grid-cols-2">
                    <FormTextField
                        id="name"
                        name="name"
                        :label="t('app.tenant.categories.fields.name')"
                        :default-value="props.category?.name ?? ''"
                        :required="true"
                        :error="errors.name"
                    />
                    <FormTextField
                        id="slug"
                        name="slug"
                        label="Slug"
                        :default-value="props.category?.slug ?? ''"
                        :error="errors.slug"
                    />
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <FormSelectField
                        id="category_id"
                        name="category_id"
                        :label="t('app.tenant.categories.fields.parent')"
                        :default-value="props.category?.category_id ?? ''"
                        :error="errors.category_id"
                    >
                        <option value="">{{ t('app.tenant.common.all') }}</option>
                        <option
                            v-for="parent in props.parent_categories"
                            :key="parent.id"
                            :value="parent.id"
                        >
                            {{ parent.name }}
                        </option>
                    </FormSelectField>

                    <FormTextField
                        id="codigo"
                        name="codigo"
                        :label="t('app.tenant.categories.fields.codigo')"
                        :default-value="String(props.category?.codigo ?? '')"
                        :error="errors.codigo"
                    />

                    <FormTextField
                        id="hierarchy_position"
                        name="hierarchy_position"
                        :label="t('app.tenant.categories.fields.hierarchy_position')"
                        :default-value="String(props.category?.hierarchy_position ?? '')"
                        :error="errors.hierarchy_position"
                    />
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <FormTextField
                        id="level_name"
                        name="level_name"
                        :label="t('app.tenant.categories.fields.level_name')"
                        :default-value="props.category?.level_name ?? ''"
                        :error="errors.level_name"
                    />
                    <FormTextField
                        id="nivel"
                        name="nivel"
                        :label="t('app.tenant.categories.fields.nivel')"
                        :default-value="props.category?.nivel ?? ''"
                        :error="errors.nivel"
                    />
                    <FormTextField
                        id="full_path"
                        name="full_path"
                        :label="t('app.tenant.categories.fields.full_path')"
                        :default-value="props.category?.full_path ?? ''"
                        :error="errors.full_path"
                    />
                </div>

                <FormTextField
                    id="description"
                    name="description"
                    :label="t('app.tenant.categories.fields.description')"
                    :default-value="props.category?.description ?? ''"
                    :error="errors.description"
                />

                <FormStatusField
                    id="status"
                    name="status"
                    :label="t('app.tenant.categories.fields.status')"
                    :options="statusOptions"
                    :default-value="props.category?.status ?? 'draft'"
                    :error="errors.status"
                />

                <input type="hidden" name="hierarchy_path[]" :value="(props.category?.hierarchy_path ?? []).join(' > ')" />

                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-border bg-muted/30 px-4 py-3 transition-colors hover:bg-muted/50 has-checked:border-primary/50 has-checked:bg-primary/5">
                    <input type="hidden" name="is_placeholder" value="0" />
                    <input
                        id="is_placeholder"
                        name="is_placeholder"
                        type="checkbox"
                        value="1"
                        :checked="props.category?.is_placeholder ?? false"
                        class="accent-primary"
                    />
                    <div>
                        <span class="text-sm font-medium">{{ t('app.tenant.categories.fields.is_placeholder') }}</span>
                    </div>
                    <InputError :message="errors.is_placeholder" />
                </label>
            </FormCard>
        </Form>
    </div>
</template>

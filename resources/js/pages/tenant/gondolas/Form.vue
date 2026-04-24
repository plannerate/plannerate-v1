<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { PanelTop } from 'lucide-vue-next';
import GondolaController from '@/actions/App/Http/Controllers/Tenant/GondolaController';
import FormCard from '@/components/FormCard.vue';
import FormSelectField from '@/components/form/FormSelectField.vue';
import FormStatusField from '@/components/form/FormStatusField.vue';
import FormTextField from '@/components/form/FormTextField.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';

type GondolaPayload = {
    id: string;
    planogram_id: string;
    linked_map_gondola_id: string | null;
    linked_map_gondola_category: string | null;
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
    gondola: GondolaPayload | null;
}>();

const { t } = useT();
const isEdit = computed(() => props.gondola !== null);
const gondolasIndexPath = GondolaController.index.url({
    subdomain: props.subdomain,
    planogram: props.planogram.id,
}).replace(/^\/\/[^/]+/, '');
const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value ? t('app.tenant.gondolas.actions.edit') : t('app.tenant.gondolas.actions.new'),
    title: isEdit.value ? t('app.tenant.gondolas.actions.edit') : t('app.tenant.gondolas.actions.new'),
    description: t('app.tenant.gondolas.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.gondolas.navigation'), href: gondolasIndexPath },
        {
            title: isEdit.value ? t('app.tenant.gondolas.actions.edit') : t('app.tenant.gondolas.actions.new'),
            href: isEdit.value
                ? GondolaController.edit.url({
                    subdomain: props.subdomain,
                    planogram: props.planogram.id,
                    gondola: props.gondola!.id,
                })
                : GondolaController.create.url({
                    subdomain: props.subdomain,
                    planogram: props.planogram.id,
                }),
        },
    ],
});
</script>

<template>
    <Head :title="pageMeta.headTitle" />

    <div class="p-4">
        <Form
            v-bind="isEdit
                ? GondolaController.update.form({
                    subdomain: props.subdomain,
                    planogram: props.planogram.id,
                    gondola: props.gondola!.id,
                })
                : GondolaController.store.form({
                    subdomain: props.subdomain,
                    planogram: props.planogram.id,
                })"
            v-slot="{ errors, processing }"
        >
            <input type="hidden" name="planogram_id" :value="props.planogram.id" />

            <FormCard
                :title="pageMeta.title"
                :description="`${pageMeta.description} ${t('app.tenant.gondolas.planogram_prefix')}: ${props.planogram.name ?? '-'}`"
                :processing="processing"
                :cancel-href="gondolasIndexPath"
            >
                <template #icon>
                    <PanelTop class="size-5" />
                </template>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                    <FormTextField
                        id="name"
                        name="name"
                        :label="t('app.tenant.gondolas.fields.name')"
                        :default-value="props.gondola?.name ?? ''"
                        :error="errors.name"
                        class="md:col-span-5"
                        required
                    />

                    <FormTextField
                        id="slug"
                        name="slug"
                        label="Slug"
                        :default-value="props.gondola?.slug ?? ''"
                        :error="errors.slug"
                        class="md:col-span-3"
                    />

                    <FormTextField
                        id="num_modulos"
                        name="num_modulos"
                        type="number"
                        :label="t('app.tenant.gondolas.fields.modules')"
                        :default-value="String(props.gondola?.num_modulos ?? 1)"
                        :error="errors.num_modulos"
                        class="md:col-span-2"
                        required
                    />

                    <FormTextField
                        id="scale_factor"
                        name="scale_factor"
                        type="number"
                        step="0.01"
                        :label="t('app.tenant.gondolas.fields.scale_factor')"
                        :default-value="String(props.gondola?.scale_factor ?? 1)"
                        :error="errors.scale_factor"
                        class="md:col-span-2"
                        required
                    />

                    <FormTextField
                        id="location"
                        name="location"
                        :label="t('app.tenant.gondolas.fields.location')"
                        :default-value="props.gondola?.location ?? ''"
                        :error="errors.location"
                        class="md:col-span-4"
                    />

                    <FormTextField
                        id="side"
                        name="side"
                        :label="t('app.tenant.gondolas.fields.side')"
                        :default-value="props.gondola?.side ?? ''"
                        :error="errors.side"
                        class="md:col-span-2"
                    />

                    <FormSelectField
                        id="flow"
                        name="flow"
                        :label="t('app.tenant.gondolas.fields.flow')"
                        :default-value="props.gondola?.flow ?? 'left_to_right'"
                        :error="errors.flow"
                        class="md:col-span-3"
                        required
                    >
                        <option value="left_to_right">left_to_right</option>
                        <option value="right_to_left">right_to_left</option>
                    </FormSelectField>

                    <FormSelectField
                        id="alignment"
                        name="alignment"
                        :label="t('app.tenant.gondolas.fields.alignment')"
                        :default-value="props.gondola?.alignment ?? 'justify'"
                        :error="errors.alignment"
                        class="md:col-span-3"
                        required
                    >
                        <option value="left">left</option>
                        <option value="right">right</option>
                        <option value="center">center</option>
                        <option value="justify">justify</option>
                    </FormSelectField>

                    <FormStatusField
                        id="status"
                        name="status"
                        :label="t('app.tenant.gondolas.fields.status')"
                        :default-value="props.gondola?.status ?? 'draft'"
                        :error="errors.status"
                        class="md:col-span-3"
                        :options="[
                            { value: 'draft', label: 'Draft' },
                            { value: 'published', label: 'Published' },
                        ]"
                    />
                </div>
            </FormCard>
        </Form>
    </div>
</template>

<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Layers } from 'lucide-vue-next';
import WorkflowTemplateController from '@/actions/App/Http/Controllers/Tenant/WorkflowTemplateController';
import WorkflowKanbanController from '@/actions/App/Http/Controllers/Tenant/WorkflowKanbanController';
import AppLayout from '@/layouts/AppLayout.vue';
import FormCard from '@/components/FormCard.vue';
import FormTextField from '@/components/form/FormTextField.vue';
import FormTextareaField from '@/components/form/FormTextareaField.vue';
import FormSelectField from '@/components/form/FormSelectField.vue';
import FormStatusField from '@/components/form/FormStatusField.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';

type TemplatePayload = {
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
    status: 'draft' | 'published';
    user_ids: string[];
};

const props = defineProps<{
    subdomain: string;
    template: TemplatePayload | null;
    users: Array<{ id: string; name: string }>;
    existing_templates: Array<{ id: string; name: string }>;
}>();

const { t } = useT();
const isEdit = computed(() => props.template !== null);

const indexPath = WorkflowTemplateController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const kanbanPath = WorkflowKanbanController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');

const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value
        ? t('app.kanban.templates.actions.edit')
        : t('app.kanban.templates.actions.new'),
    title: isEdit.value
        ? t('app.kanban.templates.actions.edit')
        : t('app.kanban.templates.actions.new'),
    description: t('app.kanban.templates.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.kanban.navigation'), href: kanbanPath },
        { title: t('app.kanban.templates.navigation'), href: indexPath },
        {
            title: isEdit.value
                ? t('app.kanban.templates.actions.edit')
                : t('app.kanban.templates.actions.new'),
            href: isEdit.value
                ? WorkflowTemplateController.edit.url({ subdomain: props.subdomain, template: props.template!.id })
                : WorkflowTemplateController.create.url(props.subdomain),
        },
    ],
});
</script>

<template>
    <Head :title="pageMeta.headTitle" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="p-4">
            <Form
                v-bind="
                    isEdit
                        ? WorkflowTemplateController.update.form({ subdomain: props.subdomain, template: props.template!.id })
                        : WorkflowTemplateController.store.form(props.subdomain)
                "
                v-slot="{ errors, processing }"
            >
                <FormCard :processing="processing" :cancel-href="indexPath">
                    <template #icon>
                        <Layers class="size-5" />
                    </template>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                        <!-- Nome -->
                        <FormTextField
                            id="name"
                            name="name"
                            :label="t('app.kanban.templates.fields.name')"
                            :default-value="props.template?.name ?? ''"
                            :error="errors.name"
                            class="md:col-span-8"
                            required
                        />

                        <!-- Ordem sugerida -->
                        <FormTextField
                            id="suggested_order"
                            name="suggested_order"
                            type="number"
                            :label="t('app.kanban.templates.fields.suggested_order')"
                            :default-value="String(props.template?.suggested_order ?? 0)"
                            :error="errors.suggested_order"
                            class="md:col-span-2"
                            min="0"
                        />

                        <!-- Status -->
                        <FormStatusField
                            id="status"
                            name="status"
                            :label="t('app.kanban.templates.fields.status')"
                            :default-value="props.template?.status ?? 'draft'"
                            :error="errors.status"
                            class="md:col-span-2"
                            :options="[
                                { value: 'draft', label: 'Draft' },
                                { value: 'published', label: 'Published' },
                            ]"
                        />

                        <!-- Slug -->
                        <FormTextField
                            id="slug"
                            name="slug"
                            label="Slug"
                            :default-value="props.template?.slug ?? ''"
                            :error="errors.slug"
                            class="md:col-span-4"
                        />

                        <!-- Duracao estimada -->
                        <FormTextField
                            id="estimated_duration_days"
                            name="estimated_duration_days"
                            type="number"
                            :label="t('app.kanban.templates.fields.estimated_duration_days')"
                            :default-value="String(props.template?.estimated_duration_days ?? '')"
                            :error="errors.estimated_duration_days"
                            class="md:col-span-2"
                            min="1"
                        />

                        <!-- Cor -->
                        <FormTextField
                            id="color"
                            name="color"
                            type="color"
                            :label="t('app.kanban.templates.fields.color')"
                            :default-value="props.template?.color ?? '#6366f1'"
                            :error="errors.color"
                            class="md:col-span-2"
                        />

                        <!-- Icone -->
                        <FormTextField
                            id="icon"
                            name="icon"
                            :label="t('app.kanban.templates.fields.icon')"
                            :default-value="props.template?.icon ?? ''"
                            :error="errors.icon"
                            class="md:col-span-4"
                            placeholder="ex: check-circle"
                        />

                        <!-- Etapa anterior -->
                        <FormSelectField
                            id="template_previous_step_id"
                            name="template_previous_step_id"
                            :label="t('app.kanban.templates.fields.previous_step')"
                            :default-value="props.template?.template_previous_step_id ?? ''"
                            :error="errors.template_previous_step_id"
                            class="md:col-span-6"
                        >
                            <option value="">—</option>
                            <option
                                v-for="tpl in props.existing_templates"
                                :key="tpl.id"
                                :value="tpl.id"
                            >
                                {{ tpl.name }}
                            </option>
                        </FormSelectField>

                        <!-- Proxima etapa -->
                        <FormSelectField
                            id="template_next_step_id"
                            name="template_next_step_id"
                            :label="t('app.kanban.templates.fields.next_step')"
                            :default-value="props.template?.template_next_step_id ?? ''"
                            :error="errors.template_next_step_id"
                            class="md:col-span-6"
                        >
                            <option value="">—</option>
                            <option
                                v-for="tpl in props.existing_templates"
                                :key="tpl.id"
                                :value="tpl.id"
                            >
                                {{ tpl.name }}
                            </option>
                        </FormSelectField>

                        <!-- Obrigatoria por padrao -->
                        <div class="flex items-center gap-2 md:col-span-4">
                            <input
                                id="is_required_by_default"
                                name="is_required_by_default"
                                type="checkbox"
                                :checked="props.template?.is_required_by_default ?? false"
                                value="1"
                                class="size-4 rounded border-input accent-primary"
                            />
                            <label for="is_required_by_default" class="text-sm text-foreground">
                                {{ t('app.kanban.templates.fields.is_required_by_default') }}
                            </label>
                        </div>

                        <!-- Usuarios sugeridos -->
                        <div class="md:col-span-8">
                            <label class="mb-1 block text-sm font-medium text-foreground">
                                {{ t('app.kanban.templates.fields.suggested_users') }}
                            </label>
                            <div class="flex flex-wrap gap-2 rounded-lg border border-input bg-background p-3">
                                <label
                                    v-for="user in props.users"
                                    :key="user.id"
                                    class="flex cursor-pointer items-center gap-1.5 rounded-md border border-border px-2 py-1 text-sm transition hover:bg-muted/50"
                                    :class="(props.template?.user_ids ?? []).includes(user.id) ? 'border-primary/60 bg-primary/8 text-primary' : ''"
                                >
                                    <input
                                        type="checkbox"
                                        name="user_ids[]"
                                        :value="user.id"
                                        :checked="(props.template?.user_ids ?? []).includes(user.id)"
                                        class="size-3.5 accent-primary"
                                    />
                                    {{ user.name }}
                                </label>
                                <span v-if="props.users.length === 0" class="text-sm text-muted-foreground">
                                    Nenhum usuario disponivel.
                                </span>
                            </div>
                        </div>

                        <!-- Descricao -->
                        <FormTextareaField
                            id="description"
                            name="description"
                            :label="t('app.kanban.templates.fields.description')"
                            :default-value="props.template?.description ?? ''"
                            :error="errors.description"
                            class="md:col-span-12"
                            :rows="3"
                        />
                    </div>
                </FormCard>
            </Form>
        </div>
    </AppLayout>
</template>

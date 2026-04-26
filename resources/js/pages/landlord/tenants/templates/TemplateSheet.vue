<script setup lang="ts">
import { Layers } from 'lucide-vue-next';
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import WorkflowTemplateController from '@/actions/App/Http/Controllers/Landlord/WorkflowTemplateController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useT } from '@/composables/useT';
import type { TemplateRow, UserOption, TemplateOption } from './Index.vue';

type TenantPayload = {
    id: string;
    name: string;
    slug: string;
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    template: TemplateRow | null;
    tenant: TenantPayload;
    users: UserOption[];
    existingTemplates: TemplateOption[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { t } = useT();

// Track form key to force re-mount when switching create <-> edit
const formKey = ref(0);
watch(() => [props.open, props.template?.id], () => {
    formKey.value++;
});

const statusOptions = [
    { value: 'draft', label: 'Rascunho' },
    { value: 'published', label: 'Publicado' },
];

const templatesForNextPrev = computed(() =>
    props.existingTemplates.filter((t) => t.id !== props.template?.id),
);
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent class="w-full p-0 sm:max-w-xl">
            <div class="flex h-full flex-col">
                <!-- Header -->
                <div class="shrink-0 border-b border-sidebar-border/70 px-6 py-4 dark:border-sidebar-border">
                    <SheetHeader class="space-y-0 text-left">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-10 items-center justify-center rounded-full border border-sidebar-border/70 bg-primary/10 dark:border-sidebar-border"
                            >
                                <Layers class="size-5 text-primary" />
                            </div>
                            <SheetTitle class="text-base">
                                {{
                                    mode === 'create'
                                        ? t('app.landlord.kanban.templates.create_template')
                                        : `${t('app.landlord.kanban.templates.edit_template')}: ${template?.name}`
                                }}
                            </SheetTitle>
                        </div>
                    </SheetHeader>
                </div>

                <!-- Body -->
                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-6">
                    <Form
                        :key="formKey"
                        v-bind="
                            mode === 'create'
                                ? WorkflowTemplateController.store.form({ tenant: tenant.id })
                                : WorkflowTemplateController.update.form({ tenant: tenant.id, template: template!.id })
                        "
                        class="flex min-h-full flex-col"
                        v-slot="{ errors, processing }"
                    >
                        <div class="space-y-6">
                            <!-- Identity -->
                            <div>
                                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Identificação
                                </p>
                                <Separator class="mb-4" />
                                <div class="grid gap-4">
                                    <div class="grid gap-2">
                                        <Label for="tpl_name">{{ t('app.landlord.kanban.templates.fields.name') }}</Label>
                                        <Input
                                            id="tpl_name"
                                            name="name"
                                            required
                                            :value="template?.name"
                                        />
                                        <InputError :message="errors.name" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="tpl_slug">{{ t('app.landlord.kanban.templates.fields.slug') }}</Label>
                                        <Input id="tpl_slug" name="slug" :value="template?.slug" />
                                        <InputError :message="errors.slug" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="tpl_description">{{ t('app.landlord.kanban.templates.fields.description') }}</Label>
                                        <textarea
                                            id="tpl_description"
                                            name="description"
                                            rows="3"
                                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/20"
                                            :value="template?.description ?? ''"
                                        />
                                        <InputError :message="errors.description" />
                                    </div>
                                </div>
                            </div>

                            <!-- Configuration -->
                            <div>
                                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Configuração
                                </p>
                                <Separator class="mb-4" />
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label for="tpl_order">{{ t('app.landlord.kanban.templates.fields.suggested_order') }}</Label>
                                        <Input
                                            id="tpl_order"
                                            name="suggested_order"
                                            type="number"
                                            min="1"
                                            :value="template?.suggested_order ?? 1"
                                        />
                                        <InputError :message="errors.suggested_order" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="tpl_duration">{{ t('app.landlord.kanban.templates.fields.estimated_duration_days') }}</Label>
                                        <Input
                                            id="tpl_duration"
                                            name="estimated_duration_days"
                                            type="number"
                                            min="1"
                                            :value="template?.estimated_duration_days ?? ''"
                                        />
                                        <InputError :message="errors.estimated_duration_days" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="tpl_color">{{ t('app.landlord.kanban.templates.fields.color') }}</Label>
                                        <div class="flex items-center gap-2">
                                            <input
                                                id="tpl_color"
                                                name="color"
                                                type="color"
                                                class="size-9 cursor-pointer rounded-md border border-input bg-background p-1"
                                                :value="template?.color ?? '#6366f1'"
                                            />
                                            <Input
                                                name="color"
                                                class="flex-1"
                                                placeholder="#6366f1"
                                                :value="template?.color ?? ''"
                                            />
                                        </div>
                                        <InputError :message="errors.color" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="tpl_icon">{{ t('app.landlord.kanban.templates.fields.icon') }}</Label>
                                        <Input id="tpl_icon" name="icon" :value="template?.icon ?? ''" placeholder="lucide:layers" />
                                        <InputError :message="errors.icon" />
                                    </div>

                                    <div class="grid gap-2 sm:col-span-2">
                                        <Label for="tpl_status">{{ t('app.landlord.kanban.templates.fields.status') }}</Label>
                                        <select
                                            id="tpl_status"
                                            name="status"
                                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/20"
                                        >
                                            <option
                                                v-for="opt in statusOptions"
                                                :key="opt.value"
                                                :value="opt.value"
                                                :selected="(template?.status ?? 'draft') === opt.value"
                                            >
                                                {{ opt.label }}
                                            </option>
                                        </select>
                                        <InputError :message="errors.status" />
                                    </div>

                                    <div class="flex items-center gap-2 sm:col-span-2">
                                        <input
                                            id="tpl_required"
                                            name="is_required_by_default"
                                            type="checkbox"
                                            value="1"
                                            class="size-4 cursor-pointer rounded border-input text-primary focus:ring-primary/20"
                                            :checked="template?.is_required_by_default ?? false"
                                        />
                                        <Label for="tpl_required" class="cursor-pointer">
                                            {{ t('app.landlord.kanban.templates.fields.is_required_by_default') }}
                                        </Label>
                                    </div>
                                </div>
                            </div>

                            <!-- Workflow chain -->
                            <div>
                                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Encadeamento
                                </p>
                                <Separator class="mb-4" />
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label for="tpl_prev">{{ t('app.landlord.kanban.templates.fields.previous_step') }}</Label>
                                        <select
                                            id="tpl_prev"
                                            name="template_previous_step_id"
                                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/20"
                                        >
                                            <option value="">Nenhuma</option>
                                            <option
                                                v-for="opt in templatesForNextPrev"
                                                :key="opt.id"
                                                :value="opt.id"
                                                :selected="template?.template_previous_step_id === opt.id"
                                            >
                                                {{ opt.name }}
                                            </option>
                                        </select>
                                        <InputError :message="errors.template_previous_step_id" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="tpl_next">{{ t('app.landlord.kanban.templates.fields.next_step') }}</Label>
                                        <select
                                            id="tpl_next"
                                            name="template_next_step_id"
                                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/20"
                                        >
                                            <option value="">Nenhuma</option>
                                            <option
                                                v-for="opt in templatesForNextPrev"
                                                :key="opt.id"
                                                :value="opt.id"
                                                :selected="template?.template_next_step_id === opt.id"
                                            >
                                                {{ opt.name }}
                                            </option>
                                        </select>
                                        <InputError :message="errors.template_next_step_id" />
                                    </div>
                                </div>
                            </div>

                            <!-- Suggested users -->
                            <div v-if="users.length > 0">
                                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    {{ t('app.landlord.kanban.templates.fields.suggested_users') }}
                                </p>
                                <Separator class="mb-4" />
                                <div class="grid grid-cols-1 gap-2">
                                    <label
                                        v-for="user in users"
                                        :key="user.id"
                                        class="flex cursor-pointer items-center gap-2 rounded-md border border-transparent px-2 py-1.5 text-sm transition hover:border-border hover:bg-muted/40"
                                    >
                                        <input
                                            type="checkbox"
                                            name="user_ids[]"
                                            :value="user.id"
                                            :checked="template?.user_ids?.includes(user.id) ?? false"
                                            class="size-4 rounded border-input text-primary focus:ring-primary/20"
                                        />
                                        {{ user.name }}
                                    </label>
                                </div>
                                <InputError :message="errors['user_ids']" />
                            </div>
                        </div>

                        <!-- Footer actions -->
                        <div class="mt-auto flex items-center justify-end gap-2 border-t border-border pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="emit('update:open', false)"
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" variant="gradient" size="sm" :disabled="processing">
                                {{ mode === 'create' ? 'Criar etapa' : 'Salvar alterações' }}
                            </Button>
                        </div>
                    </Form>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>

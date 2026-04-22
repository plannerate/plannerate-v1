<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed } from 'vue';
import { FolderTree } from 'lucide-vue-next';
import CategoryController from '@/actions/App/Http/Controllers/Tenant/CategoryController';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    category: CategoryPayload | null;
    parent_categories: Array<{ id: string; name: string }>;
}>();

const { t } = useT();
const isEdit = computed(() => props.category !== null);
const categoriesIndexPath = CategoryController.index.url().replace(/^\/\/[^/]+/, '');

setLayoutProps({
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.categories.navigation'), href: categoriesIndexPath },
        {
            title: isEdit.value ? t('app.tenant.categories.actions.edit') : t('app.tenant.categories.actions.new'),
            href: isEdit.value ? CategoryController.edit.url(props.category!.id) : CategoryController.create.url(),
        },
    ],
});
</script>

<template>
    <Head :title="isEdit ? t('app.tenant.categories.actions.edit') : t('app.tenant.categories.actions.new')" />

    <div class="p-4">
        <Form
            v-bind="isEdit ? CategoryController.update.form(props.category!.id) : CategoryController.store.form()"
            v-slot="{ errors, processing }"
        >
            <FormCard
                :title="isEdit ? t('app.tenant.categories.actions.edit') : t('app.tenant.categories.actions.new')"
                :description="t('app.tenant.categories.description')"
                :processing="processing"
                :cancel-href="categoriesIndexPath"
            >
                <template #icon>
                    <FolderTree class="size-5" />
                </template>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">{{ t('app.tenant.categories.fields.name') }}</Label>
                        <Input id="name" name="name" :default-value="props.category?.name ?? ''" required />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input id="slug" name="slug" :default-value="props.category?.slug ?? ''" />
                        <InputError :message="errors.slug" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="category_id">{{ t('app.tenant.categories.fields.parent') }}</Label>
                        <select
                            id="category_id"
                            name="category_id"
                            :value="props.category?.category_id ?? ''"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">{{ t('app.tenant.common.all') }}</option>
                            <option v-for="parent in props.parent_categories" :key="parent.id" :value="parent.id">
                                {{ parent.name }}
                            </option>
                        </select>
                        <InputError :message="errors.category_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="codigo">{{ t('app.tenant.categories.fields.codigo') }}</Label>
                        <Input id="codigo" name="codigo" type="number" :default-value="props.category?.codigo ?? ''" />
                        <InputError :message="errors.codigo" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="status">{{ t('app.tenant.categories.fields.status') }}</Label>
                        <select id="status" name="status" :value="props.category?.status ?? 'draft'" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="importer">Importer</option>
                        </select>
                        <InputError :message="errors.status" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="level_name">{{ t('app.tenant.categories.fields.level_name') }}</Label>
                        <Input id="level_name" name="level_name" :default-value="props.category?.level_name ?? ''" />
                        <InputError :message="errors.level_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="nivel">{{ t('app.tenant.categories.fields.nivel') }}</Label>
                        <Input id="nivel" name="nivel" :default-value="props.category?.nivel ?? ''" />
                        <InputError :message="errors.nivel" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="hierarchy_position">{{ t('app.tenant.categories.fields.hierarchy_position') }}</Label>
                        <Input id="hierarchy_position" name="hierarchy_position" type="number" min="1" max="7" :default-value="props.category?.hierarchy_position ?? ''" />
                        <InputError :message="errors.hierarchy_position" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="full_path">{{ t('app.tenant.categories.fields.full_path') }}</Label>
                    <Input id="full_path" name="full_path" :default-value="props.category?.full_path ?? ''" />
                    <InputError :message="errors.full_path" />
                </div>

                <div class="grid gap-2">
                    <Label for="description">{{ t('app.tenant.categories.fields.description') }}</Label>
                    <Input id="description" name="description" :default-value="props.category?.description ?? ''" />
                    <InputError :message="errors.description" />
                </div>

                <input type="hidden" name="hierarchy_path[]" :value="(props.category?.hierarchy_path ?? []).join(' > ')" />

                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-border bg-muted/30 px-4 py-3 transition-colors hover:bg-muted/50 has-checked:border-primary/50 has-checked:bg-primary/5">
                    <input type="hidden" name="is_placeholder" value="0" />
                    <input id="is_placeholder" name="is_placeholder" type="checkbox" value="1" :checked="props.category?.is_placeholder ?? false" class="accent-primary" />
                    <div>
                        <span class="text-sm font-medium">{{ t('app.tenant.categories.fields.is_placeholder') }}</span>
                    </div>
                    <InputError :message="errors.is_placeholder" />
                </label>
            </FormCard>
        </Form>
    </div>
</template>

<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Blocks } from 'lucide-vue-next';
import { computed } from 'vue';
import ModuleController from '@/actions/App/Http/Controllers/Landlord/ModuleController';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';

type ModulePayload = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
};

const props = defineProps<{
    module: ModulePayload | null;
}>();

const { t } = useT();
const isEdit = computed(() => props.module !== null);
const modulesIndexPath = ModuleController.index.url().replace(/^\/\/[^/]+/, '');

const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value ? t('app.landlord.modules.actions.edit') : t('app.landlord.modules.actions.new'),
    title: isEdit.value ? t('app.landlord.modules.actions.edit') : t('app.landlord.modules.actions.new'),
    description: t('app.landlord.modules.description'),
    breadcrumbs: [
        {
            title: t('app.landlord.modules.navigation'),
            href: modulesIndexPath,
        },
        {
            title: isEdit.value ? t('app.landlord.common.edit') : t('app.landlord.common.create'),
            href: isEdit.value ? tenantWayfinderPath(ModuleController.edit.url(props.module!.id)) : tenantWayfinderPath(ModuleController.create.url()),
        },
    ],
});
</script>

<template>
    <Head :title="pageMeta.headTitle" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="p-4">
            <Form
                v-bind="isEdit
                    ? { ...ModuleController.update.form(props.module!.id), action: tenantWayfinderPath(ModuleController.update.form(props.module!.id).action) }
                    : { ...ModuleController.store.form(), action: tenantWayfinderPath(ModuleController.store.form().action) }"
                v-slot="{ errors, processing }"
            >
                <FormCard
                    :processing="processing"
                    :cancel-href="modulesIndexPath"
                    :title="pageMeta.title"
                    :description="pageMeta.description"
                >
                    <template #icon>
                        <Blocks class="size-5" />
                    </template>

                    <div class="grid gap-2">
                        <Label for="name">{{ t('app.landlord.modules.fields.name') }}</Label>
                        <Input id="name" name="name" :default-value="props.module?.name ?? ''" required />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input id="slug" name="slug" :default-value="props.module?.slug ?? ''" required />
                        <InputError :message="errors.slug" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">{{ t('app.landlord.modules.fields.description') }}</Label>
                        <textarea
                            id="description"
                            name="description"
                            rows="3"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                        :value="props.module?.description ?? ''"></textarea>
                        <InputError :message="errors.description" />
                    </div>

                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-border bg-muted/30 px-4 py-3 transition-colors hover:bg-muted/50 has-checked:border-primary/50 has-checked:bg-primary/5">
                        <input type="hidden" name="is_active" value="0" />
                        <input id="is_active" name="is_active" type="checkbox" value="1" :checked="props.module?.is_active ?? true" class="accent-primary" />
                        <div>
                            <span class="text-sm font-medium">{{ t('app.landlord.modules.fields.is_active') }}</span>
                            <p class="text-xs text-muted-foreground">Modulos inativos nao aparecem para ativacao em tenants.</p>
                        </div>
                        <InputError :message="errors.is_active" />
                    </label>
                </FormCard>
            </Form>
        </div>
    </AppLayout>
</template>

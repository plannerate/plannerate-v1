<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Link } from 'lucide-vue-next';
import { computed } from 'vue';
import UsefulLinkController from '@/actions/App/Http/Controllers/Landlord/UsefulLinkController';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';

type UsefulLinkPayload = {
    id: string;
    name: string;
    url: string;
    logo: string | null;
    description: string | null;
    show_on_tenant_dashboard: boolean;
};

const props = defineProps<{
    useful_link: UsefulLinkPayload | null;
}>();

const { t } = useT();
const isEdit = computed(() => props.useful_link !== null);
const usefulLinksIndexPath = UsefulLinkController.index.url().replace(/^\/\/[^/]+/, '');

const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value ? t('app.landlord.useful_links.actions.edit') : t('app.landlord.useful_links.actions.new'),
    title: isEdit.value ? t('app.landlord.useful_links.actions.edit') : t('app.landlord.useful_links.actions.new'),
    description: t('app.landlord.useful_links.description'),
    breadcrumbs: [
        {
            title: t('app.landlord.useful_links.navigation'),
            href: usefulLinksIndexPath,
        },
        {
            title: isEdit.value ? t('app.landlord.common.edit') : t('app.landlord.common.create'),
            href: isEdit.value ? tenantWayfinderPath(UsefulLinkController.edit.url(props.useful_link!.id)) : tenantWayfinderPath(UsefulLinkController.create.url()),
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
                    ? { ...UsefulLinkController.update.form(props.useful_link!.id), action: tenantWayfinderPath(UsefulLinkController.update.url(props.useful_link!.id)) }
                    : { ...UsefulLinkController.store.form(), action: tenantWayfinderPath(UsefulLinkController.store.url()) }"
                v-slot="{ errors, processing }"
            >
                <FormCard
                    :processing="processing"
                    :cancel-href="usefulLinksIndexPath"
                >
                    <template #icon>
                        <Link class="size-5" />
                    </template>

                    <div class="grid gap-2">
                        <Label for="name">{{ t('app.landlord.useful_links.fields.name') }}</Label>
                        <Input id="name" name="name" :default-value="props.useful_link?.name ?? ''" required />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="url">{{ t('app.landlord.useful_links.fields.url') }}</Label>
                        <Input id="url" name="url" type="url" :default-value="props.useful_link?.url ?? ''" required />
                        <InputError :message="errors.url" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="logo">{{ t('app.landlord.useful_links.fields.logo') }}</Label>
                        <Input id="logo" name="logo" type="url" :default-value="props.useful_link?.logo ?? ''" />
                        <InputError :message="errors.logo" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">{{ t('app.landlord.useful_links.fields.description') }}</Label>
                        <textarea
                            id="description"
                            name="description"
                            rows="3"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                            :value="props.useful_link?.description ?? ''"
                        />
                        <InputError :message="errors.description" />
                    </div>

                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-border bg-muted/30 px-4 py-3 transition-colors hover:bg-muted/50 has-checked:border-primary/50 has-checked:bg-primary/5">
                        <input type="hidden" name="show_on_tenant_dashboard" value="0" />
                        <input
                            id="show_on_tenant_dashboard"
                            name="show_on_tenant_dashboard"
                            type="checkbox"
                            value="1"
                            :checked="props.useful_link?.show_on_tenant_dashboard ?? false"
                            class="accent-primary"
                        />
                        <div>
                            <span class="text-sm font-medium">{{ t('app.landlord.useful_links.fields.show_on_tenant_dashboard') }}</span>
                            <p class="text-xs text-muted-foreground">Quando ativo, o link aparece no dashboard dos tenants.</p>
                        </div>
                        <InputError :message="errors.show_on_tenant_dashboard" />
                    </label>
                </FormCard>
            </Form>
        </div>
    </AppLayout>
</template>

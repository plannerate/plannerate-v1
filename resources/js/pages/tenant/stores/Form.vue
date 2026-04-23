<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Store as StoreIcon } from 'lucide-vue-next';
import StoreController from '@/actions/App/Http/Controllers/Tenant/StoreController';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';

type StorePayload = {
    id: string;
    name: string | null;
    document: string | null;
    slug: string | null;
    code: string | null;
    phone: string | null;
    email: string | null;
    status: 'draft' | 'published';
    description: string | null;
};

const props = defineProps<{
    subdomain: string;
    store: StorePayload | null;
}>();

const { t } = useT();
const isEdit = computed(() => props.store !== null);
const storesIndexPath = StoreController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
</script>

<template>
    <Head :title="isEdit ? t('app.tenant.stores.actions.edit') : t('app.tenant.stores.actions.new')" />

    <div class="p-4">
        <Form
            v-bind="isEdit
                ? StoreController.update.form({ subdomain: props.subdomain, store: props.store!.id })
                : StoreController.store.form(props.subdomain)"
            v-slot="{ errors, processing }"
        >
            <FormCard
                :title="isEdit ? t('app.tenant.stores.actions.edit') : t('app.tenant.stores.actions.new')"
                :description="t('app.tenant.stores.description')"
                :processing="processing"
                :cancel-href="storesIndexPath"
            >
                <template #icon>
                    <StoreIcon class="size-5" />
                </template>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">{{ t('app.tenant.stores.fields.name') }}</Label>
                        <Input id="name" name="name" :default-value="props.store?.name ?? ''" required />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="document">{{ t('app.tenant.stores.fields.document') }}</Label>
                        <Input id="document" name="document" :default-value="props.store?.document ?? ''" />
                        <InputError :message="errors.document" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input id="slug" name="slug" :default-value="props.store?.slug ?? ''" />
                        <InputError :message="errors.slug" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="code">{{ t('app.tenant.stores.fields.code') }}</Label>
                        <Input id="code" name="code" :default-value="props.store?.code ?? ''" />
                        <InputError :message="errors.code" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="phone">{{ t('app.tenant.stores.fields.phone') }}</Label>
                        <Input id="phone" name="phone" :default-value="props.store?.phone ?? ''" />
                        <InputError :message="errors.phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="email">{{ t('app.tenant.stores.fields.email') }}</Label>
                        <Input id="email" name="email" type="email" :default-value="props.store?.email ?? ''" />
                        <InputError :message="errors.email" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="status">{{ t('app.tenant.stores.fields.status') }}</Label>
                        <select id="status" name="status" :value="props.store?.status ?? 'draft'" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </select>
                        <InputError :message="errors.status" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">{{ t('app.tenant.stores.fields.description') }}</Label>
                        <Input id="description" name="description" :default-value="props.store?.description ?? ''" />
                        <InputError :message="errors.description" />
                    </div>
                </div>
            </FormCard>
        </Form>
    </div>
</template>

<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Truck } from 'lucide-vue-next';
import ProviderController from '@/actions/App/Http/Controllers/Tenant/ProviderController';
import AddressFields from '@/components/form/AddressFields.vue';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';

type ProviderPayload = {
    id: string;
    code: string | null;
    name: string | null;
    email: string | null;
    phone: string | null;
    cnpj: string | null;
    is_default: boolean;
    description: string | null;
};

const props = defineProps<{
    subdomain: string;
    provider: ProviderPayload | null;
}>();

const { t } = useT();
const isEdit = computed(() => props.provider !== null);
const providersIndexPath = ProviderController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
</script>

<template>
    <Head :title="isEdit ? t('app.tenant.providers.actions.edit') : t('app.tenant.providers.actions.new')" />

    <div class="p-4">
        <Form
            v-bind="isEdit
                ? ProviderController.update.form({ subdomain: props.subdomain, provider: props.provider!.id })
                : ProviderController.store.form(props.subdomain)"
            v-slot="{ errors, processing }"
        >
            <FormCard
                :title="isEdit ? t('app.tenant.providers.actions.edit') : t('app.tenant.providers.actions.new')"
                :description="t('app.tenant.providers.description')"
                :processing="processing"
                :cancel-href="providersIndexPath"
            >
                <template #icon>
                    <Truck class="size-5" />
                </template>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">{{ t('app.tenant.providers.fields.name') }}</Label>
                        <Input id="name" name="name" :default-value="props.provider?.name ?? ''" required />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="code">{{ t('app.tenant.providers.fields.code') }}</Label>
                        <Input id="code" name="code" :default-value="props.provider?.code ?? ''" />
                        <InputError :message="errors.code" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="cnpj">{{ t('app.tenant.providers.fields.cnpj') }}</Label>
                        <Input id="cnpj" name="cnpj" :default-value="props.provider?.cnpj ?? ''" />
                        <InputError :message="errors.cnpj" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="phone">{{ t('app.tenant.providers.fields.phone') }}</Label>
                        <Input id="phone" name="phone" :default-value="props.provider?.phone ?? ''" />
                        <InputError :message="errors.phone" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="email">{{ t('app.tenant.providers.fields.email') }}</Label>
                        <Input id="email" name="email" type="email" :default-value="props.provider?.email ?? ''" />
                        <InputError :message="errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">{{ t('app.tenant.providers.fields.description') }}</Label>
                        <Input id="description" name="description" :default-value="props.provider?.description ?? ''" />
                        <InputError :message="errors.description" />
                    </div>
                </div>

                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-border bg-muted/30 px-4 py-3 transition-colors hover:bg-muted/50 has-checked:border-primary/50 has-checked:bg-primary/5">
                    <input type="hidden" name="is_default" value="0" />
                    <input id="is_default" name="is_default" type="checkbox" value="1" :checked="props.provider?.is_default ?? true" class="accent-primary" />
                    <div>
                        <span class="text-sm font-medium">{{ t('app.tenant.providers.fields.is_default') }}</span>
                    </div>
                    <InputError :message="errors.is_default" />
                </label>

                <AddressFields :errors="errors" />
            </FormCard>
        </Form>
    </div>
</template>

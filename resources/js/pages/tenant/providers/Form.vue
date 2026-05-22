<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Truck } from 'lucide-vue-next';
import { computed } from 'vue';
import ProviderController from '@/actions/App/Http/Controllers/Tenant/ProviderController';
import FormTextField from '@/components/form/FormTextField.vue';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

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

type AddressPayload = {
    id: string;
    type: string | null;
    name: string | null;
    zip_code: string | null;
    street: string | null;
    number: string | null;
    complement: string | null;
    reference: string | null;
    additional_information: string | null;
    district: string | null;
    city: string | null;
    state: string | null;
    country: string | null;
    is_default: boolean;
    status: 'draft' | 'published';
};

const props = defineProps<{
    provider: ProviderPayload | null;
    address: AddressPayload | null;
}>();

const { t } = useT();
const isEdit = computed(() => props.provider !== null);
const providersIndexPath = ProviderController.index.url().replace(/^\/\/[^/]+/, '');
const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value ? t('app.tenant.providers.actions.edit') : t('app.tenant.providers.actions.new'),
    title: isEdit.value ? t('app.tenant.providers.actions.edit') : t('app.tenant.providers.actions.new'),
    description: t('app.tenant.providers.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.providers.navigation'), href: providersIndexPath },
        {
            title: isEdit.value ? t('app.tenant.providers.actions.edit') : t('app.tenant.providers.actions.new'),
            href: isEdit.value
                ? ProviderController.edit.url({ provider: props.provider!.id })
                : ProviderController.create.url(),
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
                ? ProviderController.update.form({ provider: props.provider!.id })
                : ProviderController.store.form()"
            v-slot="{ errors, processing }"
        >
            <FormCard
                :processing="processing"
                :cancel-href="providersIndexPath"
                :title="pageMeta.title"
                :description="pageMeta.description"
            >
                <template #icon>
                    <Truck class="size-5" />
                </template>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                    <FormTextField
                        id="name"
                        name="name"
                        label="Razão Social"
                        :default-value="props.provider?.name ?? ''"
                        :error="errors.name"
                        class="md:col-span-6"
                        required
                    />

                    <FormTextField
                        id="description"
                        name="description"
                        label="Nome fantasia"
                        :default-value="props.provider?.description ?? ''"
                        :error="errors.description"
                        class="md:col-span-6"
                    />

                    <FormTextField
                        id="cnpj"
                        name="cnpj"
                        label="CNPJ"
                        :default-value="props.provider?.cnpj ?? ''"
                        :error="errors.cnpj"
                        class="md:col-span-4"
                    />

                    <FormTextField
                        id="email"
                        name="email"
                        type="email"
                        label="Email"
                        :default-value="props.provider?.email ?? ''"
                        :error="errors.email"
                        class="md:col-span-4"
                    />

                    <FormTextField
                        id="phone"
                        name="phone"
                        label="Telefone"
                        :default-value="props.provider?.phone ?? ''"
                        :error="errors.phone"
                        class="md:col-span-4"
                    />

                    <FormTextField
                        id="address-zip_code"
                        name="address[zip_code]"
                        label="Cep"
                        :default-value="props.address?.zip_code ?? ''"
                        :error="errors['address.zip_code']"
                        class="md:col-span-3"
                    />

                    <FormTextField
                        id="address-street"
                        name="address[street]"
                        label="Rua"
                        :default-value="props.address?.street ?? ''"
                        :error="errors['address.street']"
                        class="md:col-span-5"
                    />

                    <FormTextField
                        id="address-number"
                        name="address[number]"
                        label="Numero"
                        :default-value="props.address?.number ?? ''"
                        :error="errors['address.number']"
                        class="md:col-span-2"
                    />

                    <FormTextField
                        id="address-complement"
                        name="address[complement]"
                        label="Complemento"
                        :default-value="props.address?.complement ?? ''"
                        :error="errors['address.complement']"
                        class="md:col-span-2"
                    />

                    <FormTextField
                        id="address-district"
                        name="address[district]"
                        label="Bairro"
                        :default-value="props.address?.district ?? ''"
                        :error="errors['address.district']"
                        class="md:col-span-4"
                    />

                    <FormTextField
                        id="address-city"
                        name="address[city]"
                        label="Cidade"
                        :default-value="props.address?.city ?? ''"
                        :error="errors['address.city']"
                        class="md:col-span-4"
                    />

                    <FormTextField
                        id="address-state"
                        name="address[state]"
                        label="Estado"
                        :default-value="props.address?.state ?? ''"
                        :error="errors['address.state']"
                        class="md:col-span-2"
                    />

                    <FormTextField
                        id="address-country"
                        name="address[country]"
                        label="Pais"
                        :default-value="props.address?.country ?? 'Brasil'"
                        :error="errors['address.country']"
                        class="md:col-span-2"
                    />
                </div>

                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-border bg-muted/30 px-4 py-3 transition-colors hover:bg-muted/50 has-checked:border-primary/50 has-checked:bg-primary/5">
                    <input type="hidden" name="is_default" value="0" />
                    <input id="is_default" name="is_default" type="checkbox" value="1" :checked="props.provider?.is_default ?? true" class="accent-primary" />
                    <div>
                        <span class="text-sm font-medium">{{ t('app.tenant.providers.fields.is_default') }}</span>
                    </div>
                    <InputError :message="errors.is_default" />
                </label>

            </FormCard>
        </Form>
        </div>
    </AppLayout>
</template>

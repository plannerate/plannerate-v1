<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Truck } from 'lucide-vue-next';
import ProviderController from '@/actions/App/Http/Controllers/Tenant/ProviderController';
import AddressFields from '@/components/form/AddressFields.vue';
import FormTextField from '@/components/form/FormTextField.vue';
import FormTextareaField from '@/components/form/FormTextareaField.vue';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
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

                <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                    <FormTextField
                        id="name"
                        name="name"
                        :label="t('app.tenant.providers.fields.name')"
                        :default-value="props.provider?.name ?? ''"
                        :error="errors.name"
                        class="md:col-span-6"
                        required
                    />

                    <FormTextField
                        id="code"
                        name="code"
                        :label="t('app.tenant.providers.fields.code')"
                        :default-value="props.provider?.code ?? ''"
                        :error="errors.code"
                        class="md:col-span-3"
                    />

                    <FormTextField
                        id="cnpj"
                        name="cnpj"
                        :label="t('app.tenant.providers.fields.cnpj')"
                        :default-value="props.provider?.cnpj ?? ''"
                        :error="errors.cnpj"
                        class="md:col-span-3"
                    />

                    <FormTextField
                        id="phone"
                        name="phone"
                        :label="t('app.tenant.providers.fields.phone')"
                        :default-value="props.provider?.phone ?? ''"
                        :error="errors.phone"
                        class="md:col-span-4"
                    />

                    <FormTextField
                        id="email"
                        name="email"
                        type="email"
                        :label="t('app.tenant.providers.fields.email')"
                        :default-value="props.provider?.email ?? ''"
                        :error="errors.email"
                        class="md:col-span-4"
                    />

                    <FormTextareaField
                        id="description"
                        name="description"
                        :label="t('app.tenant.providers.fields.description')"
                        :default-value="props.provider?.description ?? ''"
                        :error="errors.description"
                        class="md:col-span-4"
                        :rows="2"
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

                <AddressFields :errors="errors" />
            </FormCard>
        </Form>
    </div>
</template>

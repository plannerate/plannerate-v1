<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import SaleController from '@/actions/App/Http/Controllers/Tenant/SaleController';
import FormDecimalField from '@/components/form/FormDecimalField.vue';
import FormSelectField from '@/components/form/FormSelectField.vue';
import FormTextareaField from '@/components/form/FormTextareaField.vue';
import FormTextField from '@/components/form/FormTextField.vue';
import FormCard from '@/components/FormCard.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

type SalePayload = {
    id: string;
    store_id: string | null;
    product_id: string | null;
    ean: string | null;
    codigo_erp: string | null;
    acquisition_cost: string | null;
    sale_price: string | null;
    total_profit_margin: string | null;
    sale_date: string | null;
    promotion: string | null;
    total_sale_quantity: string | null;
    total_sale_value: string | null;
    margem_contribuicao: string | null;
    extra_data: string | null;
};

const props = defineProps<{
    subdomain: string;
    sale: SalePayload | null;
    stores: Array<{ id: string; name: string }>;
}>();

const { t } = useT();
const isEdit = computed(() => props.sale !== null);
const salesIndexPath = SaleController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const salesCreatePath = SaleController.create.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const salesEditPath = computed(() => {
    if (! props.sale) {
        return salesCreatePath;
    }

    return SaleController.edit.url({ subdomain: props.subdomain, sale: props.sale.id }).replace(/^\/\/[^/]+/, '');
});
const salesFormPayload = computed(() => {
    if (isEdit.value && props.sale) {
        const form = SaleController.update.form({ subdomain: props.subdomain, sale: props.sale.id });

        return {
            ...form,
            action: form.action.replace(/^\/\/[^/]+/, ''),
        };
    }

    const form = SaleController.store.form(props.subdomain);

    return {
        ...form,
        action: form.action.replace(/^\/\/[^/]+/, ''),
    };
});
const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value ? t('app.tenant.sales.actions.edit') : t('app.tenant.sales.actions.new'),
    title: isEdit.value ? t('app.tenant.sales.actions.edit') : t('app.tenant.sales.actions.new'),
    description: t('app.tenant.sales.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.sales.navigation'), href: salesIndexPath },
        {
            title: isEdit.value ? t('app.tenant.sales.actions.edit') : t('app.tenant.sales.actions.new'),
            href: salesEditPath.value,
        },
    ],
});
</script>

<template>
    <Head :title="pageMeta.headTitle" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="p-4">
            <Form
                v-bind="salesFormPayload"
                v-slot="{ errors, processing }"
            >
                <FormCard
                    :processing="processing"
                    :cancel-href="salesIndexPath"
                >
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                        <FormSelectField
                            id="store_id"
                            name="store_id"
                            :label="t('app.tenant.sales.fields.store')"
                            :default-value="props.sale?.store_id ?? ''"
                            :error="errors.store_id"
                            class="md:col-span-4"
                            required
                        >
                            <option value="">{{ t('app.tenant.common.all') }}</option>
                            <option v-for="store in props.stores" :key="store.id" :value="store.id">
                                {{ store.name }}
                            </option>
                        </FormSelectField>

                        <FormTextField
                            id="ean"
                            name="ean"
                            :label="t('app.tenant.sales.fields.ean')"
                            :default-value="props.sale?.ean ?? ''"
                            :error="errors.ean"
                            class="md:col-span-4"
                        />

                        <FormTextField
                            id="codigo_erp"
                            name="codigo_erp"
                            :label="t('app.tenant.sales.fields.codigo_erp')"
                            :default-value="props.sale?.codigo_erp ?? ''"
                            :error="errors.codigo_erp"
                            class="md:col-span-4"
                            required
                        />

                        <FormTextField
                            id="sale_date"
                            name="sale_date"
                            type="date"
                            :label="t('app.tenant.sales.fields.sale_date')"
                            :default-value="props.sale?.sale_date ?? ''"
                            :error="errors.sale_date"
                            class="md:col-span-3"
                            required
                        />

                        <FormTextField
                            id="promotion"
                            name="promotion"
                            :label="t('app.tenant.sales.fields.promotion')"
                            :default-value="props.sale?.promotion ?? ''"
                            :error="errors.promotion"
                            class="md:col-span-3"
                        />

                        <FormDecimalField
                            id="acquisition_cost"
                            name="acquisition_cost"
                            :label="t('app.tenant.sales.fields.acquisition_cost')"
                            :default-value="props.sale?.acquisition_cost ?? ''"
                            :error="errors.acquisition_cost"
                            :decimals="2"
                            class="md:col-span-3"
                        />

                        <FormDecimalField
                            id="sale_price"
                            name="sale_price"
                            :label="t('app.tenant.sales.fields.sale_price')"
                            :default-value="props.sale?.sale_price ?? ''"
                            :error="errors.sale_price"
                            :decimals="2"
                            class="md:col-span-3"
                        />

                        <FormDecimalField
                            id="total_profit_margin"
                            name="total_profit_margin"
                            :label="t('app.tenant.sales.fields.total_profit_margin')"
                            :default-value="props.sale?.total_profit_margin ?? ''"
                            :error="errors.total_profit_margin"
                            :decimals="2"
                            class="md:col-span-3"
                        />

                        <FormDecimalField
                            id="total_sale_quantity"
                            name="total_sale_quantity"
                            :label="t('app.tenant.sales.fields.total_sale_quantity')"
                            :default-value="props.sale?.total_sale_quantity ?? ''"
                            :error="errors.total_sale_quantity"
                            :decimals="3"
                            class="md:col-span-3"
                        />

                        <FormDecimalField
                            id="total_sale_value"
                            name="total_sale_value"
                            :label="t('app.tenant.sales.fields.total_sale_value')"
                            :default-value="props.sale?.total_sale_value ?? ''"
                            :error="errors.total_sale_value"
                            :decimals="2"
                            class="md:col-span-3"
                        />

                        <FormDecimalField
                            id="margem_contribuicao"
                            name="margem_contribuicao"
                            :label="t('app.tenant.sales.fields.margem_contribuicao')"
                            :default-value="props.sale?.margem_contribuicao ?? ''"
                            :error="errors.margem_contribuicao"
                            :decimals="2"
                            class="md:col-span-3"
                        />

                        <FormTextareaField
                            id="extra_data"
                            name="extra_data"
                            :label="t('app.tenant.sales.fields.extra_data')"
                            :default-value="props.sale?.extra_data ?? ''"
                            :error="errors.extra_data"
                            :rows="4"
                            class="md:col-span-12"
                        />
                    </div>
                </FormCard>
            </Form>
        </div>
    </AppLayout>
</template>

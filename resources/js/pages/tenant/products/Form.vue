<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Package } from 'lucide-vue-next';
import ProductController from '@/actions/App/Http/Controllers/Tenant/ProductController';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';

type ProductPayload = {
    id: string;
    category_id: string | null;
    name: string | null;
    slug: string | null;
    ean: string | null;
    codigo_erp: string | null;
    stackable: boolean;
    perishable: boolean;
    flammable: boolean;
    hangable: boolean;
    description: string | null;
    sales_status: string | null;
    sales_purchases: string | null;
    status: 'draft' | 'published' | 'synced' | 'error';
    sync_source: string | null;
    sync_at: string | null;
    no_sales: boolean;
    no_purchases: boolean;
    url: string | null;
    type: string | null;
    reference: string | null;
    fragrance: string | null;
    flavor: string | null;
    color: string | null;
    brand: string | null;
    subbrand: string | null;
    packaging_type: string | null;
    packaging_size: string | null;
    measurement_unit: string | null;
    packaging_content: string | null;
    unit_measure: string | null;
    auxiliary_description: string | null;
    additional_information: string | null;
    sortiment_attribute: string | null;
    dimensions_ean: string | null;
    width: string | number | null;
    height: string | number | null;
    depth: string | number | null;
    weight: string | number | null;
    unit: string | null;
    dimensions_status: 'draft' | 'published';
    dimensions_description: string | null;
};

const props = defineProps<{
    subdomain: string;
    product: ProductPayload | null;
    categories: Array<{ id: string; name: string }>;
}>();

const { t } = useT();
const isEdit = computed(() => props.product !== null);
const productsIndexPath = ProductController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');

setLayoutProps({
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.products.navigation'), href: productsIndexPath },
        {
            title: isEdit.value ? t('app.tenant.products.actions.edit') : t('app.tenant.products.actions.new'),
            href: isEdit.value
                ? ProductController.edit.url({ subdomain: props.subdomain, product: props.product!.id })
                : ProductController.create.url(props.subdomain),
        },
    ],
});
</script>

<template>
    <Head :title="isEdit ? t('app.tenant.products.actions.edit') : t('app.tenant.products.actions.new')" />

    <div class="p-4">
        <Form
            v-bind="isEdit
                ? ProductController.update.form({ subdomain: props.subdomain, product: props.product!.id })
                : ProductController.store.form(props.subdomain)"
            v-slot="{ errors, processing }"
        >
            <FormCard
                :title="isEdit ? t('app.tenant.products.actions.edit') : t('app.tenant.products.actions.new')"
                :description="t('app.tenant.products.description')"
                :processing="processing"
                :cancel-href="productsIndexPath"
            >
                <template #icon>
                    <Package class="size-5" />
                </template>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="category_id">{{ t('app.tenant.products.fields.category') }}</Label>
                        <select id="category_id" name="category_id" :value="props.product?.category_id ?? ''" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">{{ t('app.tenant.common.all') }}</option>
                            <option v-for="category in props.categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                        </select>
                        <InputError :message="errors.category_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="name">{{ t('app.tenant.products.fields.name') }}</Label>
                        <Input id="name" name="name" :default-value="props.product?.name ?? ''" required />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input id="slug" name="slug" :default-value="props.product?.slug ?? ''" />
                        <InputError :message="errors.slug" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-4">
                    <div class="grid gap-2">
                        <Label for="ean">EAN</Label>
                        <Input id="ean" name="ean" :default-value="props.product?.ean ?? ''" />
                        <InputError :message="errors.ean" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="codigo_erp">{{ t('app.tenant.products.fields.codigo_erp') }}</Label>
                        <Input id="codigo_erp" name="codigo_erp" :default-value="props.product?.codigo_erp ?? ''" />
                        <InputError :message="errors.codigo_erp" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="status">{{ t('app.tenant.products.fields.status') }}</Label>
                        <select id="status" name="status" :value="props.product?.status ?? 'draft'" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="synced">Synced</option>
                            <option value="error">Error</option>
                        </select>
                        <InputError :message="errors.status" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="dimensions_status">{{ t('app.tenant.products.fields.dimensions_status') }}</Label>
                        <select id="dimensions_status" name="dimensions_status" :value="props.product?.dimensions_status ?? 'published'" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </select>
                        <InputError :message="errors.dimensions_status" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-4">
                    <div class="grid gap-2"><Label for="brand">{{ t('app.tenant.products.fields.brand') }}</Label><Input id="brand" name="brand" :default-value="props.product?.brand ?? ''" /><InputError :message="errors.brand" /></div>
                    <div class="grid gap-2"><Label for="subbrand">{{ t('app.tenant.products.fields.subbrand') }}</Label><Input id="subbrand" name="subbrand" :default-value="props.product?.subbrand ?? ''" /><InputError :message="errors.subbrand" /></div>
                    <div class="grid gap-2"><Label for="type">{{ t('app.tenant.products.fields.type') }}</Label><Input id="type" name="type" :default-value="props.product?.type ?? ''" /><InputError :message="errors.type" /></div>
                    <div class="grid gap-2"><Label for="reference">{{ t('app.tenant.products.fields.reference') }}</Label><Input id="reference" name="reference" :default-value="props.product?.reference ?? ''" /><InputError :message="errors.reference" /></div>
                </div>

                <div class="grid gap-4 md:grid-cols-4">
                    <div class="grid gap-2"><Label for="fragrance">{{ t('app.tenant.products.fields.fragrance') }}</Label><Input id="fragrance" name="fragrance" :default-value="props.product?.fragrance ?? ''" /><InputError :message="errors.fragrance" /></div>
                    <div class="grid gap-2"><Label for="flavor">{{ t('app.tenant.products.fields.flavor') }}</Label><Input id="flavor" name="flavor" :default-value="props.product?.flavor ?? ''" /><InputError :message="errors.flavor" /></div>
                    <div class="grid gap-2"><Label for="color">{{ t('app.tenant.products.fields.color') }}</Label><Input id="color" name="color" :default-value="props.product?.color ?? ''" /><InputError :message="errors.color" /></div>
                    <div class="grid gap-2"><Label for="url">URL</Label><Input id="url" name="url" :default-value="props.product?.url ?? ''" /><InputError :message="errors.url" /></div>
                </div>

                <div class="grid gap-4 md:grid-cols-4">
                    <div class="grid gap-2"><Label for="packaging_type">{{ t('app.tenant.products.fields.packaging_type') }}</Label><Input id="packaging_type" name="packaging_type" :default-value="props.product?.packaging_type ?? ''" /><InputError :message="errors.packaging_type" /></div>
                    <div class="grid gap-2"><Label for="packaging_size">{{ t('app.tenant.products.fields.packaging_size') }}</Label><Input id="packaging_size" name="packaging_size" :default-value="props.product?.packaging_size ?? ''" /><InputError :message="errors.packaging_size" /></div>
                    <div class="grid gap-2"><Label for="packaging_content">{{ t('app.tenant.products.fields.packaging_content') }}</Label><Input id="packaging_content" name="packaging_content" :default-value="props.product?.packaging_content ?? ''" /><InputError :message="errors.packaging_content" /></div>
                    <div class="grid gap-2"><Label for="measurement_unit">{{ t('app.tenant.products.fields.measurement_unit') }}</Label><Input id="measurement_unit" name="measurement_unit" :default-value="props.product?.measurement_unit ?? ''" /><InputError :message="errors.measurement_unit" /></div>
                </div>

                <div class="grid gap-4 md:grid-cols-4">
                    <div class="grid gap-2"><Label for="unit_measure">{{ t('app.tenant.products.fields.unit_measure') }}</Label><Input id="unit_measure" name="unit_measure" :default-value="props.product?.unit_measure ?? ''" /><InputError :message="errors.unit_measure" /></div>
                    <div class="grid gap-2"><Label for="dimensions_ean">{{ t('app.tenant.products.fields.dimensions_ean') }}</Label><Input id="dimensions_ean" name="dimensions_ean" :default-value="props.product?.dimensions_ean ?? ''" /><InputError :message="errors.dimensions_ean" /></div>
                    <div class="grid gap-2"><Label for="unit">{{ t('app.tenant.products.fields.unit') }}</Label><Input id="unit" name="unit" :default-value="props.product?.unit ?? 'cm'" /><InputError :message="errors.unit" /></div>
                    <div class="grid gap-2"><Label for="sync_source">{{ t('app.tenant.products.fields.sync_source') }}</Label><Input id="sync_source" name="sync_source" :default-value="props.product?.sync_source ?? ''" /><InputError :message="errors.sync_source" /></div>
                </div>

                <div class="grid gap-4 md:grid-cols-4">
                    <div class="grid gap-2"><Label for="width">{{ t('app.tenant.products.fields.width') }}</Label><Input id="width" name="width" type="number" step="0.01" min="0" :default-value="props.product?.width ?? ''" /><InputError :message="errors.width" /></div>
                    <div class="grid gap-2"><Label for="height">{{ t('app.tenant.products.fields.height') }}</Label><Input id="height" name="height" type="number" step="0.01" min="0" :default-value="props.product?.height ?? ''" /><InputError :message="errors.height" /></div>
                    <div class="grid gap-2"><Label for="depth">{{ t('app.tenant.products.fields.depth') }}</Label><Input id="depth" name="depth" type="number" step="0.01" min="0" :default-value="props.product?.depth ?? ''" /><InputError :message="errors.depth" /></div>
                    <div class="grid gap-2"><Label for="weight">{{ t('app.tenant.products.fields.weight') }}</Label><Input id="weight" name="weight" type="number" step="0.01" min="0" :default-value="props.product?.weight ?? ''" /><InputError :message="errors.weight" /></div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="grid gap-2"><Label for="sales_status">{{ t('app.tenant.products.fields.sales_status') }}</Label><Input id="sales_status" name="sales_status" :default-value="props.product?.sales_status ?? ''" /><InputError :message="errors.sales_status" /></div>
                    <div class="grid gap-2"><Label for="sales_purchases">{{ t('app.tenant.products.fields.sales_purchases') }}</Label><Input id="sales_purchases" name="sales_purchases" :default-value="props.product?.sales_purchases ?? ''" /><InputError :message="errors.sales_purchases" /></div>
                    <div class="grid gap-2"><Label for="sync_at">{{ t('app.tenant.products.fields.sync_at') }}</Label><Input id="sync_at" name="sync_at" type="datetime-local" :default-value="props.product?.sync_at ?? ''" /><InputError :message="errors.sync_at" /></div>
                </div>

                <div class="grid gap-2">
                    <Label for="auxiliary_description">{{ t('app.tenant.products.fields.auxiliary_description') }}</Label>
                    <Input id="auxiliary_description" name="auxiliary_description" :default-value="props.product?.auxiliary_description ?? ''" />
                    <InputError :message="errors.auxiliary_description" />
                </div>
                <div class="grid gap-2">
                    <Label for="additional_information">{{ t('app.tenant.products.fields.additional_information') }}</Label>
                    <Input id="additional_information" name="additional_information" :default-value="props.product?.additional_information ?? ''" />
                    <InputError :message="errors.additional_information" />
                </div>
                <div class="grid gap-2">
                    <Label for="sortiment_attribute">{{ t('app.tenant.products.fields.sortiment_attribute') }}</Label>
                    <Input id="sortiment_attribute" name="sortiment_attribute" :default-value="props.product?.sortiment_attribute ?? ''" />
                    <InputError :message="errors.sortiment_attribute" />
                </div>
                <div class="grid gap-2">
                    <Label for="description">{{ t('app.tenant.products.fields.description') }}</Label>
                    <textarea id="description" name="description" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20">{{ props.product?.description ?? '' }}</textarea>
                    <InputError :message="errors.description" />
                </div>
                <div class="grid gap-2">
                    <Label for="dimensions_description">{{ t('app.tenant.products.fields.dimensions_description') }}</Label>
                    <Input id="dimensions_description" name="dimensions_description" :default-value="props.product?.dimensions_description ?? ''" />
                    <InputError :message="errors.dimensions_description" />
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="stackable" value="0" />
                        <input type="checkbox" name="stackable" value="1" :checked="props.product?.stackable ?? false" class="accent-primary" />
                        {{ t('app.tenant.products.fields.stackable') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="perishable" value="0" />
                        <input type="checkbox" name="perishable" value="1" :checked="props.product?.perishable ?? false" class="accent-primary" />
                        {{ t('app.tenant.products.fields.perishable') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="flammable" value="0" />
                        <input type="checkbox" name="flammable" value="1" :checked="props.product?.flammable ?? false" class="accent-primary" />
                        {{ t('app.tenant.products.fields.flammable') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="hangable" value="0" />
                        <input type="checkbox" name="hangable" value="1" :checked="props.product?.hangable ?? false" class="accent-primary" />
                        {{ t('app.tenant.products.fields.hangable') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="no_sales" value="0" />
                        <input type="checkbox" name="no_sales" value="1" :checked="props.product?.no_sales ?? false" class="accent-primary" />
                        {{ t('app.tenant.products.fields.no_sales') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="no_purchases" value="0" />
                        <input type="checkbox" name="no_purchases" value="1" :checked="props.product?.no_purchases ?? false" class="accent-primary" />
                        {{ t('app.tenant.products.fields.no_purchases') }}
                    </label>
                </div>
            </FormCard>
        </Form>
    </div>
</template>

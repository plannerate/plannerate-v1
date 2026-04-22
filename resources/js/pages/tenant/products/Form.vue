<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Package } from 'lucide-vue-next';
import ProductController from '@/actions/App/Http/Controllers/Tenant/ProductController';
import CategoryCascadeSelect from '@/components/tenant/CategoryCascadeSelect.vue';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';

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
}>();

const { t } = useT();
const isEdit = computed(() => props.product !== null);
const productsIndexPath = tenantWayfinderPath(ProductController.index.url(props.subdomain));

const storeFormAttrs = computed(() => {
    const def = ProductController.store.form(props.subdomain);

    return { ...def, action: tenantWayfinderPath(def.action) };
});

const updateFormAttrs = computed(() => {
    const def = ProductController.update.form({ subdomain: props.subdomain, product: props.product!.id });

    return { ...def, action: tenantWayfinderPath(def.action) };
});

const textareaClass =
    'flex min-h-[80px] w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50';

const selectClass =
    'flex h-9 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20';

setLayoutProps({
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.products.navigation'), href: productsIndexPath },
        {
            title: isEdit.value ? t('app.tenant.products.actions.edit') : t('app.tenant.products.actions.new'),
            href: isEdit.value
                ? tenantWayfinderPath(
                    ProductController.edit.url({ subdomain: props.subdomain, product: props.product!.id }),
                )
                : tenantWayfinderPath(ProductController.create.url(props.subdomain)),
        },
    ],
});
</script>

<template>

    <Head :title="isEdit ? t('app.tenant.products.actions.edit') : t('app.tenant.products.actions.new')" />

    <div class="p-4">
        <Form v-bind="isEdit ? updateFormAttrs : storeFormAttrs" v-slot="{ errors, processing }">
            <FormCard :title="isEdit ? t('app.tenant.products.actions.edit') : t('app.tenant.products.actions.new')"
                :description="t('app.tenant.products.description')" :processing="processing"
                :cancel-href="productsIndexPath">
                <template #icon>
                    <Package class="size-5" />
                </template>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:items-start">
                    <!-- Coluna estreita: midia + classificacao rapida -->


                    <!-- Coluna principal: identificacao, dimensoes fisicas, dados extensos -->
                    <div class="flex min-w-0 flex-col gap-6 lg:col-span-8 xl:col-span-9">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                            <div class="flex flex-col gap-y-1 md:col-span-8">
                                <Label for="name">{{ t('app.tenant.products.fields.name') }} <span
                                        class="text-destructive">*</span></Label>
                                <Input id="name" name="name" type="text" required
                                    :placeholder="t('app.tenant.products.fields.name')"
                                    :default-value="props.product?.name ?? ''" />
                                <InputError :message="errors.name" />
                            </div>
                            <div class="flex flex-col gap-y-1 md:col-span-4">
                                <Label for="ean">{{ t('app.tenant.products.form.ean') }}</Label>
                                <Input id="ean" name="ean" type="text" :placeholder="t('app.tenant.products.form.ean')"
                                    :default-value="props.product?.ean ?? ''" />
                                <InputError :message="errors.ean" />
                            </div>
                        </div>
                        <fieldset class="rounded-lg border border-border/80 p-3">
                                <legend class="px-1 text-xs font-semibold text-foreground">
                                    {{ t('app.tenant.products.form.sections.category') }}
                                </legend>
                                <div class="mt-2">
                                    <CategoryCascadeSelect
                                        :model-value="props.product?.category_id ?? null"
                                        :error="errors.category_id"
                                    />
                                </div>
                            </fieldset>
                        <!-- Dimensoes -->
                        <div class="space-y-4">
                            <div>
                                <h4 class="text-sm font-semibold">{{ t('app.tenant.products.form.sections.dimensions')
                                    }}</h4>
                                <p class="text-sm text-muted-foreground">{{
                                    t('app.tenant.products.form.sections.dimensions_lead') }}
                                </p>
                            </div>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                                <div class="flex flex-col gap-y-1 md:col-span-2">
                                    <Label for="width">{{ t('app.tenant.products.fields.width') }} (cm)</Label>
                                    <Input id="width" name="width" type="number" step="0.01" min="0"
                                        :default-value="props.product?.width ?? ''" />
                                    <p class="text-sm text-muted-foreground">{{
                                        t('app.tenant.products.form.hints.width') }}</p>
                                    <InputError :message="errors.width" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-2">
                                    <Label for="height">{{ t('app.tenant.products.fields.height') }} (cm)</Label>
                                    <Input id="height" name="height" type="number" step="0.01" min="0"
                                        :default-value="props.product?.height ?? ''" />
                                    <p class="text-sm text-muted-foreground">{{
                                        t('app.tenant.products.form.hints.height') }}</p>
                                    <InputError :message="errors.height" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-2">
                                    <Label for="depth">{{ t('app.tenant.products.fields.depth') }} (cm)</Label>
                                    <Input id="depth" name="depth" type="number" step="0.01" min="0"
                                        :default-value="props.product?.depth ?? ''" />
                                    <p class="text-sm text-muted-foreground">{{
                                        t('app.tenant.products.form.hints.depth') }}</p>
                                    <InputError :message="errors.depth" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-2">
                                    <Label for="weight">{{ t('app.tenant.products.fields.weight') }}</Label>
                                    <Input id="weight" name="weight" type="number" step="0.01" min="0"
                                        :default-value="props.product?.weight ?? ''" />
                                    <p class="text-sm text-muted-foreground">{{
                                        t('app.tenant.products.form.hints.weight') }}</p>
                                    <InputError :message="errors.weight" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-4">
                                    <Label for="unit">{{ t('app.tenant.products.fields.unit') }} <span
                                            class="text-destructive">*</span></Label>
                                    <Input id="unit" name="unit" type="text"
                                        :default-value="props.product?.unit ?? 'cm'" />
                                    <InputError :message="errors.unit" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-12">
                                    <Label for="dimensions_description">{{
                                        t('app.tenant.products.fields.dimensions_description')
                                        }}</Label>
                                    <Input id="dimensions_description" name="dimensions_description"
                                        :default-value="props.product?.dimensions_description ?? ''" />
                                    <InputError :message="errors.dimensions_description" />
                                </div>
                            </div>
                        </div>

                        <!-- Dados adicionais (ordem do stitch) -->
                        <div class="space-y-4">
                            <div>
                                <h4 class="text-sm font-semibold">{{ t('app.tenant.products.form.sections.additional')
                                    }}</h4>
                                <p class="text-sm text-muted-foreground">{{
                                    t('app.tenant.products.form.sections.additional_lead') }}
                                </p>
                            </div>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                                <div class="flex flex-col gap-y-1 md:col-span-3">
                                    <Label for="type">{{ t('app.tenant.products.fields.type') }}</Label>
                                    <Input id="type" name="type" :default-value="props.product?.type ?? ''" />
                                    <InputError :message="errors.type" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-3">
                                    <Label for="reference">{{ t('app.tenant.products.fields.reference') }}</Label>
                                    <Input id="reference" name="reference"
                                        :default-value="props.product?.reference ?? ''" />
                                    <InputError :message="errors.reference" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-3">
                                    <Label for="codigo_erp">{{ t('app.tenant.products.fields.codigo_erp') }}</Label>
                                    <Input id="codigo_erp" name="codigo_erp"
                                        :default-value="props.product?.codigo_erp ?? ''" />
                                    <InputError :message="errors.codigo_erp" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-3">
                                    <Label for="brand">{{ t('app.tenant.products.fields.brand') }}</Label>
                                    <Input id="brand" name="brand" :default-value="props.product?.brand ?? ''" />
                                    <InputError :message="errors.brand" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-3">
                                    <Label for="subbrand">{{ t('app.tenant.products.fields.subbrand') }}</Label>
                                    <Input id="subbrand" name="subbrand"
                                        :default-value="props.product?.subbrand ?? ''" />
                                    <InputError :message="errors.subbrand" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-3">
                                    <Label for="color">{{ t('app.tenant.products.fields.color') }}</Label>
                                    <Input id="color" name="color" :default-value="props.product?.color ?? ''" />
                                    <InputError :message="errors.color" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-3">
                                    <Label for="fragrance">{{ t('app.tenant.products.fields.fragrance') }}</Label>
                                    <Input id="fragrance" name="fragrance"
                                        :default-value="props.product?.fragrance ?? ''" />
                                    <InputError :message="errors.fragrance" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-3">
                                    <Label for="flavor">{{ t('app.tenant.products.fields.flavor') }}</Label>
                                    <Input id="flavor" name="flavor" :default-value="props.product?.flavor ?? ''" />
                                    <InputError :message="errors.flavor" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-3">
                                    <Label for="packaging_type">{{ t('app.tenant.products.fields.packaging_type')
                                        }}</Label>
                                    <Input id="packaging_type" name="packaging_type"
                                        :default-value="props.product?.packaging_type ?? ''" />
                                    <InputError :message="errors.packaging_type" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-3">
                                    <Label for="packaging_size">{{ t('app.tenant.products.fields.packaging_size')
                                        }}</Label>
                                    <Input id="packaging_size" name="packaging_size"
                                        :default-value="props.product?.packaging_size ?? ''" />
                                    <InputError :message="errors.packaging_size" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-3">
                                    <Label for="packaging_content">{{ t('app.tenant.products.fields.packaging_content')
                                        }}</Label>
                                    <Input id="packaging_content" name="packaging_content"
                                        :default-value="props.product?.packaging_content ?? ''" />
                                    <InputError :message="errors.packaging_content" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-3">
                                    <Label for="measurement_unit">{{ t('app.tenant.products.fields.measurement_unit')
                                        }}</Label>
                                    <Input id="measurement_unit" name="measurement_unit"
                                        :default-value="props.product?.measurement_unit ?? ''" />
                                    <InputError :message="errors.measurement_unit" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-3">
                                    <Label for="unit_measure">{{ t('app.tenant.products.fields.unit_measure') }}</Label>
                                    <Input id="unit_measure" name="unit_measure"
                                        :default-value="props.product?.unit_measure ?? ''" />
                                    <InputError :message="errors.unit_measure" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-4">
                                    <Label for="sortiment_attribute">{{
                                        t('app.tenant.products.fields.sortiment_attribute') }}</Label>
                                    <Input id="sortiment_attribute" name="sortiment_attribute"
                                        :default-value="props.product?.sortiment_attribute ?? ''" />
                                    <InputError :message="errors.sortiment_attribute" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-6">
                                    <Label for="auxiliary_description">{{
                                        t('app.tenant.products.fields.auxiliary_description')
                                        }}</Label>
                                    <textarea id="auxiliary_description" name="auxiliary_description" rows="3"
                                        :class="textareaClass"
                                        :placeholder="t('app.tenant.products.fields.auxiliary_description')">{{
                                            props.product?.auxiliary_description ?? '' }}</textarea>
                                    <InputError :message="errors.auxiliary_description" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-6">
                                    <Label for="additional_information">{{
                                        t('app.tenant.products.fields.additional_information')
                                        }}</Label>
                                    <textarea id="additional_information" name="additional_information" rows="3"
                                        :class="textareaClass"
                                        :placeholder="t('app.tenant.products.fields.additional_information')">{{
                                            props.product?.additional_information ?? '' }}</textarea>
                                    <InputError :message="errors.additional_information" />
                                </div>
                            </div>
                        </div>

                        <!-- Slug, descricao, sincronizacao -->
                        <details class="rounded-lg border border-border p-4">
                            <summary class="cursor-pointer text-sm font-semibold text-foreground">
                                {{ t('app.tenant.products.form.sections.advanced') }}
                            </summary>
                            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-12">
                                <div class="flex flex-col gap-y-1 md:col-span-6">
                                    <Label for="slug">{{ t('app.tenant.products.form.slug') }}</Label>
                                    <Input id="slug" name="slug" :default-value="props.product?.slug ?? ''" />
                                    <InputError :message="errors.slug" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-6">
                                    <Label for="sales_status">{{ t('app.tenant.products.fields.sales_status') }}</Label>
                                    <Input id="sales_status" name="sales_status"
                                        :default-value="props.product?.sales_status ?? ''" />
                                    <InputError :message="errors.sales_status" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-6">
                                    <Label for="sales_purchases">{{ t('app.tenant.products.fields.sales_purchases')
                                        }}</Label>
                                    <Input id="sales_purchases" name="sales_purchases"
                                        :default-value="props.product?.sales_purchases ?? ''" />
                                    <InputError :message="errors.sales_purchases" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-6">
                                    <Label for="sync_source">{{ t('app.tenant.products.fields.sync_source') }}</Label>
                                    <Input id="sync_source" name="sync_source"
                                        :default-value="props.product?.sync_source ?? ''" />
                                    <InputError :message="errors.sync_source" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-6">
                                    <Label for="sync_at">{{ t('app.tenant.products.fields.sync_at') }}</Label>
                                    <Input id="sync_at" name="sync_at" type="datetime-local"
                                        :default-value="props.product?.sync_at ?? ''" />
                                    <InputError :message="errors.sync_at" />
                                </div>
                                <div class="flex flex-col gap-y-1 md:col-span-12">
                                    <Label for="description">{{ t('app.tenant.products.fields.description') }}</Label>
                                    <textarea id="description" name="description" rows="4" :class="textareaClass">{{
                                        props.product?.description ?? '' }}</textarea>
                                    <InputError :message="errors.description" />
                                </div>
                            </div>
                        </details>

                        <!-- Logistica -->
                        <div>
                            <p class="mb-3 text-sm font-semibold">{{ t('app.tenant.products.form.logistics') }}</p>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="hidden" name="stackable" value="0" />
                                    <input type="checkbox" name="stackable" value="1"
                                        :checked="props.product?.stackable ?? false" class="accent-primary" />
                                    {{ t('app.tenant.products.fields.stackable') }}
                                </label>
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="hidden" name="perishable" value="0" />
                                    <input type="checkbox" name="perishable" value="1"
                                        :checked="props.product?.perishable ?? false" class="accent-primary" />
                                    {{ t('app.tenant.products.fields.perishable') }}
                                </label>
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="hidden" name="flammable" value="0" />
                                    <input type="checkbox" name="flammable" value="1"
                                        :checked="props.product?.flammable ?? false" class="accent-primary" />
                                    {{ t('app.tenant.products.fields.flammable') }}
                                </label>
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="hidden" name="hangable" value="0" />
                                    <input type="checkbox" name="hangable" value="1"
                                        :checked="props.product?.hangable ?? false" class="accent-primary" />
                                    {{ t('app.tenant.products.fields.hangable') }}
                                </label>
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="hidden" name="no_sales" value="0" />
                                    <input type="checkbox" name="no_sales" value="1"
                                        :checked="props.product?.no_sales ?? false" class="accent-primary" />
                                    {{ t('app.tenant.products.fields.no_sales') }}
                                </label>
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="hidden" name="no_purchases" value="0" />
                                    <input type="checkbox" name="no_purchases" value="1"
                                        :checked="props.product?.no_purchases ?? false" class="accent-primary" />
                                    {{ t('app.tenant.products.fields.no_purchases') }}
                                </label>
                            </div>
                        </div>
                    </div>

                    <aside class="flex flex-col gap-5 lg:sticky lg:top-4 lg:col-span-4 lg:self-start xl:col-span-3">
                        <div class="space-y-5 rounded-xl border border-border bg-muted/5 p-4 dark:bg-muted/10">
                            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                {{ t('app.tenant.products.form.sidebar_title') }}
                            </p>

                            <div class="flex flex-col gap-y-2">
                                <Label for="url">{{ t('app.tenant.products.form.sections.image') }}</Label>
                                <p class="text-xs text-muted-foreground">{{
                                    t('app.tenant.products.form.sections.image_help') }}</p>
                                <div
                                    class="relative rounded-lg border-2 border-dashed border-border px-3 py-8 text-center transition-colors hover:border-primary/50">
                                    <p class="text-xs text-muted-foreground">{{
                                        t('app.tenant.products.form.sections.image_drop') }}</p>
                                </div>
                                <Input id="url" name="url" type="url" :placeholder="'https://…'"
                                    :default-value="props.product?.url ?? ''" />
                                <InputError :message="errors.url" />
                            </div>

                          

                            <div class="flex flex-col gap-y-1">
                                <Label for="status">{{ t('app.tenant.products.form.status_product') }} <span
                                        class="text-destructive">*</span></Label>
                                <select id="status" name="status" :value="props.product?.status ?? 'draft'"
                                    :class="selectClass">
                                    <option value="draft">{{ t('app.tenant.products.status_options.draft') }}</option>
                                    <option value="published">{{ t('app.tenant.products.status_options.published') }}
                                    </option>
                                    <option value="synced">{{ t('app.tenant.products.status_options.synced') }}</option>
                                    <option value="error">{{ t('app.tenant.products.status_options.error') }}</option>
                                </select>
                                <InputError :message="errors.status" />
                            </div>

                            <div class="space-y-3 border-t border-border pt-4">
                                <p class="text-xs font-semibold text-muted-foreground">{{
                                    t('app.tenant.products.form.sidebar_dimensions') }}</p>
                                <div class="flex flex-col gap-y-1">
                                    <Label for="dimensions_status">{{ t('app.tenant.products.fields.dimensions_status')
                                        }}</Label>
                                    <select id="dimensions_status" name="dimensions_status"
                                        :value="props.product?.dimensions_status ?? 'draft'" :class="selectClass">
                                        <option value="draft">{{
                                            t('app.tenant.products.dimensions_status_options.draft') }}</option>
                                        <option value="published">{{
                                            t('app.tenant.products.dimensions_status_options.published') }}
                                        </option>
                                    </select>
                                    <InputError :message="errors.dimensions_status" />
                                </div>
                                <div class="flex flex-col gap-y-1">
                                    <Label for="dimensions_ean">{{ t('app.tenant.products.fields.dimensions_ean')
                                        }}</Label>
                                    <Input id="dimensions_ean" name="dimensions_ean"
                                        :default-value="props.product?.dimensions_ean ?? ''" />
                                    <InputError :message="errors.dimensions_ean" />
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </FormCard>
        </Form>
    </div>
</template>

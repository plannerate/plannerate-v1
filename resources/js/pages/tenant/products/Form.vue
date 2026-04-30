<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ProductController from '@/actions/App/Http/Controllers/Tenant/ProductController';
import FormDecimalField from '@/components/form/FormDecimalField.vue';
import FormSelectField from '@/components/form/FormSelectField.vue';
import FormTabsBar from '@/components/form/FormTabsBar.vue';
import FormTextareaField from '@/components/form/FormTextareaField.vue';
import FormTextField from '@/components/form/FormTextField.vue';
import ProductIdentitySyncFieldset from '@/components/form/ProductIdentitySyncFieldset.vue';
import FormCard from '@/components/FormCard.vue';
import ImageUploadField from '@/components/ImageUploadField.vue';
import InputError from '@/components/InputError.vue';
import CategoryCascadeSelect from '@/components/tenant/CategoryCascadeSelect.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
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
    image_url: string | null;
    store_ids?: string[];
};

type TabKey = 'identification' | 'market' | 'dimensions' | 'additional';

const props = defineProps<{
    subdomain: string;
    product: ProductPayload | null;
    stores: Array<{ id: string; name: string; document: string | null }>;
}>();

const { t } = useT();
const page = usePage<{ errors: Record<string, string> }>();
const isEdit = computed(() => props.product !== null);
const productsIndexPath = tenantWayfinderPath(
    ProductController.index.url(props.subdomain),
);

const storeFormAttrs = computed(() => {
    const def = ProductController.store.form(props.subdomain);

    return { ...def, action: tenantWayfinderPath(def.action) };
});

const updateFormAttrs = computed(() => {
    const def = ProductController.update.form({
        subdomain: props.subdomain,
        product: props.product!.id,
    });

    return { ...def, action: tenantWayfinderPath(def.action) };
});

const tabsOrder: TabKey[] = [
    'identification',
    'market',
    'dimensions',
    'additional',
];
const activeTab = ref<TabKey>('identification');
const imageError = ref('');
const localErrors = ref<Record<string, string>>({});

const productName = ref(props.product?.name ?? '');
const ean = ref(props.product?.ean ?? '');
const codigoErp = ref(props.product?.codigo_erp ?? '');
const productStatus = ref(props.product?.status ?? 'draft');
const dimensionsStatus = ref(props.product?.dimensions_status ?? 'draft');
const width = ref(toInputValue(props.product?.width));
const height = ref(toInputValue(props.product?.height));
const depth = ref(toInputValue(props.product?.depth));
const weight = ref(toInputValue(props.product?.weight));
const selectedStoreIds = ref<string[]>(props.product?.store_ids ?? []);

function toInputValue(value: string | number | null | undefined): string {
    return value === null || value === undefined ? '' : String(value);
}

const tabs = computed(() => [
    {
        key: 'identification' as const,
        label: t('app.tenant.products.form.tabs.identification'),
    },
    {
        key: 'market' as const,
        label: t('app.tenant.products.form.tabs.market'),
    },
    {
        key: 'dimensions' as const,
        label: t('app.tenant.products.form.tabs.dimensions'),
    },
    {
        key: 'additional' as const,
        label: t('app.tenant.products.form.tabs.additional'),
    },
]);

const fieldTabMap: Record<string, TabKey> = {
    name: 'identification',
    ean: 'identification',
    status: 'identification',
    url: 'identification',
    category_id: 'market',

    type: 'market',
    reference: 'market',
    codigo_erp: 'market',
    brand: 'market',
    subbrand: 'market',
    color: 'market',
    fragrance: 'market',
    flavor: 'market',
    packaging_type: 'market',
    packaging_size: 'market',
    packaging_content: 'market',
    measurement_unit: 'market',
    unit_measure: 'market',
    sortiment_attribute: 'additional',
    stackable: 'additional',
    perishable: 'additional',
    flammable: 'additional',
    hangable: 'additional',

    width: 'dimensions',
    height: 'dimensions',
    depth: 'dimensions',
    weight: 'dimensions',
    unit: 'dimensions',
    dimensions_status: 'dimensions',
    dimensions_description: 'dimensions',

    auxiliary_description: 'additional',
    additional_information: 'additional',
    description: 'additional',
};

watch(
    () => page.props.errors,
    (errors) => {
        const keys = Object.keys(errors ?? {});

        if (keys.length === 0) {
            return;
        }

        for (const tab of tabsOrder) {
            if (keys.some((field) => fieldTabMap[field] === tab)) {
                activeTab.value = tab;
                break;
            }
        }
    },
    { deep: true },
);

function setTab(targetTab: TabKey): void {
    if (targetTab === activeTab.value) {
        return;
    }

    if (!validateCurrentTab()) {
        return;
    }

    activeTab.value = targetTab;
}

function onSubmit(event: Event): void {
    if (!validateCurrentTab()) {
        event.preventDefault();
    }
}

function validateCurrentTab(): boolean {
    imageError.value = '';

    if (activeTab.value === 'identification') {
        delete localErrors.value.name;
        delete localErrors.value.status;

        if (productName.value.trim() === '') {
            localErrors.value.name = t(
                'app.tenant.products.form.tabs_validation.name_required',
            );
        }

        if (productStatus.value.trim() === '') {
            localErrors.value.status = t(
                'app.tenant.products.form.tabs_validation.status_required',
            );
        }

        return !localErrors.value.name && !localErrors.value.status;
    }

    if (activeTab.value === 'dimensions') {
        delete localErrors.value.dimensions_status;
        delete localErrors.value.width;
        delete localErrors.value.height;
        delete localErrors.value.depth;
        delete localErrors.value.weight;

        if (dimensionsStatus.value.trim() === '') {
            localErrors.value.dimensions_status = t(
                'app.tenant.products.form.tabs_validation.dimensions_status_required',
            );
        }

        validateNumberField('width', width.value);
        validateNumberField('height', height.value);
        validateNumberField('depth', depth.value);
        validateNumberField('weight', weight.value);

        return (
            !localErrors.value.dimensions_status &&
            !localErrors.value.width &&
            !localErrors.value.height &&
            !localErrors.value.depth &&
            !localErrors.value.weight
        );
    }

    return true;
}

function validateNumberField(
    field: 'width' | 'height' | 'depth' | 'weight',
    value: string,
): void {
    if (value === '') {
        return;
    }

    const parsed = Number(value);

    if (!Number.isFinite(parsed) || parsed < 0) {
        localErrors.value[field] = t(
            'app.tenant.products.form.tabs_validation.non_negative',
            {
                field: t(`app.tenant.products.fields.${field}`),
            },
        );
    }
}

function resolveError(field: string, errors: Record<string, string>): string {
    return localErrors.value[field] ?? errors[field] ?? '';
}

function onImageUploaded(): void {
    imageError.value = '';
}

function onImageProcessed(): void {
    imageError.value = '';
}

function onImageError(message: string): void {
    imageError.value = message;
}

const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value
        ? t('app.tenant.products.actions.edit')
        : t('app.tenant.products.actions.new'),
    title: isEdit.value
        ? t('app.tenant.products.actions.edit')
        : t('app.tenant.products.actions.new'),
    description: t('app.tenant.products.description'),
    breadcrumbs: [
        {
            title: t('app.navigation.dashboard'),
            href: dashboard.url().replace(/^\/\/[^/]+/, ''),
        },
        { title: t('app.tenant.products.navigation'), href: productsIndexPath },
        {
            title: isEdit.value
                ? t('app.tenant.products.actions.edit')
                : t('app.tenant.products.actions.new'),
            href: isEdit.value
                ? tenantWayfinderPath(
                      ProductController.edit.url({
                          subdomain: props.subdomain,
                          product: props.product!.id,
                      }),
                  )
                : tenantWayfinderPath(
                      ProductController.create.url(props.subdomain),
                  ),
        },
    ],
});
</script>

<template>
    <Head :title="pageMeta.headTitle" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="px-6 py-6">
        <Form
            v-bind="isEdit ? updateFormAttrs : storeFormAttrs"
            v-slot="{ errors, processing }"
            @submit="onSubmit"
        >
            <FormCard
                :processing="processing"
                :cancel-href="productsIndexPath"
            >

                <FormTabsBar v-model="activeTab" :tabs="tabs" @update:model-value="setTab($event as TabKey)" />

                <div
                    v-show="activeTab === 'identification'"
                    class="grid grid-cols-1 gap-6 lg:grid-cols-12"
                >
                    <div class="space-y-4 lg:col-span-8">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                            <FormTextField
                                id="name"
                                v-model="productName"
                                name="name"
                                :label="t('app.tenant.products.fields.name')"
                                :required="true"
                                :placeholder="t('app.tenant.products.fields.name')"
                                :error="resolveError('name', errors)"
                                class="md:col-span-12"
                            />
                        </div>

                        <ProductIdentitySyncFieldset
                            :subdomain="props.subdomain"
                            v-model:ean="ean"
                            v-model:codigo-erp="codigoErp"
                            :store-ids="selectedStoreIds"
                            :ean-label="t('app.tenant.products.form.ean')"
                            :codigo-erp-label="t('app.tenant.products.fields.codigo_erp')"
                            :ean-error="resolveError('ean', errors)"
                            :codigo-erp-error="resolveError('codigo_erp', errors)"
                        />

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                            <FormTextareaField
                                id="auxiliary_description"
                                name="auxiliary_description"
                                :label="t('app.tenant.products.fields.auxiliary_description')"
                                :placeholder="t('app.tenant.products.fields.auxiliary_description')"
                                :default-value="props.product?.auxiliary_description ?? ''"
                                :error="resolveError('auxiliary_description', errors)"
                                class="md:col-span-6"
                            />
                            <FormTextareaField
                                id="additional_information"
                                name="additional_information"
                                :label="t('app.tenant.products.fields.additional_information')"
                                :placeholder="t('app.tenant.products.fields.additional_information')"
                                :default-value="props.product?.additional_information ?? ''"
                                :error="resolveError('additional_information', errors)"
                                class="md:col-span-6"
                            />
                            <FormTextareaField
                                id="description"
                                name="description"
                                :label="t('app.tenant.products.fields.description')"
                                :default-value="props.product?.description ?? ''"
                                :rows="4"
                                :error="resolveError('description', errors)"
                                class="md:col-span-12"
                            />
                            <FormSelectField
                                id="status"
                                v-model="productStatus"
                                name="status"
                                :label="t('app.tenant.products.form.status_product')"
                                :required="true"
                                :error="resolveError('status', errors)"
                                class="md:col-span-12"
                            >
                                <option value="draft">
                                    {{ t('app.tenant.products.status_options.draft') }}
                                </option>
                                <option value="published">
                                    {{ t('app.tenant.products.status_options.published') }}
                                </option>
                                <option value="synced">
                                    {{ t('app.tenant.products.status_options.synced') }}
                                </option>
                                <option value="error">
                                    {{ t('app.tenant.products.status_options.error') }}
                                </option>
                            </FormSelectField>
                        </div>

                        <div
                            class="rounded-lg border border-border/80 p-3"
                        >
                            <p class="mb-3 text-sm font-semibold">
                                Lojas do produto
                            </p>
                            <div
                                v-if="props.stores.length > 0"
                                class="grid grid-cols-1 gap-2 md:grid-cols-2"
                            >
                                <label
                                    v-for="store in props.stores"
                                    :key="store.id"
                                    class="flex items-start gap-2 rounded-md border border-border/60 px-2 py-2 text-sm"
                                >
                                    <input
                                        v-model="selectedStoreIds"
                                        type="checkbox"
                                        name="store_ids[]"
                                        :value="store.id"
                                        class="mt-0.5 accent-primary"
                                    />
                                    <span>
                                        {{ store.name }}{{ store.document ? ` - ${store.document}` : '' }}
                                    </span>
                                </label>
                            </div>
                            <p v-else class="text-xs text-muted-foreground">
                                Nenhuma loja disponível para associação.
                            </p>
                        </div>
                    </div>

                    <div class="space-y-4 lg:col-span-4">
                        <ImageUploadField
                            :subdomain="subdomain"
                            name="url"
                            :label="
                                t('app.tenant.products.form.sections.image')
                            "
                            :ean="ean"
                            :initial-url="props.product?.image_url ?? ''"
                            :initial-path="props.product?.url ?? ''"
                            :ai-enabled="true"
                            @uploaded="onImageUploaded"
                            @ai-processed="onImageProcessed"
                            @repository-processed="onImageProcessed"
                            @error="onImageError"
                        />
                        <InputError
                            :message="imageError || resolveError('url', errors)"
                        />
                    </div>
                </div>

                <div
                    v-show="activeTab === 'market'"
                    class="grid grid-cols-1 gap-4 md:grid-cols-12"
                >
                    <fieldset
                        class="rounded-lg border border-border/80 p-3 md:col-span-12"
                    >
                        <legend
                            class="px-1 text-xs font-semibold text-foreground"
                        >
                            {{
                                t('app.tenant.products.form.sections.category')
                            }}
                        </legend>
                        <div class="mt-2">
                            <CategoryCascadeSelect
                                :model-value="
                                    props.product?.category_id ?? null
                                "
                                :error="resolveError('category_id', errors)"
                            />
                        </div>
                    </fieldset>
                </div>

                <div
                    v-show="activeTab === 'dimensions'"
                    class="grid grid-cols-1 gap-4 md:grid-cols-12"
                >
                    <FormDecimalField
                        id="width"
                        v-model="width"
                        name="width"
                        :label="`${t('app.tenant.products.fields.width')} (cm)`"
                        :hint="t('app.tenant.products.form.hints.width')"
                        :error="resolveError('width', errors)"
                        :decimals="3"
                        class="md:col-span-2"
                    />
                    <FormDecimalField
                        id="height"
                        v-model="height"
                        name="height"
                        :label="`${t('app.tenant.products.fields.height')} (cm)`"
                        :hint="t('app.tenant.products.form.hints.height')"
                        :error="resolveError('height', errors)"
                        :decimals="3"
                        class="md:col-span-2"
                    />
                    <FormDecimalField
                        id="depth"
                        v-model="depth"
                        name="depth"
                        :label="`${t('app.tenant.products.fields.depth')} (cm)`"
                        :hint="t('app.tenant.products.form.hints.depth')"
                        :error="resolveError('depth', errors)"
                        :decimals="3"
                        class="md:col-span-2"
                    />
                    <FormDecimalField
                        id="weight"
                        v-model="weight"
                        name="weight"
                        :label="t('app.tenant.products.fields.weight')"
                        :hint="t('app.tenant.products.form.hints.weight')"
                        :error="resolveError('weight', errors)"
                        :decimals="3"
                        class="md:col-span-2"
                    />
                    <FormTextField
                        id="unit"
                        name="unit"
                        :label="t('app.tenant.products.fields.unit')"
                        :default-value="props.product?.unit ?? 'cm'"
                        :error="resolveError('unit', errors)"
                        class="md:col-span-4"
                    />
                    <FormSelectField
                        id="dimensions_status"
                        v-model="dimensionsStatus"
                        name="dimensions_status"
                        :label="t('app.tenant.products.fields.dimensions_status')"
                        :required="true"
                        :error="resolveError('dimensions_status', errors)"
                        class="md:col-span-6"
                    >
                        <option value="draft">
                            {{ t('app.tenant.products.dimensions_status_options.draft') }}
                        </option>
                        <option value="published">
                            {{ t('app.tenant.products.dimensions_status_options.published') }}
                        </option>
                    </FormSelectField>
                    <FormTextField
                        id="dimensions_description"
                        name="dimensions_description"
                        :label="t('app.tenant.products.fields.dimensions_description')"
                        :default-value="props.product?.dimensions_description ?? ''"
                        :error="resolveError('dimensions_description', errors)"
                        class="md:col-span-12"
                    />
                </div>

                <div
                    v-show="activeTab === 'additional'"
                    class="grid grid-cols-1 gap-4 md:grid-cols-12"
                >
                    <FormTextField
                        id="type"
                        name="type"
                        :label="t('app.tenant.products.fields.type')"
                        :default-value="props.product?.type ?? ''"
                        :error="resolveError('type', errors)"
                        class="md:col-span-3"
                    />
                    <FormTextField
                        id="reference"
                        name="reference"
                        :label="t('app.tenant.products.fields.reference')"
                        :default-value="props.product?.reference ?? ''"
                        :error="resolveError('reference', errors)"
                        class="md:col-span-3"
                    />
                    <FormTextField
                        id="brand"
                        name="brand"
                        :label="t('app.tenant.products.fields.brand')"
                        :default-value="props.product?.brand ?? ''"
                        :error="resolveError('brand', errors)"
                        class="md:col-span-3"
                    />
                    <FormTextField
                        id="subbrand"
                        name="subbrand"
                        :label="t('app.tenant.products.fields.subbrand')"
                        :default-value="props.product?.subbrand ?? ''"
                        :error="resolveError('subbrand', errors)"
                        class="md:col-span-3"
                    />
                    <FormTextField
                        id="color"
                        name="color"
                        :label="t('app.tenant.products.fields.color')"
                        :default-value="props.product?.color ?? ''"
                        :error="resolveError('color', errors)"
                        class="md:col-span-3"
                    />
                    <FormTextField
                        id="fragrance"
                        name="fragrance"
                        :label="t('app.tenant.products.fields.fragrance')"
                        :default-value="props.product?.fragrance ?? ''"
                        :error="resolveError('fragrance', errors)"
                        class="md:col-span-3"
                    />
                    <FormTextField
                        id="flavor"
                        name="flavor"
                        :label="t('app.tenant.products.fields.flavor')"
                        :default-value="props.product?.flavor ?? ''"
                        :error="resolveError('flavor', errors)"
                        class="md:col-span-3"
                    />
                    <FormTextField
                        id="packaging_type"
                        name="packaging_type"
                        :label="t('app.tenant.products.fields.packaging_type')"
                        :default-value="props.product?.packaging_type ?? ''"
                        :error="resolveError('packaging_type', errors)"
                        class="md:col-span-3"
                    />
                    <FormTextField
                        id="packaging_size"
                        name="packaging_size"
                        :label="t('app.tenant.products.fields.packaging_size')"
                        :default-value="props.product?.packaging_size ?? ''"
                        :error="resolveError('packaging_size', errors)"
                        class="md:col-span-3"
                    />
                    <FormTextField
                        id="packaging_content"
                        name="packaging_content"
                        :label="t('app.tenant.products.fields.packaging_content')"
                        :default-value="props.product?.packaging_content ?? ''"
                        :error="resolveError('packaging_content', errors)"
                        class="md:col-span-3"
                    />
                    <FormTextField
                        id="measurement_unit"
                        name="measurement_unit"
                        :label="t('app.tenant.products.fields.measurement_unit')"
                        :default-value="props.product?.measurement_unit ?? ''"
                        :error="resolveError('measurement_unit', errors)"
                        class="md:col-span-3"
                    />
                    <FormTextField
                        id="unit_measure"
                        name="unit_measure"
                        :label="t('app.tenant.products.fields.unit_measure')"
                        :default-value="props.product?.unit_measure ?? ''"
                        :error="resolveError('unit_measure', errors)"
                        class="md:col-span-3"
                    />
                    <FormTextField
                        id="sortiment_attribute"
                        name="sortiment_attribute"
                        :label="t('app.tenant.products.fields.sortiment_attribute')"
                        :default-value="props.product?.sortiment_attribute ?? ''"
                        :error="resolveError('sortiment_attribute', errors)"
                        class="md:col-span-4"
                    />

                    <div
                        class="rounded-lg border border-border/80 p-3 md:col-span-12"
                    >
                        <p class="mb-3 text-sm font-semibold">
                            {{ t('app.tenant.products.form.logistics') }}
                        </p>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    type="hidden"
                                    name="stackable"
                                    value="0"
                                />
                                <input
                                    type="checkbox"
                                    name="stackable"
                                    value="1"
                                    :checked="props.product?.stackable ?? false"
                                    class="accent-primary"
                                />
                                {{ t('app.tenant.products.fields.stackable') }}
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    type="hidden"
                                    name="perishable"
                                    value="0"
                                />
                                <input
                                    type="checkbox"
                                    name="perishable"
                                    value="1"
                                    :checked="
                                        props.product?.perishable ?? false
                                    "
                                    class="accent-primary"
                                />
                                {{ t('app.tenant.products.fields.perishable') }}
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    type="hidden"
                                    name="flammable"
                                    value="0"
                                />
                                <input
                                    type="checkbox"
                                    name="flammable"
                                    value="1"
                                    :checked="props.product?.flammable ?? false"
                                    class="accent-primary"
                                />
                                {{ t('app.tenant.products.fields.flammable') }}
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    type="hidden"
                                    name="hangable"
                                    value="0"
                                />
                                <input
                                    type="checkbox"
                                    name="hangable"
                                    value="1"
                                    :checked="props.product?.hangable ?? false"
                                    class="accent-primary"
                                />
                                {{ t('app.tenant.products.fields.hangable') }}
                            </label>
                        </div>
                    </div>
                </div>
            </FormCard>
        </Form>
        </div>
    </AppLayout>
</template>

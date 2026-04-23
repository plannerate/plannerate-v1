<script setup lang="ts">
import { Form, Head, setLayoutProps, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Package } from 'lucide-vue-next';
import ProductController from '@/actions/App/Http/Controllers/Tenant/ProductController';
import FormCard from '@/components/FormCard.vue';
import ImageUploadField from '@/components/ImageUploadField.vue';
import InputError from '@/components/InputError.vue';
import CategoryCascadeSelect from '@/components/tenant/CategoryCascadeSelect.vue';
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
    image_url: string | null;
};

type TabKey = 'identification' | 'market' | 'dimensions' | 'additional';

const props = defineProps<{
    subdomain: string;
    product: ProductPayload | null;
}>();

const { t } = useT();
const page = usePage<{ errors: Record<string, string> }>();
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

const tabsOrder: TabKey[] = ['identification', 'market', 'dimensions', 'additional'];
const activeTab = ref<TabKey>('identification');
const imageError = ref('');
const localErrors = ref<Record<string, string>>({});

const productName = ref(props.product?.name ?? '');
const productStatus = ref(props.product?.status ?? 'draft');
const dimensionsStatus = ref(props.product?.dimensions_status ?? 'draft');
const width = ref(toInputValue(props.product?.width));
const height = ref(toInputValue(props.product?.height));
const depth = ref(toInputValue(props.product?.depth));
const weight = ref(toInputValue(props.product?.weight));

function toInputValue(value: string | number | null | undefined): string {
    return value === null || value === undefined ? '' : String(value);
}

const tabs = computed(() => [
    { key: 'identification' as const, label: t('app.tenant.products.form.tabs.identification') },
    { key: 'market' as const, label: t('app.tenant.products.form.tabs.market') },
    { key: 'dimensions' as const, label: t('app.tenant.products.form.tabs.dimensions') },
    { key: 'additional' as const, label: t('app.tenant.products.form.tabs.additional') },
]);

const fieldTabMap: Record<string, TabKey> = {
    name: 'identification',
    category_id: 'identification',
    ean: 'identification',
    status: 'identification',
    url: 'identification',

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
    sortiment_attribute: 'market',
    stackable: 'market',
    perishable: 'market',
    flammable: 'market',
    hangable: 'market',

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
            localErrors.value.name = t('app.tenant.products.form.tabs_validation.name_required');
        }

        if (productStatus.value.trim() === '') {
            localErrors.value.status = t('app.tenant.products.form.tabs_validation.status_required');
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
            localErrors.value.dimensions_status = t('app.tenant.products.form.tabs_validation.dimensions_status_required');
        }

        validateNumberField('width', width.value);
        validateNumberField('height', height.value);
        validateNumberField('depth', depth.value);
        validateNumberField('weight', weight.value);

        return !localErrors.value.dimensions_status
            && !localErrors.value.width
            && !localErrors.value.height
            && !localErrors.value.depth
            && !localErrors.value.weight;
    }

    return true;
}

function validateNumberField(field: 'width' | 'height' | 'depth' | 'weight', value: string): void {
    if (value === '') {
        return;
    }

    const parsed = Number(value);

    if (!Number.isFinite(parsed) || parsed < 0) {
        localErrors.value[field] = t('app.tenant.products.form.tabs_validation.non_negative', {
            field: t(`app.tenant.products.fields.${field}`),
        });
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
        <Form v-bind="isEdit ? updateFormAttrs : storeFormAttrs" v-slot="{ errors, processing }" @submit="onSubmit">
            <FormCard
                :title="isEdit ? t('app.tenant.products.actions.edit') : t('app.tenant.products.actions.new')"
                :description="t('app.tenant.products.description')"
                :processing="processing"
                :cancel-href="productsIndexPath"
            >
                <template #icon>
                    <Package class="size-5" />
                </template>

                <div class="mb-6 flex flex-wrap gap-2 rounded-lg border border-border p-2">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="rounded-md px-3 py-2 text-sm font-medium transition"
                        :class="activeTab === tab.key ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                        @click="setTab(tab.key)"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <div v-show="activeTab === 'identification'" class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    <div class="space-y-4 lg:col-span-8">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                            <div class="flex flex-col gap-y-1 md:col-span-8">
                                <Label for="name">{{ t('app.tenant.products.fields.name') }} <span class="text-destructive">*</span></Label>
                                <Input id="name" v-model="productName" name="name" type="text" required :placeholder="t('app.tenant.products.fields.name')" />
                                <InputError :message="resolveError('name', errors)" />
                            </div>
                            <div class="flex flex-col gap-y-1 md:col-span-4">
                                <Label for="ean">{{ t('app.tenant.products.form.ean') }}</Label>
                                <Input id="ean" name="ean" type="text" :placeholder="t('app.tenant.products.form.ean')" :default-value="props.product?.ean ?? ''" />
                                <InputError :message="resolveError('ean', errors)" />
                            </div>
                        </div>

                        <fieldset class="rounded-lg border border-border/80 p-3">
                            <legend class="px-1 text-xs font-semibold text-foreground">
                                {{ t('app.tenant.products.form.sections.category') }}
                            </legend>
                            <div class="mt-2">
                                <CategoryCascadeSelect :model-value="props.product?.category_id ?? null" :error="resolveError('category_id', errors)" />
                            </div>
                        </fieldset>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                            <div class="flex flex-col gap-y-1 md:col-span-6">
                                <Label for="status">{{ t('app.tenant.products.form.status_product') }} <span class="text-destructive">*</span></Label>
                                <select id="status" v-model="productStatus" name="status" :class="selectClass">
                                    <option value="draft">{{ t('app.tenant.products.status_options.draft') }}</option>
                                    <option value="published">{{ t('app.tenant.products.status_options.published') }}</option>
                                    <option value="synced">{{ t('app.tenant.products.status_options.synced') }}</option>
                                    <option value="error">{{ t('app.tenant.products.status_options.error') }}</option>
                                </select>
                                <InputError :message="resolveError('status', errors)" />
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 lg:col-span-4">
                        <ImageUploadField
                            :subdomain="subdomain"
                            name="url"
                            :label="t('app.tenant.products.form.sections.image')"
                            :initial-url="props.product?.image_url ?? ''"
                            :initial-path="props.product?.url ?? ''"
                            :ai-enabled="true"
                            @uploaded="onImageUploaded"
                            @ai-processed="onImageProcessed"
                            @error="onImageError"
                        />
                        <InputError :message="imageError || resolveError('url', errors)" />
                    </div>
                </div>

                <div v-show="activeTab === 'market'" class="grid grid-cols-1 gap-4 md:grid-cols-12">
                    <div class="flex flex-col gap-y-1 md:col-span-3">
                        <Label for="type">{{ t('app.tenant.products.fields.type') }}</Label>
                        <Input id="type" name="type" :default-value="props.product?.type ?? ''" />
                        <InputError :message="resolveError('type', errors)" />
                    </div>
                    <div class="flex flex-col gap-y-1 md:col-span-3">
                        <Label for="reference">{{ t('app.tenant.products.fields.reference') }}</Label>
                        <Input id="reference" name="reference" :default-value="props.product?.reference ?? ''" />
                        <InputError :message="resolveError('reference', errors)" />
                    </div>
                    <div class="flex flex-col gap-y-1 md:col-span-3">
                        <Label for="codigo_erp">{{ t('app.tenant.products.fields.codigo_erp') }}</Label>
                        <Input id="codigo_erp" name="codigo_erp" :default-value="props.product?.codigo_erp ?? ''" />
                        <InputError :message="resolveError('codigo_erp', errors)" />
                    </div>
                    <div class="flex flex-col gap-y-1 md:col-span-3">
                        <Label for="brand">{{ t('app.tenant.products.fields.brand') }}</Label>
                        <Input id="brand" name="brand" :default-value="props.product?.brand ?? ''" />
                        <InputError :message="resolveError('brand', errors)" />
                    </div>
                    <div class="flex flex-col gap-y-1 md:col-span-3">
                        <Label for="subbrand">{{ t('app.tenant.products.fields.subbrand') }}</Label>
                        <Input id="subbrand" name="subbrand" :default-value="props.product?.subbrand ?? ''" />
                        <InputError :message="resolveError('subbrand', errors)" />
                    </div>
                    <div class="flex flex-col gap-y-1 md:col-span-3">
                        <Label for="color">{{ t('app.tenant.products.fields.color') }}</Label>
                        <Input id="color" name="color" :default-value="props.product?.color ?? ''" />
                        <InputError :message="resolveError('color', errors)" />
                    </div>
                    <div class="flex flex-col gap-y-1 md:col-span-3">
                        <Label for="fragrance">{{ t('app.tenant.products.fields.fragrance') }}</Label>
                        <Input id="fragrance" name="fragrance" :default-value="props.product?.fragrance ?? ''" />
                        <InputError :message="resolveError('fragrance', errors)" />
                    </div>
                    <div class="flex flex-col gap-y-1 md:col-span-3">
                        <Label for="flavor">{{ t('app.tenant.products.fields.flavor') }}</Label>
                        <Input id="flavor" name="flavor" :default-value="props.product?.flavor ?? ''" />
                        <InputError :message="resolveError('flavor', errors)" />
                    </div>
                    <div class="flex flex-col gap-y-1 md:col-span-3">
                        <Label for="packaging_type">{{ t('app.tenant.products.fields.packaging_type') }}</Label>
                        <Input id="packaging_type" name="packaging_type" :default-value="props.product?.packaging_type ?? ''" />
                        <InputError :message="resolveError('packaging_type', errors)" />
                    </div>
                    <div class="flex flex-col gap-y-1 md:col-span-3">
                        <Label for="packaging_size">{{ t('app.tenant.products.fields.packaging_size') }}</Label>
                        <Input id="packaging_size" name="packaging_size" :default-value="props.product?.packaging_size ?? ''" />
                        <InputError :message="resolveError('packaging_size', errors)" />
                    </div>
                    <div class="flex flex-col gap-y-1 md:col-span-3">
                        <Label for="packaging_content">{{ t('app.tenant.products.fields.packaging_content') }}</Label>
                        <Input id="packaging_content" name="packaging_content" :default-value="props.product?.packaging_content ?? ''" />
                        <InputError :message="resolveError('packaging_content', errors)" />
                    </div>
                    <div class="flex flex-col gap-y-1 md:col-span-3">
                        <Label for="measurement_unit">{{ t('app.tenant.products.fields.measurement_unit') }}</Label>
                        <Input id="measurement_unit" name="measurement_unit" :default-value="props.product?.measurement_unit ?? ''" />
                        <InputError :message="resolveError('measurement_unit', errors)" />
                    </div>
                    <div class="flex flex-col gap-y-1 md:col-span-3">
                        <Label for="unit_measure">{{ t('app.tenant.products.fields.unit_measure') }}</Label>
                        <Input id="unit_measure" name="unit_measure" :default-value="props.product?.unit_measure ?? ''" />
                        <InputError :message="resolveError('unit_measure', errors)" />
                    </div>
                    <div class="flex flex-col gap-y-1 md:col-span-4">
                        <Label for="sortiment_attribute">{{ t('app.tenant.products.fields.sortiment_attribute') }}</Label>
                        <Input id="sortiment_attribute" name="sortiment_attribute" :default-value="props.product?.sortiment_attribute ?? ''" />
                        <InputError :message="resolveError('sortiment_attribute', errors)" />
                    </div>

                    <div class="md:col-span-12">
                        <p class="mb-3 text-sm font-semibold">{{ t('app.tenant.products.form.logistics') }}</p>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
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
                        </div>
                    </div>
                </div>

                <div v-show="activeTab === 'dimensions'" class="grid grid-cols-1 gap-4 md:grid-cols-12">
                    <div class="flex flex-col gap-y-1 md:col-span-2">
                        <Label for="width">{{ t('app.tenant.products.fields.width') }} (cm)</Label>
                        <Input id="width" v-model="width" name="width" type="number" step="0.01" min="0" />
                        <p class="text-sm text-muted-foreground">{{ t('app.tenant.products.form.hints.width') }}</p>
                        <InputError :message="resolveError('width', errors)" />
                    </div>
                    <div class="flex flex-col gap-y-1 md:col-span-2">
                        <Label for="height">{{ t('app.tenant.products.fields.height') }} (cm)</Label>
                        <Input id="height" v-model="height" name="height" type="number" step="0.01" min="0" />
                        <p class="text-sm text-muted-foreground">{{ t('app.tenant.products.form.hints.height') }}</p>
                        <InputError :message="resolveError('height', errors)" />
                    </div>
                    <div class="flex flex-col gap-y-1 md:col-span-2">
                        <Label for="depth">{{ t('app.tenant.products.fields.depth') }} (cm)</Label>
                        <Input id="depth" v-model="depth" name="depth" type="number" step="0.01" min="0" />
                        <p class="text-sm text-muted-foreground">{{ t('app.tenant.products.form.hints.depth') }}</p>
                        <InputError :message="resolveError('depth', errors)" />
                    </div>
                    <div class="flex flex-col gap-y-1 md:col-span-2">
                        <Label for="weight">{{ t('app.tenant.products.fields.weight') }}</Label>
                        <Input id="weight" v-model="weight" name="weight" type="number" step="0.01" min="0" />
                        <p class="text-sm text-muted-foreground">{{ t('app.tenant.products.form.hints.weight') }}</p>
                        <InputError :message="resolveError('weight', errors)" />
                    </div>
                    <div class="flex flex-col gap-y-1 md:col-span-4">
                        <Label for="unit">{{ t('app.tenant.products.fields.unit') }}</Label>
                        <Input id="unit" name="unit" type="text" :default-value="props.product?.unit ?? 'cm'" />
                        <InputError :message="resolveError('unit', errors)" />
                    </div>
                    <div class="flex flex-col gap-y-1 md:col-span-6">
                        <Label for="dimensions_status">{{ t('app.tenant.products.fields.dimensions_status') }} <span class="text-destructive">*</span></Label>
                        <select id="dimensions_status" v-model="dimensionsStatus" name="dimensions_status" :class="selectClass">
                            <option value="draft">{{ t('app.tenant.products.dimensions_status_options.draft') }}</option>
                            <option value="published">{{ t('app.tenant.products.dimensions_status_options.published') }}</option>
                        </select>
                        <InputError :message="resolveError('dimensions_status', errors)" />
                    </div>
                    <div class="flex flex-col gap-y-1 md:col-span-12">
                        <Label for="dimensions_description">{{ t('app.tenant.products.fields.dimensions_description') }}</Label>
                        <Input id="dimensions_description" name="dimensions_description" :default-value="props.product?.dimensions_description ?? ''" />
                        <InputError :message="resolveError('dimensions_description', errors)" />
                    </div>
                </div>

                <div v-show="activeTab === 'additional'" class="grid grid-cols-1 gap-4 md:grid-cols-12">
                    <div class="flex flex-col gap-y-1 md:col-span-6">
                        <Label for="auxiliary_description">{{ t('app.tenant.products.fields.auxiliary_description') }}</Label>
                        <textarea
                            id="auxiliary_description"
                            name="auxiliary_description"
                            rows="3"
                            :class="textareaClass"
                            :placeholder="t('app.tenant.products.fields.auxiliary_description')"
                        >{{ props.product?.auxiliary_description ?? '' }}</textarea>
                        <InputError :message="resolveError('auxiliary_description', errors)" />
                    </div>
                    <div class="flex flex-col gap-y-1 md:col-span-6">
                        <Label for="additional_information">{{ t('app.tenant.products.fields.additional_information') }}</Label>
                        <textarea
                            id="additional_information"
                            name="additional_information"
                            rows="3"
                            :class="textareaClass"
                            :placeholder="t('app.tenant.products.fields.additional_information')"
                        >{{ props.product?.additional_information ?? '' }}</textarea>
                        <InputError :message="resolveError('additional_information', errors)" />
                    </div>
                    <div class="flex flex-col gap-y-1 md:col-span-12">
                        <Label for="description">{{ t('app.tenant.products.fields.description') }}</Label>
                        <textarea id="description" name="description" rows="4" :class="textareaClass">{{ props.product?.description ?? '' }}</textarea>
                        <InputError :message="resolveError('description', errors)" />
                    </div>
                </div>
            </FormCard>
        </Form>
    </div>
</template>

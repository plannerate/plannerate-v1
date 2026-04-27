<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Store as StoreIcon } from 'lucide-vue-next';
import StoreController from '@/actions/App/Http/Controllers/Tenant/StoreController';
import AppLayout from '@/layouts/AppLayout.vue';
import AddressFields from '@/components/form/AddressFields.vue';
import FormMapField from '@/components/form/FormMapField.vue';
import FormTabsBar from '@/components/form/FormTabsBar.vue';
import FormStatusField from '@/components/form/FormStatusField.vue';
import FormTextareaField from '@/components/form/FormTextareaField.vue';
import FormTextField from '@/components/form/FormTextField.vue';
import FormCard from '@/components/FormCard.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';

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

type MapRegion = {
    id: string;
    x: number;
    y: number;
    width: number;
    height: number;
    shape?: 'rectangle' | 'circle';
    label?: string | null;
    type?: string | null;
    color?: string | null;
    gondola_id?: string | null;
};

type MapData = {
    image?: string;
    image_url?: string | null;
    regions: MapRegion[];
};

type TabKey = 'identificacao' | 'endereco' | 'mapa_da_loja';

const props = defineProps<{
    subdomain: string;
    store: (StorePayload & { map: MapData | null }) | null;
    address: AddressPayload | null;
}>();

const { t } = useT();
const isEdit = computed(() => props.store !== null);
const storesIndexPath = StoreController.index
    .url(props.subdomain)
    .replace(/^\/\/[^/]+/, '');
const activeTab = ref<TabKey>('identificacao');
const storeMap = ref<MapData | null>(props.store?.map ?? null);

const tabs = computed(() => [
    {
        key: 'identificacao' as const,
        label: t('app.tenant.stores.tabs.identificacao'),
    },
    {
        key: 'endereco' as const,
        label: t('app.tenant.stores.tabs.endereco'),
    },
    {
        key: 'mapa_da_loja' as const,
        label: t('app.tenant.stores.tabs.mapa_da_loja'),
    },
]);

const mapColumn = computed(() => ({
    name: 'map',
    label: t('app.tenant.stores.fields.map'),
    helpText: t('app.tenant.stores.hints.map'),
}));

const pageMeta = useCrudPageMeta(
    {
        headTitle: isEdit.value
            ? t('app.tenant.stores.actions.edit')
            : t('app.tenant.stores.actions.new'),
        title: isEdit.value
            ? t('app.tenant.stores.actions.edit')
            : t('app.tenant.stores.actions.new'),
        description: t('app.tenant.stores.description'),
        breadcrumbs: [
            {
                title: t('app.navigation.dashboard'),
                href: dashboard.url().replace(/^\/\/[^/]+/, ''),
            },
            { title: t('app.tenant.stores.navigation'), href: storesIndexPath },
            {
                title: isEdit.value
                    ? t('app.tenant.stores.actions.edit')
                    : t('app.tenant.stores.actions.new'),
                href: isEdit.value
                    ? StoreController.edit.url({
                          subdomain: props.subdomain,
                          store: props.store!.id,
                      })
                    : StoreController.create.url(props.subdomain),
            },
        ],
    },
);
</script>

<template>
    <Head :title="pageMeta.headTitle" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="p-4">
            <Form
                v-bind="
                    isEdit
                        ? StoreController.update.form({
                              subdomain: props.subdomain,
                              store: props.store!.id,
                          })
                        : StoreController.store.form(props.subdomain)
                "
                v-slot="{ errors, processing }"
            >
                <FormCard
                    :processing="processing"
                    :cancel-href="storesIndexPath"
                >
                    <template #icon>
                        <StoreIcon class="size-5" />
                    </template>

                    <FormTabsBar v-model="activeTab" :tabs="tabs" />

                    <!-- Tab: Identificação -->
                    <div
                        v-show="activeTab === 'identificacao'"
                        class="grid grid-cols-1 gap-4 md:grid-cols-12"
                    >
                        <FormTextField
                            id="name"
                            name="name"
                            :label="t('app.tenant.stores.fields.name')"
                            :default-value="props.store?.name ?? ''"
                            :error="errors.name"
                            class="md:col-span-6"
                            required
                        />

                        <FormTextField
                            id="document"
                            name="document"
                            :label="t('app.tenant.stores.fields.document')"
                            :default-value="props.store?.document ?? ''"
                            :error="errors.document"
                            class="md:col-span-3"
                        />

                        <FormTextField
                            id="code"
                            name="code"
                            :label="t('app.tenant.stores.fields.code')"
                            :default-value="props.store?.code ?? ''"
                            :error="errors.code"
                            class="md:col-span-3"
                        />

                        <FormTextField
                            id="slug"
                            name="slug"
                            label="Slug"
                            :default-value="props.store?.slug ?? ''"
                            :error="errors.slug"
                            class="md:col-span-4"
                        />

                        <FormTextField
                            id="phone"
                            name="phone"
                            :label="t('app.tenant.stores.fields.phone')"
                            :default-value="props.store?.phone ?? ''"
                            :error="errors.phone"
                            class="md:col-span-4"
                        />

                        <FormTextField
                            id="email"
                            name="email"
                            type="email"
                            :label="t('app.tenant.stores.fields.email')"
                            :default-value="props.store?.email ?? ''"
                            :error="errors.email"
                            class="md:col-span-4"
                        />

                        <FormStatusField
                            id="status"
                            name="status"
                            :label="t('app.tenant.stores.fields.status')"
                            :default-value="props.store?.status ?? 'draft'"
                            :error="errors.status"
                            class="md:col-span-3"
                            :options="[
                                { value: 'draft', label: 'Draft' },
                                { value: 'published', label: 'Published' },
                            ]"
                        />

                        <FormTextareaField
                            id="description"
                            name="description"
                            :label="t('app.tenant.stores.fields.description')"
                            :default-value="props.store?.description ?? ''"
                            :error="errors.description"
                            class="col-span-12"
                            :rows="2"
                        />
                    </div>

                    <!-- Tab: Endereço -->
                    <div v-show="activeTab === 'endereco'">
                        <AddressFields
                            :model-value="props.address"
                            :errors="errors"
                        />
                    </div>

                    <!-- Tab: Mapa Da Loja -->
                    <div v-show="activeTab === 'mapa_da_loja'">
                        <FormMapField
                            v-model="storeMap"
                            :column="mapColumn"
                            :visible="activeTab === 'mapa_da_loja'"
                            :error="
                                errors.map ||
                                errors['map.image'] ||
                                errors['map.regions']
                            "
                        />
                    </div>
                </FormCard>
            </Form>
        </div>
    </AppLayout>
</template>

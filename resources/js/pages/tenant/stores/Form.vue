<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Store as StoreIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import StoreController from '@/actions/App/Http/Controllers/Tenant/StoreController';
import CepLookupField from '@/components/form/CepLookupField.vue';
import FormMapField from '@/components/form/FormMapField.vue';
import FormStatusToggleField from '@/components/form/FormStatusToggleField.vue';
import FormTabsBar from '@/components/form/FormTabsBar.vue';
import FormTextField from '@/components/form/FormTextField.vue';
import FormCard from '@/components/FormCard.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
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

type TabKey = 'identificacao' | 'mapa_da_loja';

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
const addressZipCode = ref(props.address?.zip_code ?? '');
const addressStreet = ref(props.address?.street ?? '');
const addressNumber = ref(props.address?.number ?? '');
const addressComplement = ref(props.address?.complement ?? '');
const addressDistrict = ref(props.address?.district ?? '');
const addressCity = ref(props.address?.city ?? '');
const addressState = ref(props.address?.state ?? '');
const addressCountry = ref(props.address?.country ?? 'Brasil');

const tabs = computed(() => [
    {
        key: 'identificacao' as const,
        label: t('app.tenant.stores.tabs.identificacao'),
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

function onAddressCepResolved(payload: {
    street: string;
    district: string;
    city: string;
    state: string;
    complement: string;
}): void {
    addressStreet.value = payload.street;
    addressDistrict.value = payload.district;
    addressCity.value = payload.city;
    addressState.value = payload.state;

    if (payload.complement !== '') {
        addressComplement.value = payload.complement;
    }
}

const pageMeta = useCrudPageMeta({
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
});
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
                    :title="pageMeta.title"
                    :description="pageMeta.description"
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
                            id="code"
                            name="code"
                            label="Código loja"
                            :default-value="props.store?.code ?? ''"
                            :error="errors.code"
                            class="md:col-span-3"
                        />

                        <FormTextField
                            id="name"
                            name="name"
                            label="Nome da loja"
                            :default-value="props.store?.name ?? ''"
                            :error="errors.name"
                            class="md:col-span-5"
                            required
                        />

                        <FormTextField
                            id="slug"
                            name="slug"
                            label="Cluster"
                            :default-value="props.store?.slug ?? ''"
                            :error="errors.slug"
                            class="md:col-span-4"
                        />

                        <FormTextField
                            id="document"
                            name="document"
                            label="CNPJ"
                            :default-value="props.store?.document ?? ''"
                            :error="errors.document"
                            class="md:col-span-3"
                        />

                        <FormTextField
                            id="email"
                            name="email"
                            type="email"
                            label="Email"
                            :default-value="props.store?.email ?? ''"
                            :error="errors.email"
                            class="md:col-span-5"
                        />

                        <FormTextField
                            id="phone"
                            name="phone"
                            label="Telefone"
                            :default-value="props.store?.phone ?? ''"
                            :error="errors.phone"
                            class="md:col-span-4"
                        />

                        <div class="md:col-span-12">
                            <div
                                class="space-y-4 rounded-xl border border-border/70 bg-muted/20 p-4 md:p-5"
                            >
                                <input
                                    type="hidden"
                                    name="address[id]"
                                    :value="props.address?.id ?? ''"
                                />
                                <input
                                    type="hidden"
                                    name="address[type]"
                                    :value="props.address?.type ?? 'home'"
                                />
                                <input
                                    type="hidden"
                                    name="address[name]"
                                    :value="props.address?.name ?? ''"
                                />
                                <input
                                    type="hidden"
                                    name="address[reference]"
                                    :value="props.address?.reference ?? ''"
                                />
                                <input
                                    type="hidden"
                                    name="address[additional_information]"
                                    :value="
                                        props.address?.additional_information ??
                                        ''
                                    "
                                />
                                <input
                                    type="hidden"
                                    name="address[is_default]"
                                    :value="
                                        props.address?.is_default ? '1' : '0'
                                    "
                                />
                                <input
                                    type="hidden"
                                    name="address[status]"
                                    :value="props.address?.status ?? 'draft'"
                                />

                                <div
                                    class="grid grid-cols-1 gap-4 md:grid-cols-12"
                                >
                                    <CepLookupField
                                        id="address-zip_code"
                                        v-model="addressZipCode"
                                        name="address[zip_code]"
                                        label="Cep"
                                        :error="errors['address.zip_code']"
                                        class="md:col-span-3"
                                        @resolved="onAddressCepResolved"
                                    />

                                    <FormTextField
                                        id="address-street"
                                        v-model="addressStreet"
                                        name="address[street]"
                                        label="Rua"
                                        :error="errors['address.street']"
                                        class="md:col-span-6"
                                    />

                                    <FormTextField
                                        id="address-number"
                                        v-model="addressNumber"
                                        name="address[number]"
                                        label="Numero"
                                        :error="errors['address.number']"
                                        class="md:col-span-3"
                                    />

                                    <FormTextField
                                        id="address-complement"
                                        v-model="addressComplement"
                                        name="address[complement]"
                                        label="Complemento"
                                        :error="errors['address.complement']"
                                        class="md:col-span-4"
                                    />

                                    <FormTextField
                                        id="address-district"
                                        v-model="addressDistrict"
                                        name="address[district]"
                                        label="Bairro"
                                        :error="errors['address.district']"
                                        class="md:col-span-4"
                                    />

                                    <FormTextField
                                        id="address-city"
                                        v-model="addressCity"
                                        name="address[city]"
                                        label="Cidade"
                                        :error="errors['address.city']"
                                        class="md:col-span-4"
                                    />

                                    <FormTextField
                                        id="address-state"
                                        v-model="addressState"
                                        name="address[state]"
                                        label="Estado"
                                        :error="errors['address.state']"
                                        class="md:col-span-3"
                                    />

                                    <FormTextField
                                        id="address-country"
                                        v-model="addressCountry"
                                        name="address[country]"
                                        label="Pais"
                                        :error="errors['address.country']"
                                        class="md:col-span-9"
                                    />
                                </div>
                            </div>
                        </div>

                        <FormStatusToggleField
                            id="status"
                            name="status"
                            :label="t('app.tenant.stores.fields.status')"
                            :default-value="props.store?.status ?? 'draft'"
                            :error="errors.status"
                            class="md:col-span-12"
                            :checked-label="
                                t('app.tenant.stores.status_published')
                            "
                            :unchecked-label="
                                t('app.tenant.stores.status_draft')
                            "
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

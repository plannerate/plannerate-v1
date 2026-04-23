<script setup lang="ts">
import { ref } from 'vue';
import CepLookupField from '@/components/form/CepLookupField.vue';
import FormSelectField from '@/components/form/FormSelectField.vue';
import FormStatusField from '@/components/form/FormStatusField.vue';
import FormTextareaField from '@/components/form/FormTextareaField.vue';
import FormTextField from '@/components/form/FormTextField.vue';
import InputError from '@/components/InputError.vue';
import { useT } from '@/composables/useT';

type AddressFormValue = {
    type?: string | null;
    name?: string | null;
    zip_code?: string | null;
    street?: string | null;
    number?: string | null;
    complement?: string | null;
    reference?: string | null;
    additional_information?: string | null;
    district?: string | null;
    city?: string | null;
    state?: string | null;
    country?: string | null;
    is_default?: boolean;
    status?: 'draft' | 'published';
};

const props = withDefaults(
    defineProps<{
        prefix?: string;
        modelValue?: AddressFormValue | null;
        errors?: Record<string, string>;
    }>(),
    {
        prefix: 'address',
        modelValue: null,
        errors: () => ({}),
    },
);

const { t } = useT();

function inputName(field: keyof AddressFormValue): string {
    return `${props.prefix}[${field}]`;
}

function errorKey(field: keyof AddressFormValue): string {
    return `${props.prefix}.${field}`;
}

function value(field: keyof AddressFormValue): string {
    if (field === 'country') {
        return (props.modelValue?.[field] as string | undefined) ?? 'Brasil';
    }

    if (field === 'status') {
        return (props.modelValue?.[field] as string | undefined) ?? 'draft';
    }

    if (field === 'type') {
        return (props.modelValue?.[field] as string | undefined) ?? 'home';
    }

    return (props.modelValue?.[field] as string | undefined) ?? '';
}

const type = ref(value('type'));
const name = ref(value('name'));
const zipCode = ref(value('zip_code'));
const street = ref(value('street'));
const number = ref(value('number'));
const complement = ref(value('complement'));
const reference = ref(value('reference'));
const additionalInformation = ref(value('additional_information'));
const district = ref(value('district'));
const city = ref(value('city'));
const state = ref(value('state'));
const country = ref(value('country'));
const status = ref(value('status'));
const isDefault = ref(Boolean(props.modelValue?.is_default ?? false));

function onCepResolved(payload: {
    street: string;
    district: string;
    city: string;
    state: string;
    complement: string;
}): void {
    street.value = payload.street;
    district.value = payload.district;
    city.value = payload.city;
    state.value = payload.state;

    if (payload.complement !== '') {
        complement.value = payload.complement;
    }
}
</script>

<template>
    <div
        class="space-y-4 rounded-xl border border-border/70 bg-muted/20 p-4 md:p-5"
    >
        <div>
            <h3 class="text-base font-semibold">
                {{ t('app.addresses.title') }}
            </h3>
            <p class="text-sm text-muted-foreground">
                {{ t('app.addresses.description') }}
            </p>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
            <FormSelectField
                :id="`${prefix}-type`"
                v-model="type"
                :name="inputName('type')"
                :label="t('app.addresses.fields.type')"
                :error="props.errors[errorKey('type')]"
                class="md:col-span-4"
            >
                <option value="">-</option>
                <option value="home">
                    {{ t('app.addresses.types.home') }}
                </option>
                <option value="billing">
                    {{ t('app.addresses.types.billing') }}
                </option>
                <option value="shipping">
                    {{ t('app.addresses.types.shipping') }}
                </option>
                <option value="commercial">
                    {{ t('app.addresses.types.commercial') }}
                </option>
            </FormSelectField>

            <FormTextField
                :id="`${prefix}-name`"
                v-model="name"
                :name="inputName('name')"
                :label="t('app.addresses.fields.name')"
                :error="props.errors[errorKey('name')]"
                class="md:col-span-8"
            />

            <CepLookupField
                :id="`${prefix}-zip_code`"
                v-model="zipCode"
                :name="inputName('zip_code')"
                :label="t('app.addresses.fields.zip_code')"
                :error="props.errors[errorKey('zip_code')]"
                :hint="t('app.addresses.hints.zip_code')"
                class="md:col-span-3"
                @resolved="onCepResolved"
            />

            <FormTextField
                :id="`${prefix}-street`"
                v-model="street"
                :name="inputName('street')"
                :label="t('app.addresses.fields.street')"
                :error="props.errors[errorKey('street')]"
                class="md:col-span-7"
            />

            <FormTextField
                :id="`${prefix}-number`"
                v-model="number"
                :name="inputName('number')"
                :label="t('app.addresses.fields.number')"
                :error="props.errors[errorKey('number')]"
                class="md:col-span-2"
            />

            <FormTextField
                :id="`${prefix}-district`"
                v-model="district"
                :name="inputName('district')"
                :label="t('app.addresses.fields.district')"
                :error="props.errors[errorKey('district')]"
                class="md:col-span-4"
            />

            <FormTextField
                :id="`${prefix}-city`"
                v-model="city"
                :name="inputName('city')"
                :label="t('app.addresses.fields.city')"
                :error="props.errors[errorKey('city')]"
                class="md:col-span-4"
            />

            <FormTextField
                :id="`${prefix}-state`"
                v-model="state"
                :name="inputName('state')"
                :label="t('app.addresses.fields.state')"
                :error="props.errors[errorKey('state')]"
                class="md:col-span-2"
            />

            <FormTextField
                :id="`${prefix}-country`"
                v-model="country"
                :name="inputName('country')"
                :label="t('app.addresses.fields.country')"
                :error="props.errors[errorKey('country')]"
                class="md:col-span-2"
            />

            <FormTextField
                :id="`${prefix}-complement`"
                v-model="complement"
                :name="inputName('complement')"
                :label="t('app.addresses.fields.complement')"
                :error="props.errors[errorKey('complement')]"
                class="md:col-span-7"
            />

            <FormTextField
                :id="`${prefix}-reference`"
                v-model="reference"
                :name="inputName('reference')"
                :label="t('app.addresses.fields.reference')"
                :error="props.errors[errorKey('reference')]"
                class="md:col-span-5"
            />

            <FormStatusField
                :id="`${prefix}-status`"
                v-model="status"
                :name="inputName('status')"
                :label="t('app.addresses.fields.status')"
                :error="props.errors[errorKey('status')]"
                class="md:col-span-4"
                :options="[
                    { value: 'draft', label: t('app.addresses.statuses.draft') },
                    { value: 'published', label: t('app.addresses.statuses.published') },
                ]"
            />

            <FormTextareaField
                :id="`${prefix}-additional_information`"
                v-model="additionalInformation"
                :name="inputName('additional_information')"
                :label="t('app.addresses.fields.additional_information')"
                :error="props.errors[errorKey('additional_information')]"
                class="md:col-span-12"
                :rows="2"
            />
        </div>

        <label
            class="flex cursor-pointer items-center gap-3 rounded-lg border border-border bg-muted/30 px-4 py-3 transition-colors hover:bg-muted/50 has-checked:border-primary/50 has-checked:bg-primary/5"
        >
            <input type="hidden" :name="inputName('is_default')" value="0" />
            <input
                :id="`${prefix}-is_default`"
                v-model="isDefault"
                :name="inputName('is_default')"
                type="checkbox"
                value="1"
                class="accent-primary"
            />
            <div>
                <span class="text-sm font-medium">{{
                    t('app.addresses.fields.is_default')
                }}</span>
            </div>
            <InputError :message="props.errors[errorKey('is_default')]" />
        </label>
    </div>
</template>

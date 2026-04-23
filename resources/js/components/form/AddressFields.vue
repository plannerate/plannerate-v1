<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

    return (props.modelValue?.[field] as string | undefined) ?? '';
}
</script>

<template>
    <div class="space-y-4 rounded-lg border border-border p-4">
        <div>
            <h3 class="text-base font-semibold">{{ t('app.addresses.title') }}</h3>
            <p class="text-sm text-muted-foreground">{{ t('app.addresses.description') }}</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-2">
                <Label :for="`${prefix}-type`">{{ t('app.addresses.fields.type') }}</Label>
                <Input :id="`${prefix}-type`" :name="inputName('type')" :default-value="value('type')" />
                <InputError :message="props.errors[errorKey('type')]" />
            </div>
            <div class="grid gap-2">
                <Label :for="`${prefix}-name`">{{ t('app.addresses.fields.name') }}</Label>
                <Input :id="`${prefix}-name`" :name="inputName('name')" :default-value="value('name')" />
                <InputError :message="props.errors[errorKey('name')]" />
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-2">
                <Label :for="`${prefix}-zip_code`">{{ t('app.addresses.fields.zip_code') }}</Label>
                <Input :id="`${prefix}-zip_code`" :name="inputName('zip_code')" :default-value="value('zip_code')" />
                <InputError :message="props.errors[errorKey('zip_code')]" />
            </div>
            <div class="grid gap-2">
                <Label :for="`${prefix}-street`">{{ t('app.addresses.fields.street') }}</Label>
                <Input :id="`${prefix}-street`" :name="inputName('street')" :default-value="value('street')" />
                <InputError :message="props.errors[errorKey('street')]" />
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="grid gap-2">
                <Label :for="`${prefix}-number`">{{ t('app.addresses.fields.number') }}</Label>
                <Input :id="`${prefix}-number`" :name="inputName('number')" :default-value="value('number')" />
                <InputError :message="props.errors[errorKey('number')]" />
            </div>
            <div class="grid gap-2">
                <Label :for="`${prefix}-complement`">{{ t('app.addresses.fields.complement') }}</Label>
                <Input :id="`${prefix}-complement`" :name="inputName('complement')" :default-value="value('complement')" />
                <InputError :message="props.errors[errorKey('complement')]" />
            </div>
            <div class="grid gap-2">
                <Label :for="`${prefix}-reference`">{{ t('app.addresses.fields.reference') }}</Label>
                <Input :id="`${prefix}-reference`" :name="inputName('reference')" :default-value="value('reference')" />
                <InputError :message="props.errors[errorKey('reference')]" />
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-2">
                <Label :for="`${prefix}-district`">{{ t('app.addresses.fields.district') }}</Label>
                <Input :id="`${prefix}-district`" :name="inputName('district')" :default-value="value('district')" />
                <InputError :message="props.errors[errorKey('district')]" />
            </div>
            <div class="grid gap-2">
                <Label :for="`${prefix}-city`">{{ t('app.addresses.fields.city') }}</Label>
                <Input :id="`${prefix}-city`" :name="inputName('city')" :default-value="value('city')" />
                <InputError :message="props.errors[errorKey('city')]" />
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="grid gap-2">
                <Label :for="`${prefix}-state`">{{ t('app.addresses.fields.state') }}</Label>
                <Input :id="`${prefix}-state`" :name="inputName('state')" :default-value="value('state')" maxlength="2" />
                <InputError :message="props.errors[errorKey('state')]" />
            </div>
            <div class="grid gap-2">
                <Label :for="`${prefix}-country`">{{ t('app.addresses.fields.country') }}</Label>
                <Input :id="`${prefix}-country`" :name="inputName('country')" :default-value="value('country')" />
                <InputError :message="props.errors[errorKey('country')]" />
            </div>
            <div class="grid gap-2">
                <Label :for="`${prefix}-status`">{{ t('app.addresses.fields.status') }}</Label>
                <select
                    :id="`${prefix}-status`"
                    :name="inputName('status')"
                    :value="value('status')"
                    class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                >
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                </select>
                <InputError :message="props.errors[errorKey('status')]" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label :for="`${prefix}-additional_information`">{{ t('app.addresses.fields.additional_information') }}</Label>
            <Input
                :id="`${prefix}-additional_information`"
                :name="inputName('additional_information')"
                :default-value="value('additional_information')"
            />
            <InputError :message="props.errors[errorKey('additional_information')]" />
        </div>

        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-border bg-muted/30 px-4 py-3 transition-colors hover:bg-muted/50 has-checked:border-primary/50 has-checked:bg-primary/5">
            <input type="hidden" :name="inputName('is_default')" value="0" />
            <input
                :id="`${prefix}-is_default`"
                :name="inputName('is_default')"
                type="checkbox"
                value="1"
                :checked="props.modelValue?.is_default ?? false"
                class="accent-primary"
            />
            <div>
                <span class="text-sm font-medium">{{ t('app.addresses.fields.is_default') }}</span>
            </div>
            <InputError :message="props.errors[errorKey('is_default')]" />
        </label>
    </div>
</template>

<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Barcode } from 'lucide-vue-next';
import { computed } from 'vue';
import EanReferenceController from '@/actions/App/Http/Controllers/Tenant/EanReferenceController';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';

type EanReferencePayload = {
    id: string;
    ean: string;
    reference_description: string | null;
    brand: string | null;
    subbrand: string | null;
    packaging_type: string | null;
    packaging_size: string | null;
    measurement_unit: string | null;
    width: string | number | null;
    height: string | number | null;
    depth: string | number | null;
    weight: string | number | null;
    unit: string | null;
    has_dimensions: boolean;
    dimension_status: 'draft' | 'published' | null;
};

const props = defineProps<{
    subdomain: string;
    ean_reference: EanReferencePayload | null;
}>();

const { t } = useT();
const isEdit = computed(() => props.ean_reference !== null);
const eanReferencesIndexPath = tenantWayfinderPath(EanReferenceController.index.url(props.subdomain));

const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value ? t('app.tenant.ean_references.actions.edit') : t('app.tenant.ean_references.actions.new'),
    title: isEdit.value ? t('app.tenant.ean_references.actions.edit') : t('app.tenant.ean_references.actions.new'),
    description: t('app.tenant.ean_references.description'),
    breadcrumbs: [
        {
            title: t('app.navigation.dashboard'),
            href: dashboard.url().replace(/^\/\/[^/]+/, ''),
        },
        {
            title: t('app.tenant.ean_references.navigation'),
            href: eanReferencesIndexPath,
        },
    ],
});
</script>

<template>
    <Head :title="pageMeta.headTitle" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="p-4">
            <Form
                v-bind="isEdit
                    ? {
                        ...EanReferenceController.update.form({ subdomain: props.subdomain, ean_reference: props.ean_reference!.id }),
                        action: tenantWayfinderPath(EanReferenceController.update.url({ subdomain: props.subdomain, ean_reference: props.ean_reference!.id })),
                    }
                    : {
                        ...EanReferenceController.store.form(props.subdomain),
                        action: tenantWayfinderPath(EanReferenceController.store.url(props.subdomain)),
                    }
                "
                v-slot="{ errors, processing }"
            >
                <FormCard
                    :processing="processing"
                    :cancel-href="eanReferencesIndexPath"
                >
                    <template #icon>
                        <Barcode class="size-5" />
                    </template>

                    <div class="grid gap-2">
                        <Label for="ean">{{ t('app.tenant.ean_references.fields.ean') }}</Label>
                        <Input id="ean" name="ean" :default-value="props.ean_reference?.ean ?? ''" required />
                        <InputError :message="errors.ean" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="reference_description">{{ t('app.tenant.ean_references.fields.reference_description') }}</Label>
                        <textarea
                            id="reference_description"
                            name="reference_description"
                            rows="3"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                            :value="props.ean_reference?.reference_description ?? ''"
                        ></textarea>
                        <InputError :message="errors.reference_description" />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="brand">{{ t('app.tenant.ean_references.fields.brand') }}</Label>
                            <Input id="brand" name="brand" :default-value="props.ean_reference?.brand ?? ''" />
                            <InputError :message="errors.brand" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="subbrand">{{ t('app.tenant.ean_references.fields.subbrand') }}</Label>
                            <Input id="subbrand" name="subbrand" :default-value="props.ean_reference?.subbrand ?? ''" />
                            <InputError :message="errors.subbrand" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="packaging_type">{{ t('app.tenant.ean_references.fields.packaging_type') }}</Label>
                            <Input id="packaging_type" name="packaging_type" :default-value="props.ean_reference?.packaging_type ?? ''" />
                            <InputError :message="errors.packaging_type" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="packaging_size">{{ t('app.tenant.ean_references.fields.packaging_size') }}</Label>
                            <Input id="packaging_size" name="packaging_size" :default-value="props.ean_reference?.packaging_size ?? ''" />
                            <InputError :message="errors.packaging_size" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="measurement_unit">{{ t('app.tenant.ean_references.fields.measurement_unit') }}</Label>
                            <Input id="measurement_unit" name="measurement_unit" :default-value="props.ean_reference?.measurement_unit ?? ''" />
                            <InputError :message="errors.measurement_unit" />
                        </div>
                    </div>

                    <div class="mt-2 border-t border-border pt-4">
                        <p class="mb-3 text-sm font-semibold text-foreground">Dimensoes</p>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="width">Largura</Label>
                                <Input id="width" name="width" type="number" min="0" step="0.01" :default-value="props.ean_reference?.width ?? ''" />
                                <InputError :message="errors.width" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="height">Altura</Label>
                                <Input id="height" name="height" type="number" min="0" step="0.01" :default-value="props.ean_reference?.height ?? ''" />
                                <InputError :message="errors.height" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="depth">Profundidade</Label>
                                <Input id="depth" name="depth" type="number" min="0" step="0.01" :default-value="props.ean_reference?.depth ?? ''" />
                                <InputError :message="errors.depth" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="weight">Peso</Label>
                                <Input id="weight" name="weight" type="number" min="0" step="0.01" :default-value="props.ean_reference?.weight ?? ''" />
                                <InputError :message="errors.weight" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="unit">Unidade</Label>
                                <Input id="unit" name="unit" :default-value="props.ean_reference?.unit ?? 'cm'" />
                                <InputError :message="errors.unit" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="dimension_status">Status da dimensao</Label>
                                <select
                                    id="dimension_status"
                                    name="dimension_status"
                                    class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                                    :value="props.ean_reference?.dimension_status ?? 'published'"
                                >
                                    <option value="published">Publicado</option>
                                    <option value="draft">Rascunho</option>
                                </select>
                                <InputError :message="errors.dimension_status" />
                            </div>
                        </div>
                    </div>
                </FormCard>
            </Form>
        </div>
    </AppLayout>
</template>

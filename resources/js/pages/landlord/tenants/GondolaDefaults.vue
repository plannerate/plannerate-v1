<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { LayoutGrid, RotateCcw } from 'lucide-vue-next';
import TenantController from '@/actions/App/Http/Controllers/Landlord/TenantController';
import TenantGondolaDefaultsController from '@/actions/App/Http/Controllers/Landlord/TenantGondolaDefaultsController';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';

/** Padrão de gôndola — campos numéricos e de seleção mantidos pelo landlord. */
type GondolaStandard = {
    location: string | null;
    side: string;
    scaleFactor: number;
    flow: 'left_to_right' | 'right_to_left';
    height: number;
    width: number;
    numModules: number;
    baseHeight: number;
    baseWidth: number;
    baseDepth: number;
    rackWidth: number;
    holeHeight: number;
    holeWidth: number;
    holeSpacing: number;
    shelfHeight: number;
    shelfWidth: number;
    shelfDepth: number;
    numShelves: number;
    productType: 'normal' | 'hook';
};

const props = defineProps<{
    tenant: { id: string; name: string };
    defaults: GondolaStandard;
    system_defaults: GondolaStandard;
}>();

const { t } = useT();

const tenantsIndexPath = TenantController.index.url().replace(/^\/\/[^/]+/, '');

const pageMeta = useCrudPageMeta({
    headTitle: t('app.landlord.tenants.gondola_defaults.title'),
    title: t('app.landlord.tenants.gondola_defaults.title'),
    description: t('app.landlord.tenants.gondola_defaults.description'),
    breadcrumbs: [
        {
            title: t('app.landlord.tenants.navigation'),
            href: tenantsIndexPath,
        },
        {
            title: props.tenant.name,
            href: tenantWayfinderPath(TenantController.edit.url(props.tenant.id)),
        },
        {
            title: t('app.landlord.tenants.gondola_defaults.navigation'),
            href: tenantWayfinderPath(TenantGondolaDefaultsController.edit.url(props.tenant.id)),
        },
    ],
});

const form = useForm<GondolaStandard>({ ...props.defaults });

/** Restaura o formulário para o padrão de gôndola do Plannerate. */
function restoreSystemDefaults(): void {
    Object.assign(form, props.system_defaults);
}

function submit(): void {
    form.put(tenantWayfinderPath(TenantGondolaDefaultsController.update.url(props.tenant.id)), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="pageMeta.headTitle" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="p-4">
            <form @submit.prevent="submit">
                <FormCard
                    :processing="form.processing"
                    :cancel-href="tenantsIndexPath"
                    :title="t('app.landlord.tenants.gondola_defaults.title')"
                    :description="t('app.landlord.tenants.gondola_defaults.description')"
                >
                    <template #icon>
                        <LayoutGrid class="size-5" />
                    </template>

                    <template #header-extra>
                        <Button
                            type="button"
                            variant="outline"
                            size="pill-sm"
                            @click="restoreSystemDefaults"
                        >
                            <RotateCcw class="size-4" />
                            {{ t('app.landlord.tenants.gondola_defaults.restore_default') }}
                        </Button>
                    </template>

                    <!-- Geral -->
                    <section class="grid gap-4">
                        <h3 class="text-sm font-semibold text-foreground">
                            {{ t('app.landlord.tenants.gondola_defaults.sections.general') }}
                        </h3>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="location">{{ t('app.landlord.tenants.gondola_defaults.fields.location') }}</Label>
                                <Input id="location" v-model="form.location" />
                                <InputError :message="form.errors.location" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="side">{{ t('app.landlord.tenants.gondola_defaults.fields.side') }}</Label>
                                <Input id="side" v-model="form.side" />
                                <InputError :message="form.errors.side" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="scaleFactor">{{ t('app.landlord.tenants.gondola_defaults.fields.scale_factor') }}</Label>
                                <Input id="scaleFactor" v-model.number="form.scaleFactor" type="number" min="1" step="1" />
                                <InputError :message="form.errors.scaleFactor" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="flow">{{ t('app.landlord.tenants.gondola_defaults.fields.flow') }}</Label>
                                <select
                                    id="flow"
                                    v-model="form.flow"
                                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                                >
                                    <option value="left_to_right">{{ t('app.landlord.tenants.gondola_defaults.flows.left_to_right') }}</option>
                                    <option value="right_to_left">{{ t('app.landlord.tenants.gondola_defaults.flows.right_to_left') }}</option>
                                </select>
                                <InputError :message="form.errors.flow" />
                            </div>
                        </div>
                    </section>

                    <!-- Módulo -->
                    <section class="grid gap-4">
                        <h3 class="text-sm font-semibold text-foreground">
                            {{ t('app.landlord.tenants.gondola_defaults.sections.module') }}
                        </h3>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="grid gap-2">
                                <Label for="height">{{ t('app.landlord.tenants.gondola_defaults.fields.height') }}</Label>
                                <Input id="height" v-model.number="form.height" type="number" min="1" />
                                <InputError :message="form.errors.height" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="width">{{ t('app.landlord.tenants.gondola_defaults.fields.width') }}</Label>
                                <Input id="width" v-model.number="form.width" type="number" min="1" />
                                <InputError :message="form.errors.width" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="numModules">{{ t('app.landlord.tenants.gondola_defaults.fields.num_modules') }}</Label>
                                <Input id="numModules" v-model.number="form.numModules" type="number" min="1" step="1" />
                                <InputError :message="form.errors.numModules" />
                            </div>
                        </div>
                    </section>

                    <!-- Base -->
                    <section class="grid gap-4">
                        <h3 class="text-sm font-semibold text-foreground">
                            {{ t('app.landlord.tenants.gondola_defaults.sections.base') }}
                        </h3>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="grid gap-2">
                                <Label for="baseHeight">{{ t('app.landlord.tenants.gondola_defaults.fields.base_height') }}</Label>
                                <Input id="baseHeight" v-model.number="form.baseHeight" type="number" min="1" />
                                <InputError :message="form.errors.baseHeight" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="baseWidth">{{ t('app.landlord.tenants.gondola_defaults.fields.base_width') }}</Label>
                                <Input id="baseWidth" v-model.number="form.baseWidth" type="number" min="1" />
                                <InputError :message="form.errors.baseWidth" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="baseDepth">{{ t('app.landlord.tenants.gondola_defaults.fields.base_depth') }}</Label>
                                <Input id="baseDepth" v-model.number="form.baseDepth" type="number" min="1" />
                                <InputError :message="form.errors.baseDepth" />
                            </div>
                        </div>
                    </section>

                    <!-- Cremalheira -->
                    <section class="grid gap-4">
                        <h3 class="text-sm font-semibold text-foreground">
                            {{ t('app.landlord.tenants.gondola_defaults.sections.rack') }}
                        </h3>
                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <div class="grid gap-2">
                                <Label for="rackWidth">{{ t('app.landlord.tenants.gondola_defaults.fields.rack_width') }}</Label>
                                <Input id="rackWidth" v-model.number="form.rackWidth" type="number" min="1" />
                                <InputError :message="form.errors.rackWidth" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="holeHeight">{{ t('app.landlord.tenants.gondola_defaults.fields.hole_height') }}</Label>
                                <Input id="holeHeight" v-model.number="form.holeHeight" type="number" min="1" />
                                <InputError :message="form.errors.holeHeight" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="holeWidth">{{ t('app.landlord.tenants.gondola_defaults.fields.hole_width') }}</Label>
                                <Input id="holeWidth" v-model.number="form.holeWidth" type="number" min="1" />
                                <InputError :message="form.errors.holeWidth" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="holeSpacing">{{ t('app.landlord.tenants.gondola_defaults.fields.hole_spacing') }}</Label>
                                <Input id="holeSpacing" v-model.number="form.holeSpacing" type="number" min="1" />
                                <InputError :message="form.errors.holeSpacing" />
                            </div>
                        </div>
                    </section>

                    <!-- Prateleiras -->
                    <section class="grid gap-4">
                        <h3 class="text-sm font-semibold text-foreground">
                            {{ t('app.landlord.tenants.gondola_defaults.sections.shelves') }}
                        </h3>
                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <div class="grid gap-2">
                                <Label for="shelfHeight">{{ t('app.landlord.tenants.gondola_defaults.fields.shelf_height') }}</Label>
                                <Input id="shelfHeight" v-model.number="form.shelfHeight" type="number" min="1" />
                                <InputError :message="form.errors.shelfHeight" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="shelfWidth">{{ t('app.landlord.tenants.gondola_defaults.fields.shelf_width') }}</Label>
                                <Input id="shelfWidth" v-model.number="form.shelfWidth" type="number" min="1" />
                                <InputError :message="form.errors.shelfWidth" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="shelfDepth">{{ t('app.landlord.tenants.gondola_defaults.fields.shelf_depth') }}</Label>
                                <Input id="shelfDepth" v-model.number="form.shelfDepth" type="number" min="1" />
                                <InputError :message="form.errors.shelfDepth" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="numShelves">{{ t('app.landlord.tenants.gondola_defaults.fields.num_shelves') }}</Label>
                                <Input id="numShelves" v-model.number="form.numShelves" type="number" min="0" step="1" />
                                <InputError :message="form.errors.numShelves" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="productType">{{ t('app.landlord.tenants.gondola_defaults.fields.product_type') }}</Label>
                                <select
                                    id="productType"
                                    v-model="form.productType"
                                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                                >
                                    <option value="normal">{{ t('app.landlord.tenants.gondola_defaults.product_types.normal') }}</option>
                                    <option value="hook">{{ t('app.landlord.tenants.gondola_defaults.product_types.hook') }}</option>
                                </select>
                                <InputError :message="form.errors.productType" />
                            </div>
                        </div>
                    </section>
                </FormCard>
            </form>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Layers, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PlanController from '@/actions/App/Http/Controllers/Landlord/PlanController';
import FormSlugField from '@/components/form/FormSlugField.vue';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';
import PlanItemRow from './PlanItemRow.vue';

type PlanItem = {
    id: string | null;
    key: string;
    label: string;
    value: string;
    type: 'integer' | 'boolean' | 'string';
    sort_order: number;
    is_active: boolean;
    limit_message: string | null;
    upgrade_url: string | null;
};

type PlanPayload = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    price_cents: number;
    user_limit: number | null;
    is_active: boolean;
    items: PlanItem[];
};

type AdministrativeRoleLimit = {
    system_name: string;
    name: string;
    limit: number | null;
};

const props = defineProps<{
    plan: PlanPayload | null;
    administrative_roles: AdministrativeRoleLimit[];
}>();

const { t } = useT();
const isEdit = computed(() => props.plan !== null);
const plansIndexPath = PlanController.index.url().replace(/^\/\/[^/]+/, '');
const name = ref(props.plan?.name ?? '');

const items = ref<PlanItem[]>(
    props.plan?.items?.map((item) => ({ ...item })) ?? [],
);

/**
 * Preço exibido em reais para o admin. O backend armazena em centavos
 * (`price_cents`), então convertemos no envio via input hidden.
 */
const priceReais = ref<string>(
    props.plan ? (props.plan.price_cents / 100).toFixed(2) : '0.00',
);

/**
 * Valor inteiro em centavos derivado do preço em reais, enviado no form.
 */
const priceCents = computed<number>(() => {
    const parsed = Number.parseFloat(priceReais.value.replace(',', '.'));

    return Number.isFinite(parsed) ? Math.round(parsed * 100) : 0;
});

function addItem(): void {
    items.value.push({
        id: null,
        key: '',
        label: '',
        value: '',
        type: 'string',
        sort_order: items.value.length,
        is_active: true,
        limit_message: null,
        upgrade_url: null,
    });
}

function removeItem(index: number): void {
    items.value.splice(index, 1);
}

const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value
        ? t('app.landlord.plans.actions.edit')
        : t('app.landlord.plans.actions.new'),
    title: isEdit.value
        ? t('app.landlord.plans.actions.edit')
        : t('app.landlord.plans.actions.new'),
    description: t('app.landlord.plans.description'),
    breadcrumbs: [
        {
            title: t('app.landlord.plans.navigation'),
            href: plansIndexPath,
        },
        {
            title: isEdit.value
                ? t('app.landlord.common.edit')
                : t('app.landlord.common.create'),
            href: isEdit.value
                ? tenantWayfinderPath(PlanController.edit.url(props.plan!.id))
                : tenantWayfinderPath(PlanController.create.url()),
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
                        ? {
                              ...PlanController.update.form(props.plan!.id),
                              action: tenantWayfinderPath(
                                  PlanController.update.form(props.plan!.id)
                                      .action,
                              ),
                          }
                        : {
                              ...PlanController.store.form(),
                              action: tenantWayfinderPath(
                                  PlanController.store.form().action,
                              ),
                          }
                "
                v-slot="{ errors, processing }"
            >
                <FormCard
                    :processing="processing"
                    :cancel-href="plansIndexPath"
                    :title="pageMeta.title"
                    :description="pageMeta.description"
                >
                    <template #icon>
                        <Layers class="size-5" />
                    </template>

                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Name -->
                        <div class="grid gap-2">
                            <Label for="name">{{
                                t('app.landlord.plans.fields.name')
                            }}</Label>
                            <Input
                                id="name"
                                v-model="name"
                                name="name"
                                required
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <!-- Slug (gerado a partir do nome) -->
                        <FormSlugField
                            :source="name"
                            :default-value="props.plan?.slug ?? ''"
                            :error="errors.slug"
                            required
                        />
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Price (reais) -->
                        <div class="grid gap-2">
                            <Label for="price"
                                >{{
                                    t('app.landlord.plans.fields.price_cents')
                                }}
                                (R$)</Label
                            >
                            <Input
                                id="price"
                                v-model="priceReais"
                                type="number"
                                min="0"
                                step="0.01"
                                required
                            />
                            <input
                                type="hidden"
                                name="price_cents"
                                :value="priceCents"
                            />
                            <InputError :message="errors.price_cents" />
                        </div>

                        <!-- User limit -->
                        <div class="grid gap-2">
                            <Label for="user_limit">{{
                                t('app.landlord.plans.fields.user_limit')
                            }}</Label>
                            <Input
                                id="user_limit"
                                name="user_limit"
                                type="number"
                                min="1"
                                :default-value="props.plan?.user_limit ?? ''"
                                placeholder="Em branco = ilimitado"
                            />
                            <InputError :message="errors.user_limit" />
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="grid gap-2">
                        <Label for="description">{{
                            t('app.landlord.plans.fields.description')
                        }}</Label>
                        <textarea
                            id="description"
                            name="description"
                            rows="3"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground transition outline-none focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                            :value="props.plan?.description ?? ''"
                        ></textarea>
                        <InputError :message="errors.description" />
                    </div>

                    <!-- Active -->
                    <label
                        class="flex cursor-pointer items-center gap-3 rounded-lg border border-border bg-muted/30 px-4 py-3 transition-colors hover:bg-muted/50 has-checked:border-primary/50 has-checked:bg-primary/5"
                    >
                        <input type="hidden" name="is_active" value="0" />
                        <input
                            id="is_active"
                            name="is_active"
                            type="checkbox"
                            value="1"
                            :checked="props.plan?.is_active ?? true"
                            class="accent-primary"
                        />
                        <div>
                            <span class="text-sm font-medium">{{
                                t('app.landlord.plans.fields.is_active')
                            }}</span>
                            <p class="text-xs text-muted-foreground">
                                Planos inativos não aparecem para seleção de
                                novos tenants.
                            </p>
                        </div>
                        <InputError :message="errors.is_active" />
                    </label>

                    <!-- Limites por perfil administrativo -->
                    <div v-if="props.administrative_roles.length > 0" class="space-y-3">
                        <div>
                            <p class="text-sm font-semibold text-foreground">
                                {{ t('app.landlord.plans.role_limits.title') }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ t('app.landlord.plans.role_limits.hint') }}
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div
                                v-for="role in props.administrative_roles"
                                :key="role.system_name"
                                class="grid gap-2"
                            >
                                <Label :for="`role_limit_${role.system_name}`">{{ role.name }}</Label>
                                <Input
                                    :id="`role_limit_${role.system_name}`"
                                    :name="`role_limits[${role.system_name}]`"
                                    type="number"
                                    min="1"
                                    :default-value="role.limit ?? ''"
                                    :placeholder="t('app.landlord.plans.role_limits.unlimited_placeholder')"
                                />
                                <InputError :message="errors[`role_limits.${role.system_name}`]" />
                            </div>
                        </div>
                    </div>

                    <!-- Plan Items -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <p
                                    class="text-sm font-semibold text-foreground"
                                >
                                    Itens do plano
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Features e limites configuráveis para este
                                    plano.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="addItem"
                            >
                                <Plus class="mr-1 size-4" />
                                Adicionar item
                            </Button>
                        </div>

                        <div
                            v-if="items.length === 0"
                            class="flex items-center justify-center rounded-lg border border-dashed border-border px-4 py-8 text-sm text-muted-foreground"
                        >
                            Nenhum item adicionado. Clique em "Adicionar item"
                            para começar.
                        </div>

                        <div
                            v-else
                            class="divide-y divide-border rounded-lg border border-border"
                        >
                            <PlanItemRow
                                v-for="(_, index) in items"
                                :key="index"
                                v-model="items[index]"
                                :index="index"
                                :errors="errors"
                                @remove="removeItem(index)"
                            />
                        </div>
                    </div>
                </FormCard>
            </Form>
        </div>
    </AppLayout>
</template>

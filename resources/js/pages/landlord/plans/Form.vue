<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Layers, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PlanController from '@/actions/App/Http/Controllers/Landlord/PlanController';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';

type PlanItem = {
    id: string | null;
    key: string;
    label: string;
    value: string;
    type: 'integer' | 'boolean' | 'string';
    sort_order: number;
    is_active: boolean;
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

const props = defineProps<{
    plan: PlanPayload | null;
}>();

const { t } = useT();
const isEdit = computed(() => props.plan !== null);
const plansIndexPath = PlanController.index.url().replace(/^\/\/[^/]+/, '');

const items = ref<PlanItem[]>(
    props.plan?.items?.map((item) => ({ ...item })) ?? [],
);

function addItem(): void {
    items.value.push({
        id: null,
        key: '',
        label: '',
        value: '',
        type: 'string',
        sort_order: items.value.length,
        is_active: true,
    });
}

function removeItem(index: number): void {
    items.value.splice(index, 1);
}

const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value ? t('app.landlord.plans.actions.edit') : t('app.landlord.plans.actions.new'),
    title: isEdit.value ? t('app.landlord.plans.actions.edit') : t('app.landlord.plans.actions.new'),
    description: t('app.landlord.plans.description'),
    breadcrumbs: [
        {
            title: t('app.landlord.plans.navigation'),
            href: plansIndexPath,
        },
        {
            title: isEdit.value ? t('app.landlord.common.edit') : t('app.landlord.common.create'),
            href: isEdit.value ? PlanController.edit.url(props.plan!.id) : PlanController.create.url(),
        },
    ],
});
</script>

<template>
    <Head :title="pageMeta.headTitle" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="p-4">
        <Form
            v-bind="isEdit ? PlanController.update.form(props.plan!.id) : PlanController.store.form()"
            v-slot="{ errors, processing }"
        >
            <FormCard
                :processing="processing"
                :cancel-href="plansIndexPath"
            >
                <template #icon>
                    <Layers class="size-5" />
                </template>

                <!-- Name -->
                <div class="grid gap-2">
                    <Label for="name">{{ t('app.landlord.plans.fields.name') }}</Label>
                    <Input id="name" name="name" :default-value="props.plan?.name ?? ''" required />
                    <InputError :message="errors.name" />
                </div>

                <!-- Slug -->
                <div class="grid gap-2">
                    <Label for="slug">Slug</Label>
                    <Input id="slug" name="slug" :default-value="props.plan?.slug ?? ''" required />
                    <InputError :message="errors.slug" />
                </div>

                <!-- Description -->
                <div class="grid gap-2">
                    <Label for="description">{{ t('app.landlord.plans.fields.description') }}</Label>
                    <textarea
                        id="description"
                        name="description"
                        rows="3"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                    >{{ props.plan?.description ?? '' }}</textarea>
                    <InputError :message="errors.description" />
                </div>

                <!-- Price + User limit -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="price_cents">{{ t('app.landlord.plans.fields.price_cents') }}</Label>
                        <Input
                            id="price_cents"
                            name="price_cents"
                            type="number"
                            min="0"
                            :default-value="props.plan?.price_cents ?? 0"
                            required
                        />
                        <InputError :message="errors.price_cents" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="user_limit">{{ t('app.landlord.plans.fields.user_limit') }}</Label>
                        <Input
                            id="user_limit"
                            name="user_limit"
                            type="number"
                            min="1"
                            :default-value="props.plan?.user_limit ?? ''"
                        />
                        <p class="text-xs text-muted-foreground">Deixe em branco para ilimitado.</p>
                        <InputError :message="errors.user_limit" />
                    </div>
                </div>

                <!-- Active -->
                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-border bg-muted/30 px-4 py-3 transition-colors hover:bg-muted/50 has-checked:border-primary/50 has-checked:bg-primary/5">
                    <input type="hidden" name="is_active" value="0" />
                    <input id="is_active" name="is_active" type="checkbox" value="1" :checked="props.plan?.is_active ?? true" class="accent-primary" />
                    <div>
                        <span class="text-sm font-medium">{{ t('app.landlord.plans.fields.is_active') }}</span>
                        <p class="text-xs text-muted-foreground">Planos inativos não aparecem para seleção de novos tenants.</p>
                    </div>
                    <InputError :message="errors.is_active" />
                </label>

                <!-- Plan Items -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-foreground">Itens do plano</p>
                            <p class="text-xs text-muted-foreground">Features e limites configuráveis para este plano.</p>
                        </div>
                        <Button type="button" variant="outline" size="sm" @click="addItem">
                            <Plus class="mr-1 size-4" />
                            Adicionar item
                        </Button>
                    </div>

                    <div
                        v-if="items.length === 0"
                        class="flex items-center justify-center rounded-lg border border-dashed border-border px-4 py-8 text-sm text-muted-foreground"
                    >
                        Nenhum item adicionado. Clique em "Adicionar item" para começar.
                    </div>

                    <div v-else class="divide-y divide-border rounded-lg border border-border">
                        <div
                            v-for="(item, index) in items"
                            :key="index"
                            class="grid grid-cols-[1fr_1fr_1fr_auto_auto] items-end gap-3 p-4"
                        >
                            <!-- Hidden id -->
                            <input
                                v-if="item.id"
                                type="hidden"
                                :name="`items[${index}][id]`"
                                :value="item.id"
                            />

                            <!-- Label -->
                            <div class="grid gap-1">
                                <label :for="`item_label_${index}`" class="text-xs font-medium text-muted-foreground">Label</label>
                                <Input
                                    :id="`item_label_${index}`"
                                    v-model="item.label"
                                    :name="`items[${index}][label]`"
                                    placeholder="Ex: Máximo de usuários"
                                    required
                                />
                            </div>

                            <!-- Key -->
                            <div class="grid gap-1">
                                <label :for="`item_key_${index}`" class="text-xs font-medium text-muted-foreground">Chave</label>
                                <Input
                                    :id="`item_key_${index}`"
                                    v-model="item.key"
                                    :name="`items[${index}][key]`"
                                    placeholder="Ex: user_limit"
                                    required
                                />
                            </div>

                            <!-- Value -->
                            <div class="grid gap-1">
                                <label :for="`item_value_${index}`" class="text-xs font-medium text-muted-foreground">Valor</label>
                                <Input
                                    :id="`item_value_${index}`"
                                    v-model="item.value"
                                    :name="`items[${index}][value]`"
                                    placeholder="Em branco = ilimitado"
                                />
                            </div>

                            <!-- Type -->
                            <div class="grid gap-1">
                                <label :for="`item_type_${index}`" class="text-xs font-medium text-muted-foreground">Tipo</label>
                                <select
                                    :id="`item_type_${index}`"
                                    v-model="item.type"
                                    :name="`items[${index}][type]`"
                                    class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                                >
                                    <option value="string">Texto</option>
                                    <option value="integer">Inteiro</option>
                                    <option value="boolean">Booleano</option>
                                </select>
                            </div>

                            <!-- Remove -->
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                @click="removeItem(index)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </FormCard>
        </Form>
        </div>
    </AppLayout>
</template>

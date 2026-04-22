<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Layers } from 'lucide-vue-next';
import PlanController from '@/actions/App/Http/Controllers/Landlord/PlanController';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';

type PlanPayload = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    price_cents: number;
    user_limit: number | null;
    is_active: boolean;
};

const props = defineProps<{
    plan: PlanPayload | null;
}>();

const { t } = useT();
const isEdit = computed(() => props.plan !== null);
const plansIndexPath = PlanController.index.url().replace(/^\/\/[^/]+/, '');

setLayoutProps({
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
    <Head :title="isEdit ? t('app.landlord.plans.actions.edit') : t('app.landlord.plans.actions.new')" />

    <div class="p-4">
        <Form
            v-bind="isEdit ? PlanController.update.form(props.plan!.id) : PlanController.store.form()"
            v-slot="{ errors, processing }"
        >
            <FormCard
                :title="isEdit ? t('app.landlord.plans.actions.edit') : t('app.landlord.plans.actions.new')"
                :description="t('app.landlord.plans.description')"
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
            </FormCard>
        </Form>
    </div>
</template>

<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { computed } from 'vue';
import PlanController from '@/actions/App/Http/Controllers/Landlord/PlanController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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

    <div class="space-y-6 p-4">
        <Heading
            :title="isEdit ? t('app.landlord.plans.actions.edit') : t('app.landlord.plans.actions.new')"
            :description="t('app.landlord.plans.description')"
        />

        <Form
            v-bind="isEdit ? PlanController.update.form(props.plan!.id) : PlanController.store.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">{{ t('app.landlord.plans.fields.name') }}</Label>
                <Input id="name" name="name" :default-value="props.plan?.name ?? ''" required />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="slug">Slug</Label>
                <Input id="slug" name="slug" :default-value="props.plan?.slug ?? ''" required />
                <InputError :message="errors.slug" />
            </div>

            <div class="grid gap-2">
                <Label for="description">{{ t('app.landlord.plans.fields.description') }}</Label>
                <textarea
                    id="description"
                    name="description"
                    rows="3"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    :default-value="props.plan?.description ?? ''"
                />
                <InputError :message="errors.description" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="price_cents">{{ t('app.landlord.plans.fields.price_cents') }}</Label>
                    <Input id="price_cents" name="price_cents" type="number" min="0" :default-value="props.plan?.price_cents ?? 0" required />
                    <InputError :message="errors.price_cents" />
                </div>

                <div class="grid gap-2">
                    <Label for="user_limit">{{ t('app.landlord.plans.fields.user_limit') }}</Label>
                    <Input id="user_limit" name="user_limit" type="number" min="1" :default-value="props.plan?.user_limit ?? ''" />
                    <InputError :message="errors.user_limit" />
                </div>
            </div>

            <div class="flex items-center gap-3">
                <input type="hidden" name="is_active" value="0" />
                <input id="is_active" name="is_active" type="checkbox" value="1" :checked="props.plan?.is_active ?? true" />
                <Label for="is_active">{{ t('app.landlord.plans.fields.is_active') }}</Label>
                <InputError :message="errors.is_active" />
            </div>

            <div class="flex items-center gap-3">
                <Button :disabled="processing">{{ t('app.actions.save') }}</Button>
                <Button variant="outline" as-child>
                    <Link :href="plansIndexPath">{{ t('app.actions.cancel') }}</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>

<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Blocks } from 'lucide-vue-next';
import ClusterController from '@/actions/App/Http/Controllers/Tenant/ClusterController';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';

type ClusterPayload = {
    id: string;
    store_id: string;
    name: string;
    specification_1: string | null;
    specification_2: string | null;
    specification_3: string | null;
    slug: string | null;
    status: 'draft' | 'published';
    description: string | null;
};

const props = defineProps<{
    subdomain: string;
    cluster: ClusterPayload | null;
    stores: Array<{ id: string; name: string }>;
}>();

const { t } = useT();
const isEdit = computed(() => props.cluster !== null);
const clustersIndexPath = ClusterController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
</script>

<template>
    <Head :title="isEdit ? t('app.tenant.clusters.actions.edit') : t('app.tenant.clusters.actions.new')" />

    <div class="p-4">
        <Form
            v-bind="isEdit
                ? ClusterController.update.form({ subdomain: props.subdomain, cluster: props.cluster!.id })
                : ClusterController.store.form(props.subdomain)"
            v-slot="{ errors, processing }"
        >
            <FormCard
                :title="isEdit ? t('app.tenant.clusters.actions.edit') : t('app.tenant.clusters.actions.new')"
                :description="t('app.tenant.clusters.description')"
                :processing="processing"
                :cancel-href="clustersIndexPath"
            >
                <template #icon>
                    <Blocks class="size-5" />
                </template>

                <div class="grid gap-2">
                    <Label for="store_id">{{ t('app.tenant.clusters.fields.store') }}</Label>
                    <select id="store_id" name="store_id" :value="props.cluster?.store_id ?? ''" class="h-10 rounded-md border border-input bg-background px-3 text-sm" required>
                        <option value="">{{ t('app.tenant.common.all') }}</option>
                        <option v-for="store in props.stores" :key="store.id" :value="store.id">{{ store.name }}</option>
                    </select>
                    <InputError :message="errors.store_id" />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">{{ t('app.tenant.clusters.fields.name') }}</Label>
                        <Input id="name" name="name" :default-value="props.cluster?.name ?? ''" required />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input id="slug" name="slug" :default-value="props.cluster?.slug ?? ''" />
                        <InputError :message="errors.slug" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="specification_1">{{ t('app.tenant.clusters.fields.specification_1') }}</Label>
                        <Input id="specification_1" name="specification_1" :default-value="props.cluster?.specification_1 ?? ''" />
                        <InputError :message="errors.specification_1" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="specification_2">{{ t('app.tenant.clusters.fields.specification_2') }}</Label>
                        <Input id="specification_2" name="specification_2" :default-value="props.cluster?.specification_2 ?? ''" />
                        <InputError :message="errors.specification_2" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="specification_3">{{ t('app.tenant.clusters.fields.specification_3') }}</Label>
                        <Input id="specification_3" name="specification_3" :default-value="props.cluster?.specification_3 ?? ''" />
                        <InputError :message="errors.specification_3" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="status">{{ t('app.tenant.clusters.fields.status') }}</Label>
                        <select id="status" name="status" :value="props.cluster?.status ?? 'draft'" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </select>
                        <InputError :message="errors.status" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">{{ t('app.tenant.clusters.fields.description') }}</Label>
                        <Input id="description" name="description" :default-value="props.cluster?.description ?? ''" />
                        <InputError :message="errors.description" />
                    </div>
                </div>
            </FormCard>
        </Form>
    </div>
</template>

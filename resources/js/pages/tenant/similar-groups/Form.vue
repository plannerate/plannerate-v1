<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Layers } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FormCard from '@/components/FormCard.vue';
import FormStatusField from '@/components/form/FormStatusField.vue';
import FormTextareaField from '@/components/form/FormTextareaField.vue';
import FormTextField from '@/components/form/FormTextField.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

type SimilarGroupPayload = {
    id: string;
    grouper_code: string;
    name: string;
    product_codes: string[];
    status: 'draft' | 'published';
    description: string | null;
};

const props = defineProps<{
    subdomain: string;
    similarGroup: SimilarGroupPayload | null;
}>();

const { t } = useT();
const isEdit = computed(() => props.similarGroup !== null);
const indexPath = `/similar-groups`;
const formAction = computed(() =>
    isEdit.value
        ? { action: `/similar-groups/${props.similarGroup!.id}`, method: 'put' as const }
        : { action: `/similar-groups`, method: 'post' as const },
);

const productCodesText = ref(
    (props.similarGroup?.product_codes ?? []).join('\n'),
);

const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value ? 'Editar Grupo de Similares' : 'Novo Grupo de Similares',
    title: isEdit.value ? 'Editar Grupo de Similares' : 'Novo Grupo de Similares',
    description: 'Agrupe produtos similares por código do agrupador.',
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: 'Grupo de Similares', href: indexPath },
        {
            title: isEdit.value ? 'Editar' : 'Novo',
            href: isEdit.value ? `/similar-groups/${props.similarGroup!.id}/edit` : `/similar-groups/create`,
        },
    ],
});
</script>

<template>
    <Head :title="pageMeta.headTitle" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="p-4">
            <Form v-bind="formAction" v-slot="{ errors, processing }">
                <FormCard
                    :processing="processing"
                    :cancel-href="indexPath"
                    :title="pageMeta.title"
                    :description="pageMeta.description"
                >
                    <template #icon>
                        <Layers class="size-5" />
                    </template>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                        <FormTextField
                            id="grouper_code"
                            name="grouper_code"
                            label="Código do Agrupador"
                            :default-value="props.similarGroup?.grouper_code ?? ''"
                            :error="errors.grouper_code"
                            class="md:col-span-4"
                            required
                        />

                        <FormTextField
                            id="name"
                            name="name"
                            label="Nome do Grupo"
                            :default-value="props.similarGroup?.name ?? ''"
                            :error="errors.name"
                            class="md:col-span-8"
                            required
                        />

                        <div class="md:col-span-12">
                            <label class="mb-1 block text-sm font-medium text-foreground" for="product_codes_text">
                                Códigos dos Produtos
                                <span class="ml-1 text-xs font-normal text-muted-foreground">(um código por linha)</span>
                            </label>
                            <textarea
                                id="product_codes_text"
                                v-model="productCodesText"
                                class="min-h-32 w-full rounded-lg border border-border bg-background px-3 py-2 font-mono text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                                placeholder="EAN001&#10;EAN002&#10;EAN003"
                                rows="6"
                            />
                            <template v-for="(code, index) in productCodesText.split('\n').map(s => s.trim()).filter(Boolean)" :key="index">
                                <input type="hidden" :name="`product_codes[${index}]`" :value="code" />
                            </template>
                            <p v-if="errors['product_codes']" class="mt-1 text-xs text-destructive">
                                {{ errors['product_codes'] }}
                            </p>
                        </div>

                        <FormStatusField
                            id="status"
                            name="status"
                            label="Status"
                            :default-value="props.similarGroup?.status ?? 'draft'"
                            :error="errors.status"
                            class="md:col-span-12"
                            :options="[
                                { value: 'draft', label: 'Rascunho' },
                                { value: 'published', label: 'Publicado' },
                            ]"
                        />

                        <FormTextareaField
                            id="description"
                            name="description"
                            label="Descrição"
                            :default-value="props.similarGroup?.description ?? ''"
                            :error="errors.description"
                            class="md:col-span-12"
                            :rows="2"
                        />
                    </div>
                </FormCard>
            </Form>
        </div>
    </AppLayout>
</template>

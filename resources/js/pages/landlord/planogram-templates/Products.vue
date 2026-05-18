<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import GlobalPlanogramTemplateController from '@/actions/App/Http/Controllers/Landlord/GlobalPlanogramTemplateController';
import ProductSearchPanel from '@/components/planogram-templates/ProductSearchPanel.vue';
import TemplateProductTable from '@/components/planogram-templates/TemplateProductTable.vue';
import WizardProgress from '@/components/planogram-templates/WizardProgress.vue';
import type {
    GroupingOption,
    PlanogramTemplateProduct,
    ProductSearchResult,
    WizardStep,
} from '@/components/planogram-templates/types';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

type TemplateBasic = {
    id: string;
    code: string;
    name: string;
    department: string;
    is_active: boolean;
};

const props = defineProps<{
    template: TemplateBasic;
    products: PlanogramTemplateProduct[];
    availableGroupings: string[];
    groupingOptions: GroupingOption[];
    selectedGroupingId?: string | null;
    searchResults?: ProductSearchResult[];
}>();

const { t } = useT();

// ── URL helpers ────────────────────────────────────────────────────────────────
const baseUrl = computed(() =>
    GlobalPlanogramTemplateController.show
        .url(props.template.id)
        .replace(/^\/\/[^/]+/, ''),
);
const indexPath = GlobalPlanogramTemplateController.index.url().replace(/^\/\/[^/]+/, '');
const editPath = computed(() =>
    GlobalPlanogramTemplateController.edit.url(props.template.id).replace(/^\/\/[^/]+/, ''),
);
const slotsPath = computed(() => `${baseUrl.value}/slots`);

// ── Wizard ─────────────────────────────────────────────────────────────────────
const wizardSteps: WizardStep[] = [
    { step: 1, label: t('planogram-templates.wizard.step1_label'), description: t('planogram-templates.wizard.step1_description') },
    { step: 2, label: t('planogram-templates.wizard.step2_label'), description: t('planogram-templates.wizard.step2_description') },
    { step: 3, label: t('planogram-templates.wizard.step3_label'), description: t('planogram-templates.wizard.step3_description') },
];

function navigateWizard(step: 1 | 2 | 3): void {
    if (step === 1) router.visit(editPath.value);
    if (step === 2) router.visit(slotsPath.value);
}

// ── Search ─────────────────────────────────────────────────────────────────────
const searching = ref(false);

function doSearch(groupingId: string | null): void {
    searching.value = true;
    router.get(
        `${baseUrl.value}/products`,
        { groupingId: groupingId ?? undefined },
        {
            preserveState: true,
            only: ['searchResults'],
            onFinish: () => { searching.value = false; },
        },
    );
}

// ── Product operations ─────────────────────────────────────────────────────────
function addProducts(items: Array<{ product: ProductSearchResult; grouping: string }>): void {
    router.post(
        `${baseUrl.value}/products`,
        { items: items.map((i) => ({
            ean: i.product.ean,
            grouping: i.grouping,
            sortiment_attribute: i.product.sortiment_attribute ?? null,
        })) },
        { preserveState: true, only: ['products'] },
    );
}

function updateGrouping(product: PlanogramTemplateProduct, grouping: string): void {
    router.put(
        `${baseUrl.value}/products/${product.id}`,
        { grouping },
        { preserveState: true, only: ['products'] },
    );
}

function removeProduct(product: PlanogramTemplateProduct): void {
    if (!confirm('Remover este produto do template?')) return;
    router.delete(`${baseUrl.value}/products/${product.id}`, {
        preserveState: true,
        only: ['products'],
    });
}

function importBulk(file: File): void {
    const formData = new FormData();
    formData.append('file', file);
    router.post(`${baseUrl.value}/products/bulk`, formData, {
        only: ['products'],
    });
}

function downloadTemplate(): void {
    window.location.href = `${baseUrl.value}/products/template`;
}

// ── Breadcrumbs ────────────────────────────────────────────────────────────────
const breadcrumbs = [
    { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
    { title: t('app.landlord.planogram_templates.navigation'), href: indexPath },
    { title: props.template.code, href: editPath.value },
    { title: 'Produtos', href: '#' },
];
</script>

<template>
    <Head :title="`Produtos — ${template.code}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <!-- Wizard progress -->
            <div class="mx-auto max-w-3xl">
                <WizardProgress :current-step="3" :steps="wizardSteps" @navigate="navigateWizard" />
            </div>

            <!-- Header -->
            <div>
                <h1 class="text-xl font-semibold">{{ template.name }}</h1>
                <p class="text-sm text-muted-foreground">
                    Etapa 3 — adicione produtos ao mix do template
                </p>
            </div>

            <!-- Two-panel layout -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-[22rem_1fr]">
                <!-- Left: search -->
                <div class="rounded-xl border border-border bg-card p-4">
                    <h2 class="mb-3 text-sm font-semibold">Busca manual</h2>
                    <ProductSearchPanel
                        :search-results="searchResults ?? []"
                        :searching="searching"
                        :grouping-options="groupingOptions"
                        :selected-grouping-id="selectedGroupingId"
                        @search="doSearch"
                        @add-products="addProducts"
                    />
                </div>

                <!-- Right: products table -->
                <div class="rounded-xl border border-border bg-card p-4">
                    <h2 class="mb-3 text-sm font-semibold">Produtos do template</h2>
                    <TemplateProductTable
                        :products="products"
                        :available-groupings="availableGroupings"
                        @update-grouping="updateGrouping"
                        @remove-product="removeProduct"
                        @import-xlsx="importBulk"
                        @download-template="downloadTemplate"
                    />
                </div>
            </div>

            <!-- Navigation -->
            <div class="flex justify-between pt-2">
                <Button variant="outline" :as="'a'" :href="slotsPath">
                    <ChevronLeft class="size-4" />
                    Voltar — Slots
                </Button>
                <Button variant="outline" :as="'a'" :href="indexPath">
                    Finalizar e sair
                </Button>
            </div>
        </div>
    </AppLayout>
</template>

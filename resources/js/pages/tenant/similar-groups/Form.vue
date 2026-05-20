<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    CircleHelp,
    Layers,
    PackageSearch,
    Plus,
    Ruler,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import SimilarGroupController, {
    productSearch,
} from '@/actions/App/Http/Controllers/Tenant/SimilarGroupController';
import FormDecimalField from '@/components/form/FormDecimalField.vue';
import FormStatusToggleField from '@/components/form/FormStatusToggleField.vue';
import FormTextareaField from '@/components/form/FormTextareaField.vue';
import FormTextField from '@/components/form/FormTextField.vue';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';

type ProductOption = {
    id: string;
    name: string;
    ean: string | null;
    codigo_erp: string | null;
    brand: string | null;
    dimensions: {
        width: string | number | null;
        height: string | number | null;
        depth: string | number | null;
        weight: string | number | null;
        unit: string | null;
        dimension_status: 'draft' | 'published' | null;
    };
};

type SimilarGroupPayload = {
    id: string;
    grouper_code: string;
    name: string;
    product_codes: string[];
    base_dimensions_product_ean: string | null;
    selected_products: ProductOption[];
    dimensions: ProductOption['dimensions'];
    status: 'draft' | 'published';
    description: string | null;
};

const props = defineProps<{
    subdomain: string;
    similarGroup: SimilarGroupPayload | null;
    productOptions: ProductOption[];
    suggestedGrouperCode: string;
}>();

const { t } = useT();
const isEdit = computed(() => props.similarGroup !== null);
const selectedProducts = ref<ProductOption[]>([
    ...(props.similarGroup?.selected_products ?? []),
]);
const productResults = ref<ProductOption[]>([...props.productOptions]);
const productQuery = ref('');
const productSearchLoading = ref(false);
const applyDimensions = ref(true);
const grouperCode = ref(
    props.similarGroup?.grouper_code ?? props.suggestedGrouperCode,
);
const selectedDimensionProductId = ref<string | null>(
    resolveInitialDimensionSourceProductId(),
);
const width = ref(toInputValue(props.similarGroup?.dimensions?.width));
const height = ref(toInputValue(props.similarGroup?.dimensions?.height));
const depth = ref(toInputValue(props.similarGroup?.dimensions?.depth));
const weight = ref(toInputValue(props.similarGroup?.dimensions?.weight));
const unit = ref(props.similarGroup?.dimensions?.unit ?? 'cm');
const dimensionStatus = ref(
    props.similarGroup?.dimensions?.dimension_status ?? 'published',
);

const indexPath = tenantWayfinderPath(
    SimilarGroupController.index.url(props.subdomain),
);
const createPath = tenantWayfinderPath(
    SimilarGroupController.create.url(props.subdomain),
);
const editPath = computed(() =>
    isEdit.value
        ? tenantWayfinderPath(
              SimilarGroupController.edit.url({
                  subdomain: props.subdomain,
                  similar_group: props.similarGroup!.id,
              }),
          )
        : createPath,
);

const formAction = computed(() => {
    const definition = isEdit.value
        ? SimilarGroupController.update.form({
              subdomain: props.subdomain,
              similar_group: props.similarGroup!.id,
          })
        : SimilarGroupController.store.form(props.subdomain);

    return { ...definition, action: tenantWayfinderPath(definition.action) };
});

const selectedProductIds = computed(
    () => new Set(selectedProducts.value.map((product) => product.id)),
);
const filteredResults = computed(() =>
    productResults.value.filter(
        (product) => !selectedProductIds.value.has(product.id),
    ),
);

const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value
        ? 'Editar Grupo de Similares'
        : 'Novo Grupo de Similares',
    title: isEdit.value
        ? 'Editar Grupo de Similares'
        : 'Novo Grupo de Similares',
    description:
        'Monte famílias de produtos equivalentes e normalize as dimensões em lote.',
    breadcrumbs: [
        {
            title: t('app.navigation.dashboard'),
            href: dashboard.url().replace(/^\/\/[^/]+/, ''),
        },
        { title: 'Grupo de Similares', href: indexPath },
        {
            title: isEdit.value ? 'Editar' : 'Novo',
            href: editPath.value,
        },
    ],
});

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

watch(productQuery, (query) => {
    if (searchTimeout !== null) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        void searchProducts(query);
    }, 250);
});

function toInputValue(value: string | number | null | undefined): string {
    return value === null || value === undefined ? '' : String(value);
}

function resolveInitialDimensionSourceProductId(): string | null {
    const baseEan = props.similarGroup?.base_dimensions_product_ean;

    if (!baseEan) {
        return null;
    }

    return (
        selectedProducts.value.find((product) => product.ean === baseEan)?.id ??
        null
    );
}

async function searchProducts(query: string): Promise<void> {
    productSearchLoading.value = true;

    try {
        const url = tenantWayfinderPath(
            productSearch.url(props.subdomain, { query: { search: query } }),
        );
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            return;
        }

        const payload = (await response.json()) as {
            products?: ProductOption[];
        };
        productResults.value = payload.products ?? [];
    } finally {
        productSearchLoading.value = false;
    }
}

function addProduct(product: ProductOption): void {
    if (selectedProductIds.value.has(product.id)) {
        return;
    }

    selectedProducts.value = [...selectedProducts.value, product];

    if (selectedProducts.value.length === 1) {
        width.value = toInputValue(product.dimensions.width);
        height.value = toInputValue(product.dimensions.height);
        depth.value = toInputValue(product.dimensions.depth);
        weight.value = toInputValue(product.dimensions.weight);
        unit.value = product.dimensions.unit ?? 'cm';
        dimensionStatus.value =
            product.dimensions.dimension_status ?? 'published';
    }
}

function removeProduct(productId: string): void {
    selectedProducts.value = selectedProducts.value.filter(
        (product) => product.id !== productId,
    );

    if (selectedDimensionProductId.value === productId) {
        selectedDimensionProductId.value = null;
    }
}

function productCode(product: ProductOption): string {
    return product.ean || product.codigo_erp || 'sem codigo';
}

function dimensionsLabel(product: ProductOption): string {
    const dimensions = [
        product.dimensions.width,
        product.dimensions.height,
        product.dimensions.depth,
    ]
        .filter((value) => value !== null && value !== '')
        .join(' x ');

    return dimensions
        ? `${dimensions} ${product.dimensions.unit ?? 'cm'}`
        : 'sem dimensoes';
}

function useProductDimensions(product: ProductOption): void {
    selectedDimensionProductId.value = product.id;
    width.value = toInputValue(product.dimensions.width);
    height.value = toInputValue(product.dimensions.height);
    depth.value = toInputValue(product.dimensions.depth);
    weight.value = toInputValue(product.dimensions.weight);
    unit.value = product.dimensions.unit ?? 'cm';
    dimensionStatus.value = product.dimensions.dimension_status ?? 'published';
    applyDimensions.value = true;
}

function useSuggestedGrouperCode(): void {
    grouperCode.value = props.suggestedGrouperCode;
}

function clearDimensionSource(): void {
    selectedDimensionProductId.value = null;
}
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

                    <div class="grid grid-cols-1 gap-6">
                        <section class="grid grid-cols-1 gap-4 md:grid-cols-12">
                            <FormTextField
                                id="grouper_code"
                                v-model="grouperCode"
                                name="grouper_code"
                                label="Código do Agrupador"
                                :error="errors.grouper_code"
                                class="md:col-span-4"
                                required
                            >
                                <template #help>
                                    <div
                                        class="flex items-center justify-between gap-2 text-xs"
                                    >
                                        <span class="text-muted-foreground"
                                            >Sugestão única:
                                            {{ suggestedGrouperCode }}</span
                                        >
                                        <button
                                            type="button"
                                            class="font-medium text-primary hover:underline"
                                            @click="useSuggestedGrouperCode"
                                        >
                                            Usar
                                        </button>
                                    </div>
                                </template>
                            </FormTextField>

                            <FormTextField
                                id="name"
                                name="name"
                                label="Nome do Grupo de Similares"
                                :default-value="props.similarGroup?.name ?? ''"
                                :error="errors.name"
                                class="md:col-span-8"
                                required
                            />

                            <FormStatusToggleField
                                id="status"
                                name="status"
                                label="Status"
                                :default-value="
                                    props.similarGroup?.status ?? 'draft'
                                "
                                :error="errors.status"
                                class="md:col-span-12"
                                checked-label="Publicado"
                                unchecked-label="Rascunho"
                            />

                            <FormTextareaField
                                id="description"
                                name="description"
                                label="Descrição"
                                :default-value="
                                    props.similarGroup?.description ?? ''
                                "
                                :error="errors.description"
                                class="md:col-span-12"
                                :rows="2"
                            />
                        </section>

                        <section
                            class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(360px,520px)]"
                        >
                            <div
                                class="rounded-lg border border-border bg-muted/20 p-4"
                            >
                                <div class="mb-3 flex items-center gap-2">
                                    <PackageSearch
                                        class="size-4 text-muted-foreground"
                                    />
                                    <h2
                                        class="text-sm font-semibold text-foreground"
                                    >
                                       Produtos do grupo de similares
                                    </h2>
                                </div>

                                <label
                                    class="mb-1 block text-sm font-medium text-foreground"
                                    for="product_search"
                                >
                                    Buscar produto
                                </label>
                                <div class="relative">
                                    <Search
                                        class="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground"
                                    />
                                    <input
                                        id="product_search"
                                        v-model="productQuery"
                                        type="search"
                                        class="h-10 w-full rounded-lg border border-input bg-background pr-3 pl-9 text-sm text-foreground transition outline-none placeholder:text-muted-foreground focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                                        placeholder="Nome, EAN, ERP ou marca"
                                    />
                                </div>

                                <div
                                    class="mt-3 overflow-hidden rounded-lg border border-border bg-background"
                                >
                                    <div
                                        v-if="productSearchLoading"
                                        class="px-4 py-3 text-sm text-muted-foreground"
                                    >
                                        Buscando produtos...
                                    </div>
                                    <div
                                        v-else-if="filteredResults.length === 0"
                                        class="px-4 py-3 text-sm text-muted-foreground"
                                    >
                                        Nenhum produto disponível para
                                        adicionar.
                                    </div>
                                    <template v-else>
                                        <button
                                            v-for="product in filteredResults"
                                            :key="product.id"
                                            type="button"
                                            class="flex w-full items-center justify-between gap-3 border-t border-border px-4 py-3 text-left first:border-t-0 hover:bg-muted/40"
                                            @click="addProduct(product)"
                                        >
                                            <span class="min-w-0">
                                                <span
                                                    class="block truncate text-sm font-medium text-foreground"
                                                    >{{ product.name }}</span
                                                >
                                                <span
                                                    class="block truncate text-xs text-muted-foreground"
                                                >
                                                    {{ productCode(product) }} ·
                                                    {{
                                                        product.brand ??
                                                        'sem marca'
                                                    }}
                                                    ·
                                                    {{
                                                        dimensionsLabel(product)
                                                    }}
                                                </span>
                                            </span>
                                            <Plus
                                                class="size-4 shrink-0 text-primary"
                                            />
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <div
                                class="rounded-lg border border-border bg-background p-4"
                            >
                                <div
                                    class="mb-3 flex items-center justify-between gap-3"
                                >
                                    <div class="flex items-center gap-2">
                                        <Layers
                                            class="size-4 text-muted-foreground"
                                        />
                                        <h2
                                            class="text-sm font-semibold text-foreground"
                                        >
                                            Selecionados
                                        </h2>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button
                                            v-if="
                                                selectedDimensionProductId !==
                                                null
                                            "
                                            type="button"
                                            class="text-xs font-medium text-muted-foreground hover:text-foreground"
                                            @click="clearDimensionSource"
                                        >
                                            Limpar referência
                                        </button>
                                        <span
                                            class="rounded-full bg-muted px-2 py-1 text-xs text-muted-foreground"
                                        >
                                            {{ selectedProducts.length }}
                                            produto(s)
                                        </span>
                                    </div>
                                </div>

                                <template
                                    v-for="(product, index) in selectedProducts"
                                    :key="product.id"
                                >
                                    <input
                                        type="hidden"
                                        :name="`product_ids[${index}]`"
                                        :value="product.id"
                                    />
                                </template>

                                <InputError :message="errors.product_ids" />

                                <div
                                    class="overflow-hidden rounded-lg border border-border"
                                >
                                    <table class="w-full text-sm">
                                        <thead
                                            class="bg-muted/40 text-left text-muted-foreground"
                                        >
                                            <tr>
                                                <th
                                                    class="w-12 px-3 py-2 font-medium"
                                                >
                                                    <TooltipProvider
                                                        :delay-duration="150"
                                                    >
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                as-child
                                                            >
                                                                <span
                                                                    class="inline-flex cursor-help items-center gap-1"
                                                                >
                                                                    Usar
                                                                    <CircleHelp
                                                                        class="size-3.5"
                                                                    />
                                                                </span>
                                                            </TooltipTrigger>
                                                            <TooltipContent
                                                                side="top"
                                                                class="max-w-60 text-xs"
                                                            >
                                                                Escolha um
                                                                produto como
                                                                referência para
                                                                preencher a
                                                                dimensão padrão
                                                                do grupo de
                                                                similares. É
                                                                opcional e você
                                                                ainda pode
                                                                editar os campos
                                                                abaixo.
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                </th>
                                                <th
                                                    class="px-3 py-2 font-medium"
                                                >
                                                    Produto
                                                </th>
                                                <th
                                                    class="px-3 py-2 font-medium"
                                                >
                                                    Dimensão atual
                                                </th>
                                                <th class="w-10 px-2 py-2" />
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-if="
                                                    selectedProducts.length ===
                                                    0
                                                "
                                            >
                                                <td
                                                    class="px-3 py-6 text-muted-foreground"
                                                    colspan="4"
                                                >
                                                    Escolha ao menos dois
                                                    produtos que pertençam ao
                                                    mesmo grupo de similares.
                                                </td>
                                            </tr>
                                            <tr
                                                v-for="product in selectedProducts"
                                                v-else
                                                :key="product.id"
                                                class="border-t border-border"
                                            >
                                                <td class="px-3 py-2">
                                                    <input
                                                        :id="`dimension-source-${product.id}`"
                                                        type="radio"
                                                        name="dimension_source_product_id"
                                                        :value="product.id"
                                                        :checked="
                                                            selectedDimensionProductId ===
                                                            product.id
                                                        "
                                                        class="size-4 border-input text-primary focus:ring-primary/20"
                                                        :aria-label="`Usar dimensões de ${product.name}`"
                                                        @change="
                                                            useProductDimensions(
                                                                product,
                                                            )
                                                        "
                                                    />
                                                </td>
                                                <td class="min-w-0 px-3 py-2">
                                                    <span
                                                        class="block truncate font-medium text-foreground"
                                                        >{{
                                                            product.name
                                                        }}</span
                                                    >
                                                    <span
                                                        class="block truncate text-xs text-muted-foreground"
                                                        >{{
                                                            productCode(product)
                                                        }}</span
                                                    >
                                                </td>
                                                <td
                                                    class="px-3 py-2 text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        dimensionsLabel(product)
                                                    }}
                                                </td>
                                                <td
                                                    class="px-2 py-2 text-right"
                                                >
                                                    <button
                                                        type="button"
                                                        class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                        :aria-label="`Remover ${product.name}`"
                                                        @click="
                                                            removeProduct(
                                                                product.id,
                                                            )
                                                        "
                                                    >
                                                        <Trash2
                                                            class="size-4"
                                                        />
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>

                        <section
                            class="rounded-lg border border-border bg-background p-4"
                        >
                            <div
                                class="mb-3 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                            >
                                <div class="flex items-center gap-2">
                                    <Ruler
                                        class="size-4 text-muted-foreground"
                                    />
                                    <div>
                                        <h2
                                            class="text-sm font-semibold text-foreground"
                                        >
                                            Dimensão padrão do grupo de similares
                                        </h2>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Escolha uma referência na lista ou
                                            edite manualmente antes de salvar.
                                        </p>
                                    </div>
                                </div>
                                <label
                                    class="inline-flex cursor-pointer items-center gap-2 text-sm text-foreground"
                                >
                                    <Checkbox
                                        :checked="applyDimensions"
                                        @update:model-value="
                                            (checked) =>
                                                (applyDimensions =
                                                    checked === true)
                                        "
                                    />
                                    Aplicar ao salvar
                                </label>
                                <input
                                    v-if="applyDimensions"
                                    type="hidden"
                                    name="apply_dimensions"
                                    value="1"
                                />
                            </div>

                            <div
                                class="grid grid-cols-2 gap-3 lg:grid-cols-[repeat(4,minmax(120px,1fr))_120px_160px]"
                            >
                                <FormDecimalField
                                    id="height"
                                    v-model="height"
                                    name="height"
                                    label="Altura (cm)"
                                    :error="errors.height"
                                    :disabled="!applyDimensions"
                                />
                                <FormDecimalField
                                    id="width"
                                    v-model="width"
                                    name="width"
                                    label="Largura (cm)"
                                    :error="errors.width"
                                    :disabled="!applyDimensions"
                                />
                                <FormDecimalField
                                    id="depth"
                                    v-model="depth"
                                    name="depth"
                                    label="Profundidade (cm)"
                                    :error="errors.depth"
                                    :disabled="!applyDimensions"
                                />
                                <FormDecimalField
                                    id="weight"
                                    v-model="weight"
                                    name="weight"
                                    label="Peso (g)"
                                    :error="errors.weight"
                                    :disabled="!applyDimensions"
                                />
                                <FormTextField
                                    id="unit"
                                    v-model="unit"
                                    name="unit"
                                    label="Unidade"
                                    :error="errors.unit"
                                    :disabled="!applyDimensions"
                                />
                                <FormStatusToggleField
                                    id="dimension_status"
                                    v-model="dimensionStatus"
                                    name="dimension_status"
                                    label="Publicado"
                                    :error="errors.dimension_status"
                                    :disabled="!applyDimensions"
                                    checked-label="Sim"
                                    unchecked-label="Não"
                                />
                            </div>
                        </section>
                    </div>
                </FormCard>
            </Form>
        </div>
    </AppLayout>
</template>

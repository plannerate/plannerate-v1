<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import {
    AlertTriangle,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    ExternalLink,
    Loader2,
    RefreshCcw,
    Search,
    ThumbsDown,
    ThumbsUp,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import DimensionApprovalController from '@/actions/App/Http/Controllers/Tenant/Products/DimensionApprovalController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';
import type { Paginator } from '@/types';

type DimensionStatus = 'pending' | 'researching' | 'awaiting_approval' | 'approved' | 'not_found' | 'rejected';

type ProductRow = {
    id: string;
    name: string | null;
    ean: string | null;
    brand: string | null;
    category: string | null;
    category_id: string | null;
    dimensions: {
        width: number | null;
        height: number | null;
        depth: number | null;
        weight: number | null;
        unit: string | null;
    };
    dimension_status: DimensionStatus | null;
    dimension_status_label: string | null;
    dimension_status_color: string | null;
    dimension_source: string | null;
    dimension_source_url: string | null;
    dimension_confidence: 'high' | 'medium' | 'low' | null;
    dimension_reasoning: string | null;
    dimension_warnings: string[];
    dimension_researched_at: string | null;
    similar_to_product_id: string | null;
};

type StatusOption = {
    value: string;
    label: string;
    color: string;
};

const props = defineProps<{
    products: Paginator<ProductRow>;
    filters: {
        dimension_status: string;
        category_id: string;
        dimension_source: string;
        dimension_confidence: string;
    };
    statuses: StatusOption[];
}>();

const page = usePage();
const isEchoConfigured = typeof window !== 'undefined' && window.__plannerateEchoConfigured === true;

const tenantId = computed(() => {
    const tenant = (page.props.tenant ?? null) as { id?: string } | null;

    return typeof tenant?.id === 'string' && tenant.id !== '' ? tenant.id : null;
});

// Real-time updates: refresh list when a product dimension is researched
if (isEchoConfigured && tenantId.value) {
    useEcho(`tenant.${tenantId.value}.dimensions`, '.ProductDimensionResearched', () => {
        router.reload({ only: ['products'], preserveScroll: true });
    });
}

const indexPath = tenantWayfinderPath(DimensionApprovalController.index.url());

// Filters state
const searchStatus = ref(props.filters.dimension_status);
const searchSource = ref(props.filters.dimension_source);
const searchConfidence = ref(props.filters.dimension_confidence);

watch([searchStatus, searchSource, searchConfidence], () => {
    router.get(
        indexPath,
        {
            dimension_status: searchStatus.value || undefined,
            dimension_source: searchSource.value || undefined,
            dimension_confidence: searchConfidence.value || undefined,
            category_id: props.filters.category_id || undefined,
        },
        { preserveScroll: true, replace: true },
    );
});

// Per-product expanded reasoning panel
const expandedIds = ref<Set<string>>(new Set());

function toggleExpanded(id: string): void {
    if (expandedIds.value.has(id)) {
        expandedIds.value.delete(id);
    } else {
        expandedIds.value.add(id);
    }
}

// Approve single product
const approvingId = ref<string | null>(null);

function approve(product: ProductRow): void {
    if (approvingId.value) {
        return;
    }

    approvingId.value = product.id;
    router.post(
        tenantWayfinderPath(DimensionApprovalController.approve.url({ product: product.id })),
        {},
        {
            preserveScroll: true,
            onFinish: () => { approvingId.value = null; },
        },
    );
}

// Reject modal
const rejectDialogOpen = ref(false);
const rejectTarget = ref<ProductRow | null>(null);
const rejectReason = ref('');
const rejectingId = ref<string | null>(null);

function openRejectDialog(product: ProductRow): void {
    rejectTarget.value = product;
    rejectReason.value = '';
    rejectDialogOpen.value = true;
}

function confirmReject(): void {
    if (!rejectTarget.value || !rejectReason.value.trim() || rejectingId.value) {
        return;
    }

    rejectingId.value = rejectTarget.value.id;
    router.post(
        tenantWayfinderPath(DimensionApprovalController.reject.url({ product: rejectTarget.value.id })),
        { reason: rejectReason.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                rejectDialogOpen.value = false;
                rejectTarget.value = null;
            },
            onFinish: () => { rejectingId.value = null; },
        },
    );
}

// Re-research
const researchingId = ref<string | null>(null);

function reResearch(product: ProductRow): void {
    if (researchingId.value) {
        return;
    }

    researchingId.value = product.id;
    router.post(
        tenantWayfinderPath(DimensionApprovalController.research.url({ product: product.id })),
        {},
        {
            preserveScroll: true,
            onFinish: () => { researchingId.value = null; },
        },
    );
}

// Batch approve all high-confidence products
const batchApproving = ref(false);
const selectedIds = ref<Set<string>>(new Set());

const highConfidenceProducts = computed(() =>
    props.products.data.filter(
        (p) => p.dimension_confidence === 'high' && p.dimension_status === 'awaiting_approval',
    ),
);

function toggleSelectAll(): void {
    if (selectedIds.value.size === highConfidenceProducts.value.length) {
        selectedIds.value = new Set();
    } else {
        selectedIds.value = new Set(highConfidenceProducts.value.map((p) => p.id));
    }
}

function approveAll(): void {
    const ids = Array.from(selectedIds.value);

    if (ids.length === 0 || batchApproving.value) {
        return;
    }

    batchApproving.value = true;
    router.post(
        tenantWayfinderPath(DimensionApprovalController.approveAll.url()),
        { product_ids: ids },
        {
            preserveScroll: true,
            onSuccess: () => { selectedIds.value = new Set(); },
            onFinish: () => { batchApproving.value = false; },
        },
    );
}

// Confidence badge styling
function confidenceClass(confidence: string | null): string {
    if (confidence === 'high') {
        return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
    }

    if (confidence === 'medium') {
        return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
    }

    return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
}

// Status badge styling — use server-provided color hint
function statusBadgeClass(color: string | null): string {
    const map: Record<string, string> = {
        gray: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        yellow: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        green: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        orange: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
        red: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    };

    return map[color ?? ''] ?? map.gray;
}

function formatDimensions(d: ProductRow['dimensions']): string {
    const parts = [d.width, d.height, d.depth]
        .filter((v): v is number => v !== null)
        .map(String);

    if (parts.length === 0) {
        return '—';
    }

    return `${parts.join(' × ')}${d.unit ? ` ${d.unit}` : ''}${d.weight !== null ? ` | ${d.weight} kg` : ''}`;
}

const pageMeta = useCrudPageMeta({
    headTitle: 'Aprovação de Dimensões',
    title: 'Aprovação de Dimensões',
    description: 'Revise e aprove as dimensões pesquisadas por IA antes de publicar.',
    breadcrumbs: [
        { title: 'Dashboard', href: tenantWayfinderPath(dashboard.url()) },
        { title: 'Aprovação de Dimensões', href: indexPath },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />

        <!-- Batch approval bar -->
        <template v-if="highConfidenceProducts.length > 0" #header-actions>
            <div class="flex items-center gap-2">
                <span class="text-sm text-muted-foreground">
                    {{ selectedIds.size }} / {{ highConfidenceProducts.length }} selecionados
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    @click="toggleSelectAll"
                >
                    {{ selectedIds.size === highConfidenceProducts.length ? 'Desmarcar todos' : 'Selecionar todos (alta)' }}
                </Button>
                <Button
                    v-if="selectedIds.size > 0"
                    size="sm"
                    :disabled="batchApproving"
                    @click="approveAll"
                >
                    <Loader2 v-if="batchApproving" class="mr-1.5 size-4 animate-spin" />
                    <CheckCircle2 v-else class="mr-1.5 size-4" />
                    Aprovar {{ selectedIds.size }} em lote
                </Button>
            </div>
        </template>

        <div class="flex flex-col gap-4 p-4">

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-2">
                <Search class="size-4 text-muted-foreground" />

                <select
                    v-model="searchStatus"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                >
                    <option value="">Todos os status</option>
                    <option v-for="s in statuses" :key="s.value" :value="s.value">
                        {{ s.label }}
                    </option>
                </select>

                <select
                    v-model="searchSource"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                >
                    <option value="">Todas as fontes</option>
                    <option value="local_similarity">Similaridade local</option>
                    <option value="cosmos">Cosmos/Bluesoft</option>
                    <option value="web_search">Busca web</option>
                </select>

                <select
                    v-model="searchConfidence"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                >
                    <option value="">Todas as confiabilidades</option>
                    <option value="high">Alta confiabilidade</option>
                    <option value="medium">Média confiabilidade</option>
                    <option value="low">Baixa confiabilidade</option>
                </select>

                <button
                    v-if="searchStatus || searchSource || searchConfidence"
                    type="button"
                    class="flex h-9 items-center gap-1 rounded-lg border border-border px-3 text-sm text-muted-foreground hover:text-foreground"
                    @click="searchStatus = ''; searchSource = ''; searchConfidence = ''"
                >
                    <X class="size-3.5" /> Limpar
                </button>
            </div>

            <!-- Empty state -->
            <div
                v-if="products.data.length === 0"
                class="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-border bg-card py-20 text-center"
            >
                <CheckCircle2 class="size-12 text-muted-foreground/30" />
                <p class="text-sm font-medium text-muted-foreground">Nenhum produto encontrado para os filtros selecionados.</p>
            </div>

            <!-- Product cards -->
            <div v-else class="flex flex-col gap-3">
                <div
                    v-for="product in products.data"
                    :key="product.id"
                    class="overflow-hidden rounded-lg border border-border bg-card"
                >
                    <div class="flex items-start gap-4 p-4">

                        <!-- Batch checkbox (only for high-confidence awaiting_approval) -->
                        <div class="flex items-center pt-0.5">
                            <input
                                v-if="product.dimension_confidence === 'high' && product.dimension_status === 'awaiting_approval'"
                                type="checkbox"
                                :checked="selectedIds.has(product.id)"
                                class="size-4 rounded border-border text-primary"
                                @change="selectedIds.has(product.id) ? selectedIds.delete(product.id) : selectedIds.add(product.id)"
                            />
                            <div v-else class="size-4" />
                        </div>

                        <!-- Main content -->
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <!-- Status badge -->
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="statusBadgeClass(product.dimension_status_color)"
                                >
                                    {{ product.dimension_status_label ?? product.dimension_status }}
                                </span>

                                <!-- Confidence badge -->
                                <span
                                    v-if="product.dimension_confidence"
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="confidenceClass(product.dimension_confidence)"
                                >
                                    {{
                                        product.dimension_confidence === 'high'
                                            ? 'Alta'
                                            : product.dimension_confidence === 'medium'
                                              ? 'Média'
                                              : 'Baixa'
                                    }} confiabilidade
                                </span>

                                <!-- Source badge -->
                                <span
                                    v-if="product.dimension_source"
                                    class="inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                                >
                                    {{
                                        product.dimension_source === 'local_similarity'
                                            ? 'Similaridade local'
                                            : product.dimension_source === 'cosmos'
                                              ? 'Cosmos/Bluesoft'
                                              : product.dimension_source === 'web_search'
                                                ? 'Busca web'
                                                : product.dimension_source
                                    }}
                                </span>
                            </div>

                            <div class="mt-2 flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                                <h3 class="text-sm font-semibold text-foreground">
                                    {{ product.name ?? '(sem nome)' }}
                                </h3>
                                <span v-if="product.ean" class="font-mono text-xs text-muted-foreground">
                                    EAN {{ product.ean }}
                                </span>
                                <span v-if="product.brand" class="text-xs text-muted-foreground">
                                    {{ product.brand }}
                                </span>
                                <span v-if="product.category" class="text-xs text-muted-foreground">
                                    {{ product.category }}
                                </span>
                            </div>

                            <!-- Dimensions -->
                            <p class="mt-1.5 font-mono text-sm text-foreground">
                                {{ formatDimensions(product.dimensions) }}
                            </p>

                            <!-- Source URL -->
                            <a
                                v-if="product.dimension_source_url"
                                :href="product.dimension_source_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-1 inline-flex items-center gap-1 text-xs text-primary hover:underline"
                            >
                                <ExternalLink class="size-3" />
                                Ver fonte
                            </a>

                            <!-- Similar product reference link -->
                            <p
                                v-if="product.dimension_source === 'local_similarity' && product.similar_to_product_id"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                Baseado no produto:
                                <span class="font-mono">{{ product.similar_to_product_id }}</span>
                            </p>

                            <!-- Warnings -->
                            <div v-if="product.dimension_warnings.length > 0" class="mt-2 flex flex-col gap-1">
                                <div
                                    v-for="(warning, wi) in product.dimension_warnings"
                                    :key="wi"
                                    class="flex items-start gap-1.5 text-xs text-yellow-700 dark:text-yellow-400"
                                >
                                    <AlertTriangle class="mt-px size-3.5 shrink-0" />
                                    <span>{{ warning }}</span>
                                </div>
                            </div>

                            <!-- Reasoning (expandable) -->
                            <div v-if="product.dimension_reasoning" class="mt-2">
                                <button
                                    type="button"
                                    class="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                                    @click="toggleExpanded(product.id)"
                                >
                                    <component
                                        :is="expandedIds.has(product.id) ? ChevronUp : ChevronDown"
                                        class="size-3.5"
                                    />
                                    Raciocínio da IA
                                </button>
                                <p
                                    v-if="expandedIds.has(product.id)"
                                    class="mt-1.5 rounded-md bg-muted/50 p-3 text-xs leading-relaxed text-muted-foreground"
                                >
                                    {{ product.dimension_reasoning }}
                                </p>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex shrink-0 items-center gap-2">
                            <button
                                type="button"
                                :disabled="researchingId === product.id"
                                class="flex h-8 items-center gap-1.5 rounded-lg border border-border px-3 text-xs text-muted-foreground transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                @click="reResearch(product)"
                            >
                                <Loader2
                                    v-if="researchingId === product.id"
                                    class="size-3.5 animate-spin"
                                />
                                <RefreshCcw v-else class="size-3.5" />
                                Repesquisar
                            </button>

                            <button
                                type="button"
                                class="flex h-8 items-center gap-1.5 rounded-lg border border-border px-3 text-xs text-destructive transition hover:bg-destructive/10 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="openRejectDialog(product)"
                            >
                                <ThumbsDown class="size-3.5" />
                                Rejeitar
                            </button>

                            <button
                                type="button"
                                :disabled="approvingId === product.id"
                                class="flex h-8 items-center gap-1.5 rounded-lg bg-primary px-3 text-xs text-primary-foreground transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="approve(product)"
                            >
                                <Loader2
                                    v-if="approvingId === product.id"
                                    class="size-3.5 animate-spin"
                                />
                                <ThumbsUp v-else class="size-3.5" />
                                Aprovar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div
                v-if="products.last_page > 1"
                class="flex items-center justify-between text-sm text-muted-foreground"
            >
                <span>
                    Mostrando {{ products.from ?? 0 }}–{{ products.to ?? 0 }} de {{ products.total }} produtos
                </span>
                <div class="flex items-center gap-1">
                    <a
                        v-if="products.prev_page_url"
                        :href="tenantWayfinderPath(products.prev_page_url)"
                        class="flex h-8 items-center rounded-md border border-border px-3 hover:bg-muted"
                    >
                        Anterior
                    </a>
                    <span class="px-3">
                        Página {{ products.current_page }} / {{ products.last_page }}
                    </span>
                    <a
                        v-if="products.next_page_url"
                        :href="tenantWayfinderPath(products.next_page_url)"
                        class="flex h-8 items-center rounded-md border border-border px-3 hover:bg-muted"
                    >
                        Próxima
                    </a>
                </div>
            </div>
        </div>

        <!-- Reject Dialog -->
        <Dialog v-model:open="rejectDialogOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Rejeitar dimensões</DialogTitle>
                    <DialogDescription>
                        Informe o motivo da rejeição. O produto voltará ao status "Reprovado".
                    </DialogDescription>
                </DialogHeader>

                <div class="py-2">
                    <p v-if="rejectTarget" class="mb-3 text-sm font-medium text-foreground">
                        {{ rejectTarget.name ?? rejectTarget.ean }}
                    </p>
                    <Textarea
                        v-model="rejectReason"
                        placeholder="Ex: Dimensões muito discrepantes do produto real. Verificar planograma manual."
                        rows="4"
                        maxlength="500"
                        class="resize-none"
                    />
                    <p class="mt-1 text-right text-xs text-muted-foreground">
                        {{ rejectReason.length }}/500
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="!!rejectingId"
                        @click="rejectDialogOpen = false"
                    >
                        Cancelar
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="!rejectReason.trim() || !!rejectingId"
                        @click="confirmReject"
                    >
                        <Loader2 v-if="rejectingId" class="mr-1.5 size-4 animate-spin" />
                        Confirmar rejeição
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

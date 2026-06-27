<script setup lang="ts">
import { AlertTriangle, Camera, Loader2, Store } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Separator } from '@/components/ui/separator';
import { useT } from '@/composables/useT';

/**
 * Aba "Execução" do painel do produto: mostra o retorno da loja (divergências
 * e evidências do tipo Produto) registrado para o produto em todas as gôndolas
 * do planograma. Busca read-only via API, no padrão do resumo de vendas.
 */
interface ExecutionDivergence {
    id: string;
    type: string | null;
    module_label: string | null;
    shelf_label: string | null;
    position_label: string | null;
    status: string | null;
    notes: string | null;
    gondola_name: string | null;
    created_at: string | null;
}

interface ExecutionEvidence {
    id: string;
    file_url: string | null;
    notes: string | null;
    gondola_name: string | null;
    created_at: string | null;
}

const props = defineProps<{
    productId: string | null;
    gondolaId: string | null;
}>();

const { t } = useT();

const isLoading = ref(false);
const error = ref<string | null>(null);
const divergences = ref<ExecutionDivergence[]>([]);
const evidences = ref<ExecutionEvidence[]>([]);

/** Cor do badge conforme o estado da divergência. */
function statusClass(status: string | null): string {
    switch (status) {
        case 'resolvida':
            return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300';
        case 'justificada':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300';
        case 'rejeitada':
            return 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
        case 'em_analise':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300';
        default:
            return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
    }
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString('pt-BR') : '—';
}

function locationLabel(divergence: ExecutionDivergence): string {
    return [divergence.module_label, divergence.shelf_label, divergence.position_label]
        .filter(Boolean)
        .join(' / ');
}

/** Carrega o retorno da loja do produto via API. */
async function load(): Promise<void> {
    if (!props.productId || !props.gondolaId) {
        divergences.value = [];
        evidences.value = [];
        return;
    }

    isLoading.value = true;
    error.value = null;

    try {
        const response = await fetch(
            `/api/plannerate/products/${props.productId}/execution-feedback?gondola_id=${props.gondolaId}`,
        );
        if (!response.ok) {
            throw new Error('failed');
        }
        const data = await response.json();
        divergences.value = data.divergences ?? [];
        evidences.value = data.evidences ?? [];
    } catch {
        error.value = t('plannerate.sidebar.product_execution.load_error');
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => [props.productId, props.gondolaId] as const,
    () => load(),
    { immediate: true },
);
</script>

<template>
    <div class="w-full space-y-4">
        <div>
            <h4 class="flex items-center gap-2 text-sm font-medium text-foreground">
                <Store class="size-4 text-muted-foreground" />
                {{ t('plannerate.sidebar.product_execution.title') }}
            </h4>
            <p class="mt-1 text-xs text-muted-foreground">
                {{ t('plannerate.sidebar.product_execution.subtitle') }}
            </p>
        </div>

        <Separator />

        <div v-if="isLoading" class="flex items-center justify-center py-8">
            <Loader2 class="size-6 animate-spin text-muted-foreground" />
        </div>

        <div v-else-if="error" class="rounded-lg border border-destructive/50 bg-destructive/10 p-4">
            <p class="text-sm text-destructive">{{ error }}</p>
        </div>

        <div v-else-if="!divergences.length && !evidences.length" class="rounded-lg border bg-muted/50 p-6 text-center">
            <Store class="mx-auto size-10 text-muted-foreground" />
            <p class="mt-2 text-sm font-medium text-foreground">
                {{ t('plannerate.sidebar.product_execution.empty_title') }}
            </p>
            <p class="text-xs text-muted-foreground">
                {{ t('plannerate.sidebar.product_execution.empty_description') }}
            </p>
        </div>

        <template v-else>
            <!-- Divergências -->
            <div class="space-y-2">
                <h5 class="flex items-center gap-1.5 text-xs font-semibold text-foreground">
                    <AlertTriangle class="size-3.5 text-red-500" />
                    {{ t('plannerate.sidebar.product_execution.divergences', { count: String(divergences.length) }) }}
                </h5>
                <div
                    v-for="divergence in divergences"
                    :key="divergence.id"
                    class="space-y-1 rounded-lg border p-2.5 text-xs"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium text-foreground">
                            {{ t(`plannerate.execution.divergence.types.${divergence.type}`) }}
                        </span>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="statusClass(divergence.status)">
                            {{ t(`plannerate.execution.divergence.status.${divergence.status}`) }}
                        </span>
                    </div>
                    <p v-if="locationLabel(divergence)" class="text-muted-foreground">{{ locationLabel(divergence) }}</p>
                    <p v-if="divergence.notes" class="text-muted-foreground">{{ divergence.notes }}</p>
                    <div class="flex items-center justify-between text-[10px] text-muted-foreground">
                        <span>{{ divergence.gondola_name ?? '—' }}</span>
                        <span>{{ formatDate(divergence.created_at) }}</span>
                    </div>
                </div>
            </div>

            <!-- Evidências do produto -->
            <div v-if="evidences.length" class="space-y-2">
                <h5 class="flex items-center gap-1.5 text-xs font-semibold text-foreground">
                    <Camera class="size-3.5 text-emerald-500" />
                    {{ t('plannerate.sidebar.product_execution.evidences', { count: String(evidences.length) }) }}
                </h5>
                <div class="grid grid-cols-3 gap-2">
                    <a
                        v-for="evidence in evidences"
                        :key="evidence.id"
                        :href="evidence.file_url ?? '#'"
                        target="_blank"
                        rel="noopener"
                        class="group relative block"
                        :title="evidence.notes ?? ''"
                    >
                        <img
                            v-if="evidence.file_url"
                            :src="evidence.file_url"
                            :alt="evidence.notes ?? ''"
                            class="aspect-square w-full rounded-lg object-cover ring-1 ring-border"
                        />
                        <span class="absolute bottom-1 left-1 rounded bg-black/60 px-1 text-[9px] text-white">
                            {{ evidence.gondola_name ?? '' }}
                        </span>
                    </a>
                </div>
            </div>
        </template>
    </div>
</template>

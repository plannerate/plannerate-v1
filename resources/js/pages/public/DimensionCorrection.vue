<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check, Loader2, PackageCheck, Ruler } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';
import { useT } from '@/composables/useT';

type MissingProduct = {
    id: string;
    ean: string | null;
    name: string | null;
    codigo_erp: string | null;
    width: string | number | null;
    height: string | number | null;
    depth: string | number | null;
};

const props = defineProps<{
    code: string;
    tenantName: string | null;
    categoryLabel: string | null;
    products: MissingProduct[];
    nextCursor: string | null;
    totalRemaining: number;
}>();

const { t } = useT();

type RowForm = {
    height: string;
    width: string;
    depth: string;
    confirming: boolean;
    saving: boolean;
    saved: boolean;
    error: string;
};

const rows = ref<MissingProduct[]>([...props.products]);
const forms = reactive<Record<string, RowForm>>({});
const cursor = ref<string | null>(props.nextCursor);
const remaining = ref<number>(props.totalRemaining);
const loadingMore = ref(false);

// Converte um valor de dimensão vindo do backend em string do input; medidas
// inválidas (null/0) viram vazio para o colaborador preencher.
function dimToString(value: string | number | null): string {
    const parsed = typeof value === 'string' ? Number.parseFloat(value) : value;

    return typeof parsed === 'number' && Number.isFinite(parsed) && parsed > 0 ? String(parsed) : '';
}

function ensureForm(row: MissingProduct): void {
    if (!forms[row.id]) {
        forms[row.id] = {
            height: dimToString(row.height),
            width: dimToString(row.width),
            depth: dimToString(row.depth),
            confirming: false,
            saving: false,
            saved: false,
            error: '',
        };
    }
}

rows.value.forEach((row) => ensureForm(row));

// Carregamento incremental: cada visita parcial devolve o próximo lote (cursor por id),
// que anexamos à lista — sem "drift" quando itens saem ao serem salvos.
watch(
    () => props.products,
    (incoming) => {
        const existing = new Set(rows.value.map((row) => row.id));
        incoming.forEach((row) => {
            if (!existing.has(row.id)) {
                ensureForm(row);
                rows.value.push(row);
            }
        });
        cursor.value = props.nextCursor;
    },
);

function normalize(value: string): string {
    return value.replace(',', '.').trim();
}

function isValid(form: RowForm): boolean {
    return [form.height, form.width, form.depth].every((value) => {
        const parsed = Number.parseFloat(normalize(value));

        return Number.isFinite(parsed) && parsed > 0;
    });
}

function displayValue(value: string): string {
    return normalize(value);
}

// Passo de confirmação: mostra as medidas digitadas antes de gravar de fato.
function requestConfirm(id: string): void {
    const form = forms[id];

    if (!form || !isValid(form)) {
        return;
    }

    form.error = '';
    form.confirming = true;
}

function cancelConfirm(id: string): void {
    const form = forms[id];

    if (form) {
        form.confirming = false;
    }
}

function xsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Salva um card via fetch nativo (exceção permitida): é uma requisição isolada,
 * sem navegação, e precisa ser segura para salvamentos concorrentes de vários cards.
 */
async function save(id: string): Promise<void> {
    const form = forms[id];

    if (!form || form.saving || !isValid(form)) {
        return;
    }

    form.saving = true;
    form.error = '';

    try {
        const response = await fetch(`/dimensoes/${props.code}/produtos/${id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                height: normalize(form.height),
                width: normalize(form.width),
                depth: normalize(form.depth),
            }),
        });

        if (!response.ok) {
            form.error = t('app.public_dimensions.error_generic');

            return;
        }

        form.saved = true;
        remaining.value = Math.max(0, remaining.value - 1);

        // Confirmação visual antes de remover o card da lista.
        window.setTimeout(() => {
            rows.value = rows.value.filter((row) => row.id !== id);
            delete forms[id];

            if (rows.value.length === 0 && cursor.value) {
                loadMore();
            }
        }, 900);
    } catch {
        form.error = t('app.public_dimensions.error_generic');
    } finally {
        form.saving = false;
    }
}

function loadMore(): void {
    if (loadingMore.value || !cursor.value) {
        return;
    }

    loadingMore.value = true;

    router.reload({
        only: ['products', 'nextCursor', 'totalRemaining'],
        data: { after: cursor.value },
        onFinish: () => {
            loadingMore.value = false;
        },
    });
}
</script>

<template>
    <Head :title="t('app.public_dimensions.title')" />

    <div class="min-h-screen bg-muted/30 text-foreground">
        <!-- Cabeçalho fixo -->
        <header class="sticky top-0 z-10 border-b border-border bg-background/95 backdrop-blur">
            <div class="mx-auto flex max-w-xl items-center gap-3 px-4 py-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <Ruler class="size-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-sm font-semibold">{{ t('app.public_dimensions.title') }}</h1>
                    <p class="truncate text-xs text-muted-foreground">
                        <span v-if="tenantName">{{ tenantName }}</span>
                        <span v-if="categoryLabel"> · {{ t('app.public_dimensions.category', { name: categoryLabel }) }}</span>
                    </p>
                </div>
                <span class="shrink-0 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                    {{ t('app.public_dimensions.remaining', { count: String(remaining) }) }}
                </span>
            </div>
        </header>

        <main class="mx-auto max-w-xl px-4 py-4">
            <p class="mb-4 text-sm text-muted-foreground">{{ t('app.public_dimensions.subtitle') }}</p>

            <!-- Estado concluído -->
            <div v-if="rows.length === 0 && !cursor"
                class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border bg-background px-6 py-14 text-center">
                <PackageCheck class="size-10 text-green-600" />
                <p class="text-sm font-medium">{{ t('app.public_dimensions.empty_done') }}</p>
            </div>

            <ul v-else class="grid gap-3">
                <li v-for="row in rows" :key="row.id"
                    class="rounded-2xl border border-border bg-background p-4 shadow-sm transition"
                    :class="forms[row.id]?.saved ? 'border-green-500/60 bg-green-50 dark:bg-green-900/20' : ''">
                    <!-- Identificação do produto -->
                    <div class="mb-3 flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-mono text-base font-semibold tracking-tight">{{ row.ean ?? '—' }}</p>
                            <p class="mt-0.5 line-clamp-2 text-sm text-muted-foreground">{{ row.name ?? '—' }}</p>
                            <p v-if="row.codigo_erp" class="mt-0.5 text-xs text-muted-foreground/70">
                                {{ row.codigo_erp }}
                            </p>
                        </div>
                        <span v-if="forms[row.id]?.saved"
                            class="flex shrink-0 items-center gap-1 rounded-full bg-green-600 px-2 py-1 text-xs font-semibold text-white">
                            <Check class="size-3.5" />
                            {{ t('app.public_dimensions.saved') }}
                        </span>
                    </div>

                    <!-- Estado salvo: medidas confirmadas em verde -->
                    <template v-if="forms[row.id]?.saved">
                        <div class="grid grid-cols-3 gap-2">
                            <div class="rounded-xl border border-green-500/50 bg-green-100/60 p-2 text-center dark:bg-green-900/30">
                                <p class="text-[11px] text-muted-foreground">{{ t('app.public_dimensions.height') }}</p>
                                <p class="text-lg font-semibold tabular-nums text-green-700 dark:text-green-300">{{ displayValue(forms[row.id].height) }}</p>
                            </div>
                            <div class="rounded-xl border border-green-500/50 bg-green-100/60 p-2 text-center dark:bg-green-900/30">
                                <p class="text-[11px] text-muted-foreground">{{ t('app.public_dimensions.width') }}</p>
                                <p class="text-lg font-semibold tabular-nums text-green-700 dark:text-green-300">{{ displayValue(forms[row.id].width) }}</p>
                            </div>
                            <div class="rounded-xl border border-green-500/50 bg-green-100/60 p-2 text-center dark:bg-green-900/30">
                                <p class="text-[11px] text-muted-foreground">{{ t('app.public_dimensions.depth') }}</p>
                                <p class="text-lg font-semibold tabular-nums text-green-700 dark:text-green-300">{{ displayValue(forms[row.id].depth) }}</p>
                            </div>
                        </div>
                    </template>

                    <!-- Estado de confirmação: revisão clara das medidas informadas -->
                    <template v-else-if="forms[row.id]?.confirming">
                        <p class="mb-2 text-sm font-medium">{{ t('app.public_dimensions.review_title') }}</p>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="rounded-xl border border-primary/40 bg-primary/5 p-2 text-center">
                                <p class="text-[11px] text-muted-foreground">{{ t('app.public_dimensions.height') }}</p>
                                <p class="text-lg font-semibold tabular-nums text-primary">{{ displayValue(forms[row.id].height) }}</p>
                            </div>
                            <div class="rounded-xl border border-primary/40 bg-primary/5 p-2 text-center">
                                <p class="text-[11px] text-muted-foreground">{{ t('app.public_dimensions.width') }}</p>
                                <p class="text-lg font-semibold tabular-nums text-primary">{{ displayValue(forms[row.id].width) }}</p>
                            </div>
                            <div class="rounded-xl border border-primary/40 bg-primary/5 p-2 text-center">
                                <p class="text-[11px] text-muted-foreground">{{ t('app.public_dimensions.depth') }}</p>
                                <p class="text-lg font-semibold tabular-nums text-primary">{{ displayValue(forms[row.id].depth) }}</p>
                            </div>
                        </div>

                        <p v-if="forms[row.id]?.error" class="mt-2 text-sm text-destructive">{{ forms[row.id].error }}</p>

                        <div class="mt-3 grid grid-cols-[1fr_auto] gap-2">
                            <button type="button" :disabled="forms[row.id]?.saving"
                                class="flex h-12 items-center justify-center gap-2 rounded-xl bg-green-600 text-sm font-semibold text-white transition hover:bg-green-700 disabled:opacity-50"
                                @click="save(row.id)">
                                <Loader2 v-if="forms[row.id]?.saving" class="size-4 animate-spin" />
                                <Check v-else class="size-4" />
                                {{ forms[row.id]?.saving ? t('app.public_dimensions.saving') : t('app.public_dimensions.confirm') }}
                            </button>
                            <button type="button" :disabled="forms[row.id]?.saving"
                                class="flex h-12 items-center justify-center rounded-xl border border-border bg-background px-4 text-sm font-medium transition hover:bg-muted disabled:opacity-50"
                                @click="cancelConfirm(row.id)">
                                {{ t('app.public_dimensions.edit') }}
                            </button>
                        </div>
                    </template>

                    <!-- Estado de edição: campos de dimensão -->
                    <template v-else>
                        <div class="grid grid-cols-3 gap-2">
                            <label class="grid gap-1">
                                <span class="text-[11px] font-medium text-muted-foreground">{{ t('app.public_dimensions.height') }}</span>
                                <input v-model="forms[row.id].height" type="text" inputmode="decimal"
                                    class="h-12 w-full rounded-xl border border-border bg-background px-3 text-center text-base tabular-nums outline-none focus:border-primary/60 focus:ring-2 focus:ring-primary/20" />
                            </label>
                            <label class="grid gap-1">
                                <span class="text-[11px] font-medium text-muted-foreground">{{ t('app.public_dimensions.width') }}</span>
                                <input v-model="forms[row.id].width" type="text" inputmode="decimal"
                                    class="h-12 w-full rounded-xl border border-border bg-background px-3 text-center text-base tabular-nums outline-none focus:border-primary/60 focus:ring-2 focus:ring-primary/20" />
                            </label>
                            <label class="grid gap-1">
                                <span class="text-[11px] font-medium text-muted-foreground">{{ t('app.public_dimensions.depth') }}</span>
                                <input v-model="forms[row.id].depth" type="text" inputmode="decimal"
                                    class="h-12 w-full rounded-xl border border-border bg-background px-3 text-center text-base tabular-nums outline-none focus:border-primary/60 focus:ring-2 focus:ring-primary/20" />
                            </label>
                        </div>

                        <button type="button" :disabled="!isValid(forms[row.id])"
                            class="mt-3 flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary text-sm font-semibold text-primary-foreground transition hover:bg-primary/90 disabled:opacity-50"
                            @click="requestConfirm(row.id)">
                            {{ t('app.public_dimensions.save') }}
                        </button>
                    </template>
                </li>
            </ul>

            <!-- Carregar mais -->
            <button v-if="cursor" type="button" :disabled="loadingMore"
                class="mt-4 flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-border bg-background text-sm font-medium transition hover:bg-muted disabled:opacity-50"
                @click="loadMore">
                <Loader2 v-if="loadingMore" class="size-4 animate-spin" />
                {{ t('app.public_dimensions.load_more') }}
            </button>
        </main>
    </div>
</template>

<script setup lang="ts">
/**
 * Tela de revisão de uma proposta de reotimização.
 *
 * Mostra SÓ o que muda — uma gôndola com 300 produtos e 4 mudanças rende 4 linhas. O usuário
 * revisa mudanças, não inventário.
 *
 * A aprovação é tudo-ou-nada por decisão de projeto: o motor calculou cada posição assumindo que
 * todas as outras mudanças aconteceriam. Aplicar metade produziria sobreposições e buracos.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
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
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

type ChangeType =
    | 'added'
    | 'removed'
    | 'facings_increased'
    | 'facings_decreased'
    | 'moved'
    | 'stacking_changed'
    | 'rejected_added'
    | 'rejected_resolved';

interface Position {
    module: number;
    shelf: number;
}

interface DiffEntry {
    product_id: string;
    product_name: string;
    ean: string | null;
    image_url: string | null;
    changes: ChangeType[];
    facings_before: number | null;
    facings_after: number | null;
    position_before: Position | null;
    position_after: Position | null;
    rejection_reason: string | null;
}

interface Diff {
    entries: DiffEntry[];
    summary: Record<string, number>;
    has_changes: boolean;
}

const props = defineProps<{
    proposal: {
        id: string;
        status: 'pending' | 'applied' | 'rejected' | 'no_changes' | 'superseded' | 'failed';
        status_label: string;
        trigger: string | null;
        diff: Diff | null;
        sales_period_start: string | null;
        sales_period_end: string | null;
        occupancy_before: number | null;
        occupancy_after: number | null;
        rejection_reason: string | null;
        error_message: string | null;
        reviewed_at: string | null;
        reviewer_name: string | null;
        created_at: string | null;
    };
    gondola: { id: string | null; name: string | null; planogram_id: string | null };
    editorUrl: string;
}>();

const { t } = useT();

const entries = computed<DiffEntry[]>(() => props.proposal.diff?.entries ?? []);
const summary = computed<Record<string, number>>(() => props.proposal.diff?.summary ?? {});
const isPending = computed(() => props.proposal.status === 'pending');

/**
 * Só os tipos de mudança que de fato ocorreram viram filtro — um filtro que sempre devolve
 * zero é ruído.
 */
const CHANGE_TYPES: ChangeType[] = [
    'added',
    'removed',
    'facings_increased',
    'facings_decreased',
    'moved',
    'stacking_changed',
    'rejected_added',
    'rejected_resolved',
];

const activeFilters = ref<ChangeType[]>([]);

const availableFilters = computed(() => CHANGE_TYPES.filter((type) => (summary.value[type] ?? 0) > 0));

const visibleEntries = computed(() => {
    if (activeFilters.value.length === 0) {
        return entries.value;
    }

    return entries.value.filter((entry) => entry.changes.some((change) => activeFilters.value.includes(change)));
});

function toggleFilter(type: ChangeType): void {
    activeFilters.value = activeFilters.value.includes(type)
        ? activeFilters.value.filter((item) => item !== type)
        : [...activeFilters.value, type];
}

/** Mudanças que pioram a exposição do produto ganham destaque visual. */
const NEGATIVE_CHANGES: ChangeType[] = ['removed', 'facings_decreased', 'rejected_added'];

function changeVariant(type: ChangeType): 'default' | 'secondary' | 'destructive' {
    return NEGATIVE_CHANGES.includes(type) ? 'destructive' : 'default';
}

function formatPosition(position: Position | null): string {
    if (!position) {
        return t('plannerate.reoptimization.proposal.table.absent');
    }

    return t('plannerate.reoptimization.proposal.table.module_shelf', {
        module: String(position.module),
        shelf: String(position.shelf),
    });
}

function formatFacings(value: number | null): string {
    return value === null ? t('plannerate.reoptimization.proposal.table.absent') : String(value);
}

function formatOccupancy(value: number | null): string {
    return value === null ? '—' : `${Math.round(value * 100)}%`;
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString('pt-BR') : '—';
}

const approveOpen = ref(false);
const rejectOpen = ref(false);
const rejectReason = ref('');
const submitting = ref(false);

function approve(): void {
    submitting.value = true;

    router.post(
        `/api/reoptimization/${props.proposal.id}/approve`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                submitting.value = false;
                approveOpen.value = false;
            },
        },
    );
}

function reject(): void {
    submitting.value = true;

    router.post(
        `/api/reoptimization/${props.proposal.id}/reject`,
        { reason: rejectReason.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                rejectOpen.value = false;
                rejectReason.value = '';
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}

const breadcrumbs = computed(() => [
    { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
    { title: props.gondola.name ?? '', href: props.editorUrl },
    { title: t('plannerate.reoptimization.proposal.title'), href: '' },
]);
</script>

<template>
    <Head :title="t('plannerate.reoptimization.proposal.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6 pb-24">
            <!-- Cabeçalho -->
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-1">
                    <h1 class="text-xl font-semibold">{{ t('plannerate.reoptimization.proposal.title') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ gondola.name }} · {{ t('plannerate.reoptimization.proposal.subtitle') }}
                    </p>
                    <div class="flex items-center gap-2 pt-1">
                        <Badge :variant="isPending ? 'secondary' : 'default'">{{ proposal.status_label }}</Badge>
                        <span v-if="proposal.reviewed_at && proposal.reviewer_name" class="text-xs text-muted-foreground">
                            {{
                                t('plannerate.reoptimization.proposal.reviewed_by', {
                                    name: proposal.reviewer_name,
                                    date: formatDate(proposal.reviewed_at),
                                })
                            }}
                        </span>
                    </div>
                </div>

                <Button as-child variant="outline" size="sm">
                    <Link :href="editorUrl" class="gap-2">
                        <ArrowLeft class="size-4" />
                        {{ t('plannerate.reoptimization.proposal.back_to_editor') }}
                    </Link>
                </Button>
            </div>

            <!-- Falha na análise -->
            <div
                v-if="proposal.status === 'failed'"
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm dark:border-red-900 dark:bg-red-950/40"
            >
                <p class="font-medium text-red-800 dark:text-red-200">
                    {{ t('plannerate.reoptimization.proposal.error') }}
                </p>
                <p v-if="proposal.error_message" class="mt-1 text-red-700 dark:text-red-300">
                    {{ proposal.error_message }}
                </p>
            </div>

            <!-- Sem mudanças a sugerir -->
            <div
                v-else-if="proposal.status === 'no_changes' || entries.length === 0"
                class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted-foreground"
            >
                {{ t('plannerate.reoptimization.proposal.no_changes') }}
            </div>

            <template v-else>
                <!-- Contexto da análise -->
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg border border-border bg-background p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">
                            {{ t('plannerate.reoptimization.proposal.summary.total') }}
                        </p>
                        <p class="mt-1 text-2xl font-bold">{{ summary.total_changed ?? 0 }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-background p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">
                            {{ t('plannerate.reoptimization.proposal.summary.unchanged') }}
                        </p>
                        <p class="mt-1 text-2xl font-bold">{{ summary.unchanged ?? 0 }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-background p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">
                            {{ t('plannerate.reoptimization.proposal.occupancy') }}
                        </p>
                        <p class="mt-1 flex items-center gap-2 text-2xl font-bold">
                            <span class="text-muted-foreground">{{ formatOccupancy(proposal.occupancy_before) }}</span>
                            <ArrowRight class="size-4 text-muted-foreground" />
                            <span>{{ formatOccupancy(proposal.occupancy_after) }}</span>
                        </p>
                    </div>
                    <div class="rounded-lg border border-border bg-background p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">
                            {{ t('plannerate.reoptimization.proposal.sales_period') }}
                        </p>
                        <p class="mt-1 text-sm font-medium">
                            {{ formatDate(proposal.sales_period_start) }} — {{ formatDate(proposal.sales_period_end) }}
                        </p>
                    </div>
                </div>

                <!-- Filtros por tipo de mudança -->
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="type in availableFilters"
                        :key="type"
                        type="button"
                        class="rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                        :class="
                            activeFilters.includes(type)
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-background text-muted-foreground hover:text-foreground'
                        "
                        @click="toggleFilter(type)"
                    >
                        {{ t(`plannerate.reoptimization.proposal.changes.${type}`) }}
                        <span class="ml-1 opacity-70">{{ summary[type] }}</span>
                    </button>
                </div>

                <!-- Tabela de mudanças -->
                <div class="overflow-x-auto rounded-lg border border-border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">
                                    {{ t('plannerate.reoptimization.proposal.table.product') }}
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    {{ t('plannerate.reoptimization.proposal.table.changes') }}
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    {{ t('plannerate.reoptimization.proposal.table.facings') }}
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    {{ t('plannerate.reoptimization.proposal.table.position') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="entry in visibleEntries" :key="entry.product_id" class="hover:bg-muted/30">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <img
                                            v-if="entry.image_url"
                                            :src="entry.image_url"
                                            :alt="entry.product_name"
                                            class="size-9 shrink-0 rounded border border-border object-contain"
                                        />
                                        <div class="min-w-0">
                                            <p class="truncate font-medium">{{ entry.product_name }}</p>
                                            <p v-if="entry.ean" class="text-xs text-muted-foreground">{{ entry.ean }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <Badge
                                            v-for="change in entry.changes"
                                            :key="change"
                                            :variant="changeVariant(change)"
                                            class="text-xs"
                                        >
                                            {{ t(`plannerate.reoptimization.proposal.changes.${change}`) }}
                                        </Badge>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="text-muted-foreground">{{ formatFacings(entry.facings_before) }}</span>
                                    <ArrowRight class="mx-1 inline size-3 text-muted-foreground" />
                                    <span class="font-medium">{{ formatFacings(entry.facings_after) }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-xs">
                                    <span class="text-muted-foreground">{{ formatPosition(entry.position_before) }}</span>
                                    <ArrowRight class="mx-1 inline size-3 text-muted-foreground" />
                                    <span class="font-medium">{{ formatPosition(entry.position_after) }}</span>
                                </td>
                            </tr>
                            <tr v-if="visibleEntries.length === 0">
                                <td colspan="4" class="px-4 py-8 text-center text-muted-foreground">
                                    {{ t('plannerate.reoptimization.proposal.table.empty') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Motivo, quando já rejeitada -->
                <div v-if="proposal.rejection_reason" class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">
                        {{ t('plannerate.reoptimization.proposal.rejection_reason') }}
                    </p>
                    <p class="mt-1 text-sm">{{ proposal.rejection_reason }}</p>
                </div>

                <!-- Barra de decisão -->
                <div
                    v-if="isPending"
                    class="fixed inset-x-0 bottom-0 z-10 border-t border-border bg-background/95 p-4 backdrop-blur"
                >
                    <div class="mx-auto flex max-w-5xl items-center justify-end gap-3">
                        <Button variant="outline" :disabled="submitting" @click="rejectOpen = true">
                            {{ t('plannerate.reoptimization.proposal.actions.reject') }}
                        </Button>
                        <Button :disabled="submitting" @click="approveOpen = true">
                            {{ t('plannerate.reoptimization.proposal.actions.approve') }}
                        </Button>
                    </div>
                </div>
            </template>
        </div>

        <!-- Confirmação da aprovação: o tudo-ou-nada precisa ser explícito -->
        <Dialog v-model:open="approveOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ t('plannerate.reoptimization.proposal.actions.approve_confirm_title') }}</DialogTitle>
                    <DialogDescription>
                        {{ t('plannerate.reoptimization.proposal.actions.approve_confirm_message') }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" :disabled="submitting" @click="approveOpen = false">
                        {{ t('plannerate.reoptimization.proposal.actions.cancel') }}
                    </Button>
                    <Button :disabled="submitting" @click="approve">
                        {{ t('plannerate.reoptimization.proposal.actions.approve_confirm_cta') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Rejeição: motivo obrigatório -->
        <Dialog v-model:open="rejectOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ t('plannerate.reoptimization.proposal.actions.reject_title') }}</DialogTitle>
                    <DialogDescription>
                        {{ t('plannerate.reoptimization.proposal.actions.reject_message') }}
                    </DialogDescription>
                </DialogHeader>
                <Textarea
                    v-model="rejectReason"
                    :placeholder="t('plannerate.reoptimization.proposal.actions.reject_placeholder')"
                    rows="3"
                    maxlength="500"
                />
                <DialogFooter>
                    <Button variant="outline" :disabled="submitting" @click="rejectOpen = false">
                        {{ t('plannerate.reoptimization.proposal.actions.cancel') }}
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="submitting || rejectReason.trim().length < 3"
                        @click="reject"
                    >
                        {{ t('plannerate.reoptimization.proposal.actions.reject_cta') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

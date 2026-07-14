<script setup lang="ts">
/**
 * Fila de propostas de reotimização aguardando decisão, de todas as gôndolas.
 *
 * Cada linha é uma decisão pendente, não um relatório: o que interessa aqui é o tamanho da
 * mudança e o ganho de ocupação — o suficiente para o gestor escolher qual revisar primeiro.
 * O detalhe (produto a produto) mora na tela da proposta.
 */
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Sparkles } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

interface Proposal {
    id: string;
    gondola_name: string | null;
    changes_count: number;
    summary: Record<string, number>;
    occupancy_before: number | null;
    occupancy_after: number | null;
    sales_period_start: string | null;
    sales_period_end: string | null;
    created_at: string | null;
    url: string;
}

defineProps<{
    proposals: Proposal[];
    statusLabel: string;
}>();

const { t } = useT();

function formatOccupancy(value: number | null): string {
    return value === null ? '—' : `${Math.round(value * 100)}%`;
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString('pt-BR') : '—';
}

const breadcrumbs = [
    { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
    { title: t('plannerate.reoptimization.inbox.title'), href: '' },
];
</script>

<template>
    <Head :title="t('plannerate.reoptimization.inbox.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <div class="space-y-1">
                <h1 class="text-xl font-semibold">{{ t('plannerate.reoptimization.inbox.title') }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('plannerate.reoptimization.inbox.subtitle') }}
                </p>
            </div>

            <div
                v-if="proposals.length === 0"
                class="rounded-lg border border-dashed border-border p-10 text-center"
            >
                <Sparkles class="mx-auto size-6 text-muted-foreground" />
                <p class="mt-3 text-sm font-medium">{{ t('plannerate.reoptimization.inbox.empty_title') }}</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ t('plannerate.reoptimization.inbox.empty_message') }}
                </p>
            </div>

            <div v-else class="overflow-x-auto rounded-lg border border-border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">
                                {{ t('plannerate.reoptimization.inbox.table.gondola') }}
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                {{ t('plannerate.reoptimization.inbox.table.changes') }}
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                {{ t('plannerate.reoptimization.proposal.occupancy') }}
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                {{ t('plannerate.reoptimization.inbox.table.analyzed_at') }}
                            </th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="proposal in proposals" :key="proposal.id" class="hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ proposal.gondola_name }}</p>
                                <Badge variant="secondary" class="mt-1 text-xs">{{ statusLabel }}</Badge>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-medium">{{ proposal.changes_count }}</span>
                                <span class="text-muted-foreground">
                                    {{ ' ' }}{{ t('plannerate.reoptimization.inbox.table.products') }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span class="text-muted-foreground">{{ formatOccupancy(proposal.occupancy_before) }}</span>
                                <ArrowRight class="mx-1 inline size-3 text-muted-foreground" />
                                <span class="font-medium">{{ formatOccupancy(proposal.occupancy_after) }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-muted-foreground">
                                {{ formatDate(proposal.created_at) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button as-child size="sm" variant="outline">
                                    <Link :href="proposal.url">
                                        {{ t('plannerate.reoptimization.banner.action') }}
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>

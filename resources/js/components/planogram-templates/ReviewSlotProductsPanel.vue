<script setup lang="ts">
import type {
    PlanogramTemplateSlot,
    SlotAnalysisData,
} from '@/components/planogram-templates/types';

const props = defineProps<{
    selectedSlot: PlanogramTemplateSlot | null;
    analysis: SlotAnalysisData | null;
    loading: boolean;
}>();
</script>

<template>
    <div class="rounded-lg border bg-card p-4 col-end-12 md:col-span-9 lg:col-span-8">
        <p class="mb-1 text-sm font-semibold">Análise de alocação</p>
        <p class="mb-3 text-xs text-muted-foreground">
            {{
                props.selectedSlot
                    ? `Grouping: ${props.selectedSlot.grouping} (simulação parcial)`
                    : 'Selecione um slot para iniciar a análise.'
            }}
        </p>

        <div v-if="props.loading" class="text-sm text-muted-foreground">
            Analisando produtos...
        </div>
        <div
            v-else-if="props.selectedSlot && !props.analysis"
            class="text-sm text-muted-foreground"
        >
            Nenhum dado de análise para este slot.
        </div>
        <div v-else-if="props.analysis" class="space-y-3">
            <div class="grid grid-cols-2 gap-2 lg:grid-cols-4">
                <div class="rounded-md border px-3 py-2">
                    <p class="text-xs text-muted-foreground">Total</p>
                    <p class="text-sm font-semibold">
                        {{ props.analysis.summary.total_products }}
                    </p>
                </div>
                <div class="rounded-md border px-3 py-2">
                    <p class="text-xs text-muted-foreground">Entrou</p>
                    <p class="text-sm font-semibold text-emerald-600">
                        {{ props.analysis.summary.placed_products }}
                    </p>
                </div>
                <div class="rounded-md border px-3 py-2">
                    <p class="text-xs text-muted-foreground">Fora</p>
                    <p class="text-sm font-semibold text-amber-600">
                        {{ props.analysis.summary.rejected_products }}
                    </p>
                </div>
                <div class="rounded-md border px-3 py-2">
                    <p class="text-xs text-muted-foreground">Livre (cm)</p>
                    <p class="text-sm font-semibold">
                        {{ props.analysis.summary.free_width_cm }}
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full text-sm">
                    <thead class="bg-muted/40">
                        <tr>
                            <th class="px-3 py-2 text-left">Produto</th>
                            <th class="px-3 py-2 text-left">Status</th>
                            <th class="px-3 py-2 text-left">Motivo</th>
                            <th class="px-3 py-2 text-left">Facing</th>
                            <th class="px-3 py-2 text-left">Largura (cm)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in props.analysis.rows"
                            :key="row.product_id"
                            class="border-t"
                        >
                            <td class="px-3 py-2 align-top">
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted/20"
                                    >
                                        <img
                                            v-if="row.url"
                                            :src="row.url"
                                            :alt="row.name"
                                            class="h-full w-full object-contain"
                                            loading="lazy"
                                        />
                                        <span
                                            v-else
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            Sem imagem
                                        </span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="line-clamp-2 font-medium">
                                            {{ row.name }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            EAN: {{ row.ean || '-' }} · Marca:
                                            {{ row.brand || '-' }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <span
                                    :class="
                                        row.status === 'entrou'
                                            ? 'text-emerald-600'
                                            : 'text-amber-600'
                                    "
                                >
                                    {{ row.status }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-muted-foreground">
                                {{ row.reason }}
                            </td>
                            <td class="px-3 py-2">{{ row.facing_used }}</td>
                            <td class="px-3 py-2">
                                {{ row.required_width_cm }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

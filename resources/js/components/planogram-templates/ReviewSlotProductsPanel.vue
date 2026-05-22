<script setup lang="ts">
import { ImageDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type {
    PlanogramTemplateSlot,
    SlotAnalysisData,
} from '@/components/planogram-templates/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    selectedSlot: PlanogramTemplateSlot | null;
    analysis: SlotAnalysisData | null;
    loading: boolean;
}>();

const emit = defineEmits<{
    syncImages: [];
}>();

const localFilter = ref('');

const filteredRows = computed(() => {
    if (!props.analysis) {
        return [];
    }

    const query = localFilter.value.trim().toLowerCase();

    if (query === '') {
        return props.analysis.rows;
    }

    return props.analysis.rows.filter((row) => {
        const name = row.name.toLowerCase();
        const ean = (row.ean ?? '').toLowerCase();
        const codigoErp = (row.codigo_erp ?? '').toLowerCase();

        return (
            name.includes(query) ||
            ean.includes(query) ||
            codigoErp.includes(query)
        );
    });
});

const slotLabel = computed(() =>
    props.selectedSlot
        ? props.selectedSlot.category_name ?? props.selectedSlot.category_id ?? '—'
        : null,
);

const hasOutroSlot = computed(
    () => (props.analysis?.summary.outro_slot_products ?? 0) > 0,
);

const hasPreviousSlots = computed(
    () => (props.analysis?.summary.previous_slots_placed ?? 0) > 0,
);

const summaryColClass = computed(() => {
    const extra = (hasOutroSlot.value ? 1 : 0) + (hasPreviousSlots.value ? 1 : 0);
    const total = 4 + extra;
    return `lg:grid-cols-${total}`;
});
</script>

<template>
    <div class="rounded-lg border bg-card p-4 col-end-12 md:col-span-9 lg:col-span-8">
        <div class="mb-2 flex items-center justify-between gap-2">
            <p class="text-sm font-semibold">Análise de alocação</p>
            <Button
                v-if="props.analysis && props.analysis.rows.length > 0"
                type="button"
                size="sm"
                variant="outline"
                @click="emit('syncImages')"
            >
                <ImageDown class="size-4" />
                Atualizar imagens
            </Button>
        </div>
        <p class="mb-3 text-xs text-muted-foreground">
            <template v-if="slotLabel">
                {{ slotLabel }} ·
                <span
                    v-if="props.analysis?.summary.zone"
                    class="font-medium"
                    :class="{
                        'text-amber-600': props.analysis.summary.zone === 'hot',
                        'text-blue-500': props.analysis.summary.zone === 'cold',
                        'text-muted-foreground': props.analysis.summary.zone === 'neutral',
                    }"
                >
                    Zona {{ props.analysis.summary.zone === 'hot' ? 'quente' : props.analysis.summary.zone === 'cold' ? 'fria' : 'neutra' }}
                </span>
                <span v-else>simulação completa</span>
            </template>
            <template v-else>Selecione um slot para iniciar a análise.</template>
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
            <div class="flex items-center justify-between gap-3">
                <Input
                    v-model="localFilter"
                    type="text"
                    placeholder="Filtrar por nome, EAN ou código ERP"
                    class="max-w-md"
                />
                <p class="text-xs text-muted-foreground">
                    {{ filteredRows.length }} de {{ props.analysis.rows.length }}
                </p>
            </div>

            <!-- Summary cards -->
            <div class="grid grid-cols-2 gap-2" :class="summaryColClass">
                <div class="rounded-md border px-3 py-2">
                    <p class="text-xs text-muted-foreground">Total na categoria</p>
                    <p class="text-sm font-semibold">
                        {{ props.analysis.summary.total_products }}
                    </p>
                </div>
                <div v-if="hasPreviousSlots" class="rounded-md border px-3 py-2">
                    <p class="text-xs text-muted-foreground">Prateleiras anteriores</p>
                    <p class="text-sm font-semibold text-muted-foreground">
                        {{ props.analysis.summary.previous_slots_placed }}
                    </p>
                </div>
                <div class="rounded-md border px-3 py-2">
                    <p class="text-xs text-muted-foreground">Entrou aqui</p>
                    <p class="text-sm font-semibold text-emerald-600">
                        {{ props.analysis.summary.placed_products }}
                    </p>
                </div>
                <div v-if="hasOutroSlot" class="rounded-md border px-3 py-2">
                    <p class="text-xs text-muted-foreground">Outro slot</p>
                    <p class="text-sm font-semibold text-blue-600">
                        {{ props.analysis.summary.outro_slot_products }}
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

            <div class="max-h-[70vh] overflow-auto rounded-md border">
                <table class="min-w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-muted/40">
                        <tr>
                            <th class="px-3 py-2 text-left">Produto</th>
                            <th class="px-3 py-2 text-left">Status</th>
                            <th class="px-3 py-2 text-left">Motivo</th>
                            <th class="px-3 py-2 text-left">ABC</th>
                            <th class="px-3 py-2 text-left">Venda</th>
                            <th class="px-3 py-2 text-left">Dimensões</th>
                            <th class="px-3 py-2 text-left">Facing</th>
                            <th class="px-3 py-2 text-left">Pos. (cm)</th>
                            <th class="px-3 py-2 text-left">Larg. (cm)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in filteredRows"
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
                                        <p class="text-xs text-muted-foreground">
                                            Cód. ERP: {{ row.codigo_erp || '-' }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <div class="flex items-center gap-1">
                                    <span
                                        :class="{
                                            'text-emerald-600': row.status === 'entrou',
                                            'text-blue-600': row.status === 'outro_slot',
                                            'text-amber-600': row.status === 'fora',
                                        }"
                                    >
                                        {{ row.status === 'outro_slot' ? 'outro slot' : row.status }}
                                    </span>
                                    <span
                                        v-if="row.is_mandatory"
                                        class="rounded bg-violet-100 px-1 py-px text-[10px] font-semibold text-violet-700"
                                        title="Produto obrigatório"
                                    >
                                        OBR
                                    </span>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-muted-foreground">
                                {{ row.reason }}
                            </td>
                            <td class="px-3 py-2">
                                <span
                                    v-if="row.abc_class"
                                    class="inline-flex size-5 items-center justify-center rounded text-[10px] font-bold"
                                    :class="{
                                        'bg-emerald-100 text-emerald-700': row.abc_class === 'A',
                                        'bg-blue-100 text-blue-700': row.abc_class === 'B',
                                        'bg-gray-100 text-gray-600': row.abc_class === 'C',
                                    }"
                                >
                                    {{ row.abc_class }}
                                </span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-3 py-2">
                                <span
                                    :class="
                                        row.has_sales
                                            ? 'text-emerald-600'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{ row.has_sales ? 'Sim' : 'Não' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-muted-foreground">
                                {{ row.dimensions }}
                            </td>
                            <td class="px-3 py-2">
                                {{ row.facing_used > 0 ? row.facing_used : '—' }}
                            </td>
                            <td class="px-3 py-2 text-muted-foreground">
                                {{ row.status === 'entrou' ? row.position_cm : '—' }}
                            </td>
                            <td class="px-3 py-2">
                                {{ row.required_width_cm > 0 ? row.required_width_cm : '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

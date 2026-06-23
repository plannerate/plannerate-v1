<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useT } from '@/composables/useT';
import type { Section } from '@/types/planogram';
import PdfSection from '@/components/plannerate/print/partials/PdfSection.vue';

interface GondolaMeta {
    id: string;
    name?: string;
    location?: string;
    side?: string;
    flow?: string;
    scale_factor?: number;
    alignment?: string;
    planogram?: {
        name?: string;
        type?: string;
        start_date?: string;
        description?: string;
        category?: { name?: string } | null;
    } | null;
}

const props = defineProps<{
    gondola: GondolaMeta;
    sections: Section[];
}>();

const { t } = useT();

/**
 * Calcula escala para a seção preencher o container sem overflow.
 * Target: 680px — garante que seção + cremalheiras cabem dentro do card
 * com folga para o px-4 de padding interno (32px cada lado).
 * Mínimo 0.3 para não ficar microscópico; máximo 3 para não estourar.
 */
function scaleForSection(section: Section): number {
    const totalMm = (section.width ?? 100) + 2 * (section.cremalheira_width ?? 0);
    if (totalMm <= 0) return 1;
    return Math.min(3, Math.max(0.3, 680 / totalMm));
}

const isLeftToRight = computed(() => props.gondola.flow !== 'right_to_left');
const flowLabel = computed(() =>
    isLeftToRight.value
        ? t('plannerate.print.share.flow_ltr')
        : t('plannerate.print.share.flow_rtl'),
);

const totalModules = computed(() => props.sections.length);

/**
 * Retorna todos os produtos de uma seção, organizados por prateleira (shelf_position desc = topo primeiro).
 */
function shelvesWithProducts(section: Section) {
    const shelves = (section.shelves ?? [])
        .filter((s) => !s.deleted_at)
        .sort((a, b) => (b.shelf_position ?? 0) - (a.shelf_position ?? 0));

    return shelves
        .map((shelf) => {
            // Agrupa por produto somando as frentes (layer.quantity = nº de frentes
            // lado a lado). O total de frentes da prateleira para um produto é a soma
            // das frentes de todos os segmentos onde ele aparece.
            const byProduct = new Map<string, {
                id: string;
                name: string;
                brand: string;
                ean: string;
                facings: number;
                position: number;
            }>();

            (shelf.segments ?? [])
                .filter((seg) => !seg.deleted_at && seg.layer?.product)
                .forEach((seg) => {
                    const product = seg.layer!.product!;
                    const facings = Math.max(1, Math.trunc(Number(seg.layer!.quantity ?? 1)) || 1);
                    const existing = byProduct.get(product.id);

                    if (existing) {
                        existing.facings += facings;
                    } else {
                        byProduct.set(product.id, {
                            id: product.id,
                            name: product.name ?? '—',
                            brand: product.brand ?? '—',
                            ean: product.ean ?? '—',
                            facings,
                            position: seg.position ?? 0,
                        });
                    }
                });

            const products = Array.from(byProduct.values()).sort(
                (a, b) => a.position - b.position,
            );

            return { shelf, products };
        })
        .filter((row) => row.products.length > 0);
}
</script>

<template>
    <Head :title="`${gondola.name ?? t('plannerate.print.share.title')} — Plannerate`" />

    <div class="force-light min-h-screen bg-slate-100 text-slate-900">
        <!-- Cabeçalho fixo -->
        <header class="sticky top-0 z-[1000] border-b border-slate-200 bg-white shadow-sm">
            <div class="mx-auto max-w-4xl px-4 py-3">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-semibold tracking-widest text-slate-400 uppercase">
                            {{ gondola.planogram?.category?.name ?? '—' }}
                        </p>
                        <h1 class="text-lg font-black tracking-wide text-slate-900 uppercase leading-tight">
                            {{ gondola.name ?? t('plannerate.print.share.title') }}
                        </h1>
                        <div class="mt-0.5 flex flex-wrap gap-3 text-xs text-slate-500">
                            <span v-if="gondola.planogram?.name">
                                {{ t('plannerate.print.share.planogram') }}: <strong>{{ gondola.planogram.name }}</strong>
                            </span>
                            <span v-if="gondola.planogram?.start_date">
                                {{ t('plannerate.print.share.date') }}: <strong>{{ gondola.planogram.start_date }}</strong>
                            </span>
                            <span v-if="gondola.location">
                                {{ t('plannerate.print.share.location') }}: <strong>{{ gondola.location }}</strong>
                            </span>
                            <span>
                                {{ flowLabel }} · {{ totalModules }} {{ t('plannerate.print.share.module') }}(s)
                            </span>
                        </div>
                    </div>
                    <div class="shrink-0">
                        <img src="/img/marca-claro.png" alt="Plannerate" class="h-8 w-auto" />
                    </div>
                </div>
            </div>
        </header>

        <!-- Módulos empilhados -->
        <main class="mx-auto max-w-4xl px-4 py-6 space-y-8">
            <article
                v-for="(section, index) in sections"
                :key="section.id"
                class="rounded-xl border border-slate-200 bg-white shadow-sm"
            >
                <!-- Cabeçalho do módulo -->
                <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-5 py-3">
                    <div class="flex items-center gap-2">
                        <span class="rounded bg-slate-800 px-2.5 py-1 text-xs font-black text-white uppercase tracking-wider">
                            {{ t('plannerate.print.share.module') }} #{{ section.ordering }}
                        </span>
                        <span class="text-xs text-slate-400">
                            {{ index + 1 }} {{ t('plannerate.print.share.of') }} {{ totalModules }}
                        </span>
                    </div>
                    <span class="text-[10px] text-slate-400">
                        {{ t('plannerate.print.labels.height_short') }}: {{ section.height }}mm ·
                        {{ t('plannerate.print.labels.width_short') }}: {{ section.width }}mm ·
                        {{ t('plannerate.print.labels.depth_short') }}: {{ section.base_depth }}mm
                    </span>
                </div>

                <!-- Visualização da gôndola -->
                <div class="overflow-x-auto bg-slate-50 px-6 pb-6"
                    :style="{ paddingTop: `${Math.ceil(scaleForSection(section) * 40)}px` }">
                    <!-- mx-auto + shrink-0: centraliza se cabe, rola se passa da largura -->
                    <div class="mx-auto shrink-0 w-fit">
                        <PdfSection
                            :section="section"
                            :scale-factor="scaleForSection(section)"
                            :alignment="gondola.alignment ?? 'justify'"
                            layout-direction="row"
                            :index="0"
                            :extra-height="0"
                            :is-share="true"
                        />
                    </div>
                </div>

                <!-- Lista de produtos por prateleira -->
                <div class="border-t border-slate-100 px-5 py-4">
                    <p class="mb-3 text-[10px] font-bold tracking-widest text-slate-500 uppercase">
                        {{ t('plannerate.print.share.product_list') }}
                    </p>

                    <div
                        v-for="({ shelf, products }, si) in shelvesWithProducts(section)"
                        :key="shelf.id"
                        class="mb-4 last:mb-0"
                    >
                        <p class="mb-1.5 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
                            {{ t('plannerate.print.share.shelf') }} {{ si + 1 }}
                            <span class="font-normal">({{ t('plannerate.print.labels.height_short') }}: {{ shelf.shelf_position }}mm)</span>
                        </p>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[480px] border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50 text-left text-[10px] text-slate-500 uppercase tracking-wider">
                                        <th class="border border-slate-100 px-2 py-1.5 font-semibold w-32">{{ t('plannerate.print.share.ean') }}</th>
                                        <th class="border border-slate-100 px-2 py-1.5 font-semibold">{{ t('plannerate.print.share.product_list') }}</th>
                                        <th class="border border-slate-100 px-2 py-1.5 font-semibold">{{ t('plannerate.print.share.brand') }}</th>
                                        <th class="border border-slate-100 px-2 py-1.5 font-semibold w-16 text-center">{{ t('plannerate.print.share.facings') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="product in products"
                                        :key="product.id"
                                        class="even:bg-slate-50/60 hover:bg-blue-50/40"
                                    >
                                        <td class="border border-slate-100 px-2 py-1.5 font-mono text-slate-500">
                                            {{ product.ean }}
                                        </td>
                                        <td class="border border-slate-100 px-2 py-1.5 font-medium text-slate-800">
                                            {{ product.name }}
                                        </td>
                                        <td class="border border-slate-100 px-2 py-1.5 text-slate-600">
                                            {{ product.brand }}
                                        </td>
                                        <td class="border border-slate-100 px-2 py-1.5 text-center text-slate-600">
                                            {{ product.facings }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <p
                        v-if="shelvesWithProducts(section).length === 0"
                        class="text-xs text-slate-400 italic"
                    >
                        {{ t('plannerate.print.share.no_product') }}
                    </p>
                </div>
            </article>
        </main>

        <!-- Rodapé -->
        <footer class="mt-4 border-t border-slate-200 bg-white py-4 text-center">
            <p class="text-[10px] text-slate-400">{{ t('plannerate.print.share.readonly_notice') }}</p>
        </footer>
    </div>
</template>

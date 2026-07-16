import { computed, shallowRef } from 'vue';
import type { BcgAxis, BcgDisplayBy, BcgQuadrant, BcgResult, BcgSpaceAction } from '@/components/plannerate/analysis/bcg/types';
import { createEanAnalysisStore } from './useEanAnalysisStore';

/**
 * O que o selo da gôndola precisa saber sobre um produto.
 *
 * Guarda a AÇÃO junto do quadrante de propósito: um selo que só diz o quadrante é
 * decoração — o que o repositor precisa ver na frente do produto é "esta aqui pede
 * mais frente" / "esta aqui está ocupando espaço demais".
 *
 * Os eixos vêm junto porque o rótulo do quadrante é derivado deles (ver bcg/labels.ts).
 */
export interface BcgBadgeData {
    quadrant: BcgQuadrant;
    acao_espaco: BcgSpaceAction;
    x_axis: BcgAxis;
    y_axis: BcgAxis;
    /**
     * Granularidade da análise que gerou o selo. Ausente ou 'produto' = o selo é do
     * próprio produto. Um nível da hierarquia (ex.: 'categoria') = o selo representa o
     * GRUPO ao qual o produto pertence — o mesmo selo aparece em todos os produtos da
     * categoria, e é marcado visualmente como categoria (ver BcgBadge).
     */
    display_by?: BcgDisplayBy;
    /** Nome do grupo/categoria representado — só no modo agregado, rotula o selo. */
    group_label?: string;
}

const useBcgStore = createEanAnalysisStore<BcgBadgeData>('bcg-analysis');

/**
 * Filtro de quadrante ativo na gôndola. Escopo de MÓDULO (não do composable) para
 * ser um singleton: o painel de análises e o selo de cada segmento precisam ler o
 * mesmo estado. Set vazio = sem filtro (mostra todos).
 */
const _activeQuadrants = shallowRef<Set<BcgQuadrant>>(new Set());

/**
 * Modo "exibir por categoria/nível": o backend agrega os produtos no seu ancestral e
 * devolve uma linha por grupo (sem EAN). Para marcar cada produto na gôndola com o selo
 * da SUA categoria, mapeamos cada produto membro (member_product_ids) ao selo do grupo.
 * Singletons de módulo, como _activeQuadrants, pelo mesmo motivo.
 */
const _badgeByProductId = shallowRef<Map<string, BcgBadgeData>>(new Map());
/** Uma entrada por grupo agregado — alimenta os contadores do painel no modo categoria. */
const _aggregatedBadges = shallowRef<BcgBadgeData[]>([]);

/**
 * Store dos quadrantes BCG por EAN, para o selo na frente do produto.
 *
 * Uso:
 *   - PerformanceBcgTab / DropdownPerformance → alimentam via setBcgQuadrants
 *   - Segment / BcgBadge                      → leem via getBcgData
 */
export function useBcgAnalysis() {
    const store = useBcgStore();

    function setBcgQuadrants(items: BcgResult[]) {
        // Exibir por categoria/nível: linhas agregadas sem EAN. Marcamos cada produto
        // membro com o selo do seu grupo, em vez de ignorá-las por não terem EAN.
        const isAggregated = items.some((item) => item.display_by && item.display_by !== 'produto');

        if (isAggregated) {
            const byProduct = new Map<string, BcgBadgeData>();
            const rows: BcgBadgeData[] = [];

            for (const item of items) {
                if (!item.quadrant) {
                    continue;
                }

                const badge: BcgBadgeData = {
                    quadrant: item.quadrant,
                    acao_espaco: item.acao_espaco,
                    x_axis: item.x_axis,
                    y_axis: item.y_axis,
                    display_by: item.display_by,
                    group_label: item.product_name,
                };
                rows.push(badge);

                for (const productId of item.member_product_ids ?? []) {
                    byProduct.set(productId, badge);
                }
            }

            _badgeByProductId.value = byProduct;
            _aggregatedBadges.value = rows;
            store.clear(); // nenhum selo por EAN no modo categoria

            return;
        }

        _badgeByProductId.value = new Map();
        _aggregatedBadges.value = [];
        store.setBatch(
            items
                .filter((item) => item.ean && item.quadrant)
                .map((item) => ({
                    ean: item.ean,
                    value: {
                        quadrant: item.quadrant,
                        acao_espaco: item.acao_espaco,
                        x_axis: item.x_axis,
                        y_axis: item.y_axis,
                    },
                })),
        );
    }

    /**
     * Selo do produto na gôndola. No modo por produto, busca pelo EAN; no modo por
     * categoria, cai no selo do grupo do produto (mapeado por member_product_ids).
     */
    function getBcgData(ean: string | undefined, productId?: string | undefined): BcgBadgeData | undefined {
        return store.get(ean) ?? (productId ? _badgeByProductId.value.get(productId) : undefined);
    }

    function hasBcgData(ean: string | undefined, productId?: string | undefined): boolean {
        return store.has(ean) || (productId ? _badgeByProductId.value.has(productId) : false);
    }

    function clearBcgQuadrants() {
        store.clear();
        _badgeByProductId.value = new Map();
        _aggregatedBadges.value = [];
        _activeQuadrants.value = new Set();
    }

    /** Sem filtro (conjunto vazio) → todos os quadrantes aparecem. */
    function isQuadrantActive(quadrant: BcgQuadrant | undefined): boolean {
        if (!quadrant) {
return false;
}

        return _activeQuadrants.value.size === 0 || _activeQuadrants.value.has(quadrant);
    }

    function toggleQuadrantFilter(quadrant: BcgQuadrant) {
        const next = new Set(_activeQuadrants.value);

        if (next.has(quadrant)) {
            next.delete(quadrant);
        } else {
            next.add(quadrant);
        }

        _activeQuadrants.value = next;
    }

    function resetQuadrantFilter() {
        _activeQuadrants.value = new Set();
    }

    /**
     * Fonte dos contadores: no modo agregado, uma entrada por categoria; no modo por
     * produto, os selos por EAN. Assim o painel conta categorias (não produtos) quando
     * a análise é exibida por categoria.
     */
    const effectiveValues = computed<BcgBadgeData[]>(() =>
        _aggregatedBadges.value.length > 0 ? _aggregatedBadges.value : store.rawValues.value,
    );

    /** Contagem por quadrante — alimenta os botões de filtro do painel. */
    const quadrantStats = computed(() => {
        const stats: Record<BcgQuadrant, number> = {
            alto_alto: 0,
            forte_x: 0,
            forte_y: 0,
            baixo_baixo: 0,
        };

        for (const item of effectiveValues.value) {
            stats[item.quadrant] += 1;
        }

        return stats;
    });

    /** Produtos/categorias cujo espaço na gôndola está desalinhado do valor entregue. */
    const misallocatedCount = computed(
        () =>
            effectiveValues.value.filter(
                (item) => item.acao_espaco === 'aumentar' || item.acao_espaco === 'reduzir',
            ).length,
    );

    /** Tem dado para exibir em qualquer modo (por produto ou por categoria). */
    const hasData = computed(() => effectiveValues.value.length > 0);

    /** Total exibido no painel: nº de produtos ou de categorias, conforme o modo. */
    const stats = computed(() => ({
        ...store.stats.value,
        total: effectiveValues.value.length,
    }));

    return {
        setBcgQuadrants,
        getBcgData,
        hasBcgData,
        clearBcgQuadrants,
        isQuadrantActive,
        toggleQuadrantFilter,
        resetQuadrantFilter,
        activeQuadrants: computed(() => _activeQuadrants.value),
        quadrantStats,
        misallocatedCount,
        toggleVisibility: store.toggleVisibility,
        setVisibility: store.setVisibility,
        stats,
        hasData,
        isVisible: store.isVisible,
        lastAnalysisDate: store.lastAnalysisDate,
    };
}

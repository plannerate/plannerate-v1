import { computed, shallowRef } from 'vue';
import { createEanAnalysisStore } from './useEanAnalysisStore';

/**
 * Classificação ABC de um produto.
 */
export type AbcClass = 'A' | 'B' | 'C';

/**
 * Recomendação de sortimento derivada da classificação ABC e da decisão de
 * retirar (ou não) o produto do mix:
 * - `proteger`     → Produto A (alta performance, deve ser protegido)
 * - `potencializar`→ Produto B (média performance, deve ser potencializado)
 * - `monitorar`    → Produto C que a análise recomenda manter (monitorar)
 * - `retirar`      → Produto C que a análise recomenda retirar do mix
 */
export type AbcRecommendation = 'proteger' | 'potencializar' | 'monitorar' | 'retirar';

/** Lista canônica das recomendações (tags) na ordem de exibição. */
export const ABC_RECOMMENDATIONS: AbcRecommendation[] = ['proteger', 'potencializar', 'monitorar', 'retirar'];

/**
 * Filtro de tags (recomendações) ativas para exibição dos selos ABC.
 * Estado singleton no nível do módulo — compartilhado entre todos os
 * componentes que consomem `useAbcClassification()` (dropdown e selos).
 *
 * Filtra pela TAG COMPLETA (Proteger/Potencializar/Monitorar/Retirar) e não só
 * pela classe ABC — assim os produtos C "monitorar" e "retirar", que compartilham
 * a mesma classe, podem ser exibidos/ocultados independentemente.
 *
 * Por padrão todas as tags estão ativas. Ao desmarcar uma tag, os selos daquela
 * recomendação deixam de ser renderizados no planograma.
 */
const _activeRecommendations = shallowRef<Set<AbcRecommendation>>(new Set<AbcRecommendation>(ABC_RECOMMENDATIONS));

/**
 * Entrada armazenada por EAN: a classificação ABC mais a informação de se o
 * produto deve ser retirado do mix (necessária para distinguir os dois cenários
 * de produtos C: "monitorar" vs. "retirar").
 */
export interface AbcClassificationEntry {
    classificacao: AbcClass;
    retirarDoMix: boolean;
}

/**
 * Deriva a recomendação de sortimento a partir da classificação e da decisão
 * de retirada do mix.
 */
export function resolveAbcRecommendation(entry: AbcClassificationEntry | undefined): AbcRecommendation | undefined {
    if (!entry) {
        return undefined;
    }

    switch (entry.classificacao) {
        case 'A':
            return 'proteger';
        case 'B':
            return 'potencializar';
        case 'C':
            return entry.retirarDoMix ? 'retirar' : 'monitorar';
        default:
            return undefined;
    }
}

/**
 * Store singleton para classificações ABC por EAN.
 * Criada no nível do módulo para garantir estado compartilhado entre todos os
 * componentes que chamarem `useAbcClassification()`.
 *
 * Cada valor armazenado é um {@link AbcClassificationEntry} (classificação +
 * decisão de retirada do mix); por isso `getClassificacao` extrai apenas a
 * classificação para os contadores de stats.
 */
const useAbcStore = createEanAnalysisStore<AbcClassificationEntry>('abc-classification', {
    getClassificacao: (item) => item.classificacao,
});

/**
 * Composable para gerenciar classificações ABC de produtos em tempo real.
 * Armazena um mapa de EAN → {@link AbcClassificationEntry}.
 *
 * Uso:
 * - No PerformanceAbcTab: salvar resultados após cálculo via `setClassifications`
 * - No Segment / AbcBadge: buscar classificação/recomendação por EAN
 */
export function useAbcClassification() {
    const store = useAbcStore();

    /**
     * Define a classificação ABC de um produto pelo EAN.
     * Não atualiza lastAnalysisDate — use `setClassifications` (batch) para isso.
     */
    function setClassification(ean: string, classification: AbcClass, retirarDoMix = false) {
        store.set(ean, { classificacao: classification, retirarDoMix });
    }

    /**
     * Define múltiplas classificações de uma vez (batch) e registra a data da análise.
     * Itens com EAN ou classificação vazios são ignorados silenciosamente.
     */
    function setClassifications(items: Array<{ ean: string; classificacao: AbcClass; retirar_do_mix?: boolean }>) {
        store.setBatch(
            items
                .filter((i) => i.ean && i.classificacao)
                .map((i) => ({
                    ean: i.ean,
                    value: { classificacao: i.classificacao, retirarDoMix: Boolean(i.retirar_do_mix) },
                })),
        );
    }

    /**
     * Obtém a entrada completa (classificação + retirada do mix) de um produto pelo EAN.
     * Retorna undefined se não houver classificação registrada.
     */
    function getEntry(ean: string | undefined): AbcClassificationEntry | undefined {
        return store.get(ean);
    }

    /**
     * Obtém a classificação ABC de um produto pelo EAN.
     * Retorna undefined se não houver classificação registrada.
     */
    function getClassification(ean: string | undefined): AbcClass | undefined {
        return store.get(ean)?.classificacao;
    }

    /**
     * Obtém a recomendação de sortimento (proteger/potencializar/monitorar/retirar)
     * de um produto pelo EAN. Retorna undefined se não houver classificação.
     */
    function getRecommendation(ean: string | undefined): AbcRecommendation | undefined {
        return resolveAbcRecommendation(store.get(ean));
    }

    /**
     * Verifica se existe classificação para o EAN informado.
     */
    function hasClassification(ean: string | undefined): boolean {
        return store.has(ean);
    }

    /**
     * Remove todas as classificações e reseta o timestamp da análise.
     */
    function clearClassifications() {
        store.clear();
    }

    /**
     * Remove a classificação de um produto específico pelo EAN.
     */
    function removeClassification(ean: string) {
        store.remove(ean);
    }

    /**
     * Verifica se a tag (recomendação) informada está ativa no filtro de exibição.
     * Retorna false para produtos sem recomendação (undefined).
     */
    function isRecommendationActive(recommendation: AbcRecommendation | undefined): boolean {
        if (!recommendation) {
            return false;
        }

        return _activeRecommendations.value.has(recommendation);
    }

    /**
     * Alterna (liga/desliga) a exibição dos selos de uma tag específica.
     * Cria um novo Set para disparar a reatividade do shallowRef.
     */
    function toggleRecommendationFilter(recommendation: AbcRecommendation): void {
        const next = new Set(_activeRecommendations.value);
        if (next.has(recommendation)) {
            next.delete(recommendation);
        } else {
            next.add(recommendation);
        }
        _activeRecommendations.value = next;
    }

    /**
     * Define explicitamente o conjunto de tags ativas no filtro.
     */
    function setRecommendationFilter(recommendations: AbcRecommendation[]): void {
        _activeRecommendations.value = new Set(recommendations);
    }

    /**
     * Reseta o filtro para exibir todas as tags (Proteger/Potencializar/Monitorar/Retirar).
     */
    function resetRecommendationFilter(): void {
        _activeRecommendations.value = new Set<AbcRecommendation>(ABC_RECOMMENDATIONS);
    }

    /**
     * Contagem de produtos por tag (recomendação), derivada das entradas atuais.
     * Usada pelo dropdown para exibir "(N)" ao lado de cada tag.
     */
    const recommendationStats = computed(() => {
        const counts: Record<AbcRecommendation, number> = {
            proteger: 0,
            potencializar: 0,
            monitorar: 0,
            retirar: 0,
        };

        for (const entry of store.rawValues.value) {
            const recommendation = resolveAbcRecommendation(entry);
            if (recommendation) {
                counts[recommendation] += 1;
            }
        }

        return counts;
    });

    return {
        // Métodos
        setClassification,
        setClassifications,
        getEntry,
        getClassification,
        getRecommendation,
        hasClassification,
        clearClassifications,
        removeClassification,
        toggleVisibility: store.toggleVisibility,
        setVisibility: store.setVisibility,

        // Filtro por tag completa (Proteger/Potencializar/Monitorar/Retirar)
        isRecommendationActive,
        toggleRecommendationFilter,
        setRecommendationFilter,
        resetRecommendationFilter,

        // Computed
        stats: store.stats,
        recommendationStats,
        hasData: store.hasData,
        isVisible: store.isVisible,
        lastAnalysisDate: store.lastAnalysisDate,
        activeRecommendations: computed(() => _activeRecommendations.value),
    };
}

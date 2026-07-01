import { computed, shallowRef } from 'vue';
import { createEanAnalysisStore } from './useEanAnalysisStore';

/**
 * Classificação ABC de um produto.
 */
export type AbcClass = 'A' | 'B' | 'C';

/**
 * Filtro de classes ativas para exibição dos selos ABC.
 * Estado singleton no nível do módulo — compartilhado entre todos os
 * componentes que consomem `useAbcClassification()` (dropdown e selos).
 *
 * Por padrão todas as classes (A/B/C) estão ativas. Ao desmarcar uma classe,
 * os selos daquela classe deixam de ser renderizados no planograma.
 */
const _activeClasses = shallowRef<Set<AbcClass>>(new Set<AbcClass>(['A', 'B', 'C']));

/**
 * Recomendação de sortimento derivada da classificação ABC e da decisão de
 * retirar (ou não) o produto do mix:
 * - `proteger`     → Produto A (alta performance, deve ser protegido)
 * - `potencializar`→ Produto B (média performance, deve ser potencializado)
 * - `monitorar`    → Produto C que a análise recomenda manter (monitorar)
 * - `retirar`      → Produto C que a análise recomenda retirar do mix
 */
export type AbcRecommendation = 'proteger' | 'potencializar' | 'monitorar' | 'retirar';

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
     * Verifica se a classe informada está ativa no filtro de exibição.
     * Retorna false para EANs sem classificação (classe undefined).
     */
    function isClassActive(classification: AbcClass | undefined): boolean {
        if (!classification) {
            return false;
        }

        return _activeClasses.value.has(classification);
    }

    /**
     * Alterna (liga/desliga) a exibição dos selos de uma classe específica.
     * Cria um novo Set para disparar a reatividade do shallowRef.
     */
    function toggleClassFilter(classification: AbcClass): void {
        const next = new Set(_activeClasses.value);
        if (next.has(classification)) {
            next.delete(classification);
        } else {
            next.add(classification);
        }
        _activeClasses.value = next;
    }

    /**
     * Define explicitamente o conjunto de classes ativas no filtro.
     */
    function setClassFilter(classes: AbcClass[]): void {
        _activeClasses.value = new Set(classes);
    }

    /**
     * Reseta o filtro para exibir todas as classes (A/B/C).
     */
    function resetClassFilter(): void {
        _activeClasses.value = new Set<AbcClass>(['A', 'B', 'C']);
    }

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

        // Filtro por classe (A/B/C)
        isClassActive,
        toggleClassFilter,
        setClassFilter,
        resetClassFilter,

        // Computed
        stats: store.stats,
        hasData: store.hasData,
        isVisible: store.isVisible,
        lastAnalysisDate: store.lastAnalysisDate,
        activeClasses: computed(() => _activeClasses.value),
    };
}

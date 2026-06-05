import { createEanAnalysisStore } from './useEanAnalysisStore';
import type { ProductRole } from '@/components/plannerate/analysis/paper/types';

/**
 * Store singleton para papéis estratégicos (Análise de Papel) por EAN.
 * Criada no nível do módulo para garantir estado compartilhado entre todos os
 * componentes que chamarem `usePaperAnalysis()`.
 *
 * Cada valor armazenado é diretamente o papel ('leader' | 'anchor' | 'rising' | 'lagging'),
 * portanto `getClassificacao` não se aplica aqui (sem contagem por classe ABC).
 */
const usePaperStore = createEanAnalysisStore<ProductRole>('paper-analysis');

/**
 * Composable para gerenciar papéis estratégicos de produtos em tempo real.
 * Armazena um mapa de EAN → ProductRole (leader, anchor, rising, lagging).
 *
 * Uso:
 * - No PerformancePaperTab: salvar resultados via `setPaperRoles`
 * - No Segment / PaperRoleBadge: buscar papel por EAN via `getPaperRole`
 */
export function usePaperAnalysis() {
    const store = usePaperStore();

    /**
     * Define o papel estratégico de um produto pelo EAN.
     */
    function setPaperRole(ean: string, role: ProductRole) {
        store.set(ean, role);
    }

    /**
     * Define múltiplos papéis de uma vez (batch) e registra a data da análise.
     * Itens com EAN ou role vazios são ignorados silenciosamente.
     */
    function setPaperRoles(items: Array<{ ean: string; role: ProductRole }>) {
        store.setBatch(
            items
                .filter((i) => i.ean && i.role)
                .map((i) => ({ ean: i.ean, value: i.role })),
        );
    }

    /**
     * Obtém o papel estratégico de um produto pelo EAN.
     * Retorna undefined se não houver papel registrado.
     */
    function getPaperRole(ean: string | undefined): ProductRole | undefined {
        return store.get(ean);
    }

    /**
     * Verifica se existe papel registrado para o EAN informado.
     */
    function hasPaperRole(ean: string | undefined): boolean {
        return store.has(ean);
    }

    /**
     * Remove todos os papéis registrados e reseta o timestamp da análise.
     */
    function clearPaperRoles() {
        store.clear();
    }

    return {
        setPaperRole,
        setPaperRoles,
        getPaperRole,
        hasPaperRole,
        clearPaperRoles,
        toggleVisibility: store.toggleVisibility,
        setVisibility: store.setVisibility,
        stats: store.stats,
        hasData: store.hasData,
        isVisible: store.isVisible,
        lastAnalysisDate: store.lastAnalysisDate,
    };
}

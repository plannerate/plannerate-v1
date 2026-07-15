/**
 * Visibilidade das análises de performance (ABC, Estoque Alvo, Papel, BCG).
 *
 * Ponto ÚNICO de liga/desliga: as abas da modal de Performance e os cards do dropdown
 * "Análises do Planograma" leem daqui, então esconder uma análise em um lugar a esconde
 * em todos. Hoje é estático; no futuro cada flag pode vir de uma prop do backend sem
 * mexer nos componentes.
 */

export type AnalysisKey = 'abc' | 'target-stock' | 'paper' | 'bcg'

const ANALYSIS_VISIBILITY: Record<AnalysisKey, boolean> = {
    abc: true,
    'target-stock': true,
    // Oculta por ora — reativar é trocar para true
    paper: false,
    bcg: true,
}

export const isAnalysisVisible = (key: AnalysisKey): boolean => ANALYSIS_VISIBILITY[key] !== false

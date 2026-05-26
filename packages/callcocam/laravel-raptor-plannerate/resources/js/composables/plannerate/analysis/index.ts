/**
 * Analysis — classificação ABC, análise de estoque-alvo e filtros.
 *
 * Agrupa todos os composables de análise de desempenho de produtos:
 *   - useAbcClassification: curva ABC por EAN (A/B/C baseado em vendas)
 *   - useTargetStockAnalysis: análise de estoque-alvo por EAN
 *   - usePerformanceIndicators: orquestra ABC + TargetStock num único composable
 *   - createEanAnalysisStore: factory genérico para stores de análise por EAN
 *   - useAnalysisFilters: filtro, busca e ordenação genéricos para listas
 */
export * from './useAnalysisFilters';
export * from './useAnalysisExport';
export * from './useEanAnalysisStore';
export * from './useAbcClassification';
export * from './useTargetStockAnalysis';
export * from './usePerformanceIndicators';

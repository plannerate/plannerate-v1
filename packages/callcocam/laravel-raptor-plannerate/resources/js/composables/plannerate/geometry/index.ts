/**
 * Geometry — cálculos visuais e geométricos da gôndola e prateleiras.
 *
 * Agrupa funções puras e composables que operam sobre medidas físicas,
 * posições e zonas de visibilidade, sem dependência de estado reativo:
 *   - useSectionHoles: posições de furos da cremalheira por seção
 *   - useShelfZone: classificação de zona visual (eye/hand/high/low)
 *   - useShelfAreaCalculation: área clicável e dimensões visíveis da prateleira
 *   - useShelfLayout: geometria de posicionamento de produtos na prateleira
 */
export * from './useSectionHoles';
export * from './useShelfZone';
export * from './useShelfAreaCalculation';
export * from './useShelfLayout';

/**
 * Papéis estratégicos de produto na Análise de Papel.
 *
 * Cada papel é determinado pelo cruzamento de dois indicadores:
 *   - market_share : participação do produto no total da categoria (período atual)
 *   - growth_rate  : variação de venda em relação ao período anterior
 *
 *   leader  → alto share + alto crescimento : produto que lidera em ambas as dimensões
 *   anchor  → alto share + baixo crescimento: âncora de receita, posição estável
 *   rising  → baixo share + alto crescimento: potencial de crescimento, ampliar frentes
 *   lagging → baixo share + baixo crescimento: candidato à revisão de mix
 */
export type ProductRole = 'leader' | 'anchor' | 'rising' | 'lagging'

export interface PaperResult {
    product_id: string
    product_name: string
    ean: string
    image_url?: string | null
    category_id?: string | null
    category_name: string
    /** Papel estratégico calculado pelo PaperAnalysisService */
    role: ProductRole
    /** Participação no valor total da categoria no período atual (%) */
    market_share: number
    /** Variação percentual em relação ao período anterior */
    growth_rate: number
    total_value_current: number
    total_value_previous: number
    /** Mediana de market_share da categoria — linha divisória entre alto e baixo share */
    share_threshold: number
}

export interface PaperSummary {
    total: number
    leader: number
    anchor: number
    rising: number
    lagging: number
}

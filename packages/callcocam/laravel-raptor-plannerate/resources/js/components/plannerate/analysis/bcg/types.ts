export type BcgQuadrant = 'star' | 'cash_cow' | 'question_mark' | 'dog'

export interface BcgResult {
    product_id: string
    product_name: string
    ean: string
    image_url?: string | null
    category_id?: string | null
    category_name: string
    quadrant: BcgQuadrant
    market_share: number     // % do total da categoria (período atual)
    growth_rate: number      // % de crescimento (período anterior → atual)
    total_value_current: number
    total_value_previous: number
    share_threshold: number  // mediana usada como linha divisória de share
}

export interface BcgSummary {
    total: number
    star: number
    cash_cow: number
    question_mark: number
    dog: number
}

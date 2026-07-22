/**
 * Tipos da Análise BCG (matriz de quadrantes com eixos configuráveis).
 *
 * Diferente da Análise de Papel (share × crescimento, dois períodos), a BCG cruza
 * duas métricas de NÍVEL num único período e compara cada produto ao seu grupo
 * mercadológico.
 */

/** Métricas disponíveis como eixo — espelham BcgAnalysisService::AXIS_COLUMNS. */
export type BcgAxis = 'valor' | 'quantidade' | 'margem'

/** Método de corte dos quadrantes. `mean` reproduz a planilha VBA original. */
export type BcgThresholdMethod = 'median' | 'mean'

/**
 * Quadrantes: as chaves são AGNÓSTICAS DE EIXO de propósito.
 *
 * Os rótulos da planilha ("Incentivo – volume", "Incentivo – lucro") só fazem sentido
 * quando X = quantidade e Y = margem; com eixos configuráveis eles mentem (X = valor
 * e Y = quantidade não tem eixo de lucro nenhum). Por isso o backend devolve a chave
 * estável + os eixos usados, e o rótulo é composto aqui no frontend.
 *
 *   alto_alto   → forte nos dois eixos
 *   forte_x     → forte só no eixo X
 *   forte_y     → forte só no eixo Y
 *   baixo_baixo → fraco nos dois
 */
export type BcgQuadrant = 'alto_alto' | 'forte_x' | 'forte_y' | 'baixo_baixo'

/**
 * Ação de planograma derivada do cruzamento quadrante × espaço ocupado.
 * `null` quando o produto não tem largura cadastrada — sem o dado, não há base
 * para recomendar nada.
 */
export type BcgSpaceAction = 'aumentar' | 'reduzir' | 'manter' | null

/** Níveis da hierarquia mercadológica onde a linha de corte pode ser calculada. */
export type BcgClassifyLevel =
    | 'segmento_varejista'
    | 'departamento'
    | 'subdepartamento'
    | 'categoria'
    | 'subcategoria'

/**
 * Granularidade da exibição dos resultados:
 *   - `produto`         → uma linha por produto (padrão, nível mais profundo)
 *   - um nível da hierarquia → produtos somados no seu ancestral daquele nível, cada
 *     grupo como um item único. Exige classificar acima do nível exibido.
 */
export type BcgDisplayBy = 'produto' | BcgClassifyLevel

export interface BcgResult {
    product_id: string
    product_name: string
    ean: string
    image_url?: string | null
    /** Categoria FOLHA do produto — exibição. Distinta do grupo onde o corte foi calculado. */
    category_id?: string | null
    category_name: string

    /** Grupo de comparação: a categoria ancestral no nível de classify_by */
    group_id?: string | null
    group_name: string
    classify_by: BcgClassifyLevel
    /** Presente e igual a 'categoria' quando a linha representa uma categoria agregada */
    display_by?: BcgDisplayBy
    /**
     * IDs dos produtos somados nesta linha — só no modo agregado (display_by !== 'produto').
     * O selo da gôndola usa isso para marcar cada produto físico com o quadrante da SUA
     * categoria, já que a linha agregada não tem EAN próprio.
     */
    member_product_ids?: string[]

    quadrant: BcgQuadrant
    /** Produto sem venda no período: entra zerado e fica fora do cálculo do limiar */
    sem_venda: boolean

    /** Eixos usados no cálculo — necessários para compor os rótulos dos quadrantes */
    x_axis: BcgAxis
    y_axis: BcgAxis
    x_value: number
    y_value: number
    /** Limiar do grupo (mediana ou média) em cada eixo */
    x_threshold: number
    y_threshold: number
    /** Posição do produto dentro do grupo (0-100) — o score contínuo por trás do rótulo */
    x_percentil: number
    y_percentil: number
    /** Produto a menos de 10% da dispersão do grupo da linha de corte: pode trocar de quadrante por ruído */
    is_borderline: boolean
    /** Vendeu com margem de contribuição negativa (só quando a margem é um dos eixos) */
    alerta_margem_negativa: boolean

    /** Espaço ocupado na gôndola (GondolaSpaceService) */
    facings: number
    espaco_linear_cm: number
    share_gondola: number
    /** Produto sem largura cadastrada — o share é 0 por falta de dado, não por ser pequeno */
    sem_dimensao: boolean
    /** Mediana do share de gôndola — divisor entre "muito" e "pouco" espaço */
    share_threshold_gondola: number
    acao_espaco: BcgSpaceAction
}

export interface BcgSummary {
    total: number
    alto_alto: number
    forte_x: number
    forte_y: number
    baixo_baixo: number
    sem_venda: number
    borderline: number
    /** Produtos cujo espaço na gôndola está desalinhado do valor que entregam */
    espaco_mal_alocado: number
}

/** Rótulo curto de cada métrica de eixo — usado nos títulos dos eixos e no CSV. */
export const AXIS_LABEL_KEYS: Record<BcgAxis, string> = {
    valor: 'plannerate.analysis.bcg_params.axis_valor',
    quantidade: 'plannerate.analysis.bcg_params.axis_quantidade',
    margem: 'plannerate.analysis.bcg_params.axis_margem',
}

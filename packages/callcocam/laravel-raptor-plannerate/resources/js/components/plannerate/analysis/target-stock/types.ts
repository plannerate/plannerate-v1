export interface TargetStockResult {
    product_id: string;
    product_name: string;
    ean: string;
    classificacao: 'A' | 'B' | 'C';
    demanda_media: number;
    desvio_padrao: number;
    variabilidade: number;
    cobertura_dias: number;
    nivel_servico: number;
    z_score: number;
    estoque_seguranca: number;
    estoque_minimo: number;
    estoque_alvo: number;
    estoque_atual: number;
    permite_frentes: string;
    alerta_variabilidade: boolean;
}

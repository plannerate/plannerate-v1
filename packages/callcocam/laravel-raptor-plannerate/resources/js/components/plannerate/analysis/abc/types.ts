export interface AbcResult {
    product_id: string;
    product_name: string;
    ean: string;
    image_url?: string | null;
    category_id: string;
    category_name: string;
    qtde: number;
    valor: number;
    margem: number;
    media_ponderada: number;
    percentual_individual: number;
    percentual_acumulado: number;
    classificacao: 'A' | 'B' | 'C';
    ranking: number;
    class_rank: string;
    retirar_do_mix: boolean;
    status?: {
        status: string;
        motivo: string;
    };
}

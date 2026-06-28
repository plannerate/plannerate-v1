<?php

namespace Callcocam\LaravelRaptorPlannerate\Sales;

use Callcocam\LaravelRaptorPlannerate\Models\Sale;
use Illuminate\Database\Eloquent\Builder;

/**
 * Construtor de consultas de vendas reutilizável.
 *
 * Centraliza, num único lugar, regras que antes estavam copiadas entre
 * ProductController::sales e ProductSalesController::summary:
 *  - o casamento produto↔venda (product_id OU ean OU codigo_erp);
 *  - o filtro de período (sale_date >=/<=);
 *  - os filtros de loja e promoção;
 *  - e, sobretudo, o SELECT agregado canônico (AGGREGATE_SELECT), que define
 *    o vocabulário oficial das colunas somadas.
 */
class SalesQuery
{
    /**
     * SELECT agregado canônico — substitui as cópias de SUM/AVG espalhadas pelo sistema.
     * Os aliases aqui definidos são os nomes oficiais consumidos por SalesSummary.
     */
    public const AGGREGATE_SELECT = "
        COUNT(*) as total_records,
        SUM(total_sale_quantity) as total_quantity,
        SUM(total_sale_value) as total_value,
        SUM(acquisition_cost) as total_acquisition_cost,
        SUM(total_profit_margin) as total_profit_margin,
        SUM(margem_contribuicao) as total_margem_contribuicao,
        AVG(sale_price) as avg_sale_price,
        MIN(sale_date) as first_sale_date,
        MAX(sale_date) as last_sale_date,
        SUM(CASE WHEN promotion = 'S' THEN 1 ELSE 0 END) as promo_records,
        SUM(CASE WHEN promotion = 'S' THEN total_sale_quantity ELSE 0 END) as promo_quantity,
        SUM(CASE WHEN promotion = 'S' THEN total_sale_value ELSE 0 END) as promo_value,
        SUM(CASE WHEN promotion != 'S' OR promotion IS NULL THEN total_sale_quantity ELSE 0 END) as regular_quantity,
        SUM(CASE WHEN promotion != 'S' OR promotion IS NULL THEN total_sale_value ELSE 0 END) as regular_value
    ";

    /**
     * @param  Builder<Sale>  $query
     */
    private function __construct(private Builder $query) {}

    /**
     * Inicia uma nova consulta de vendas a partir do model Sale.
     */
    public static function make(): self
    {
        return new self(Sale::query());
    }

    /**
     * Restringe às vendas do produto pelas suas chaves de casamento.
     *
     * Muitas vendas chegam da integração sem product_id preenchido (vinculadas
     * apenas por codigo_erp/ean), por isso o casamento usa OU entre as três chaves.
     * EAN e codigo_erp só entram no OU quando não-vazios, evitando casar registros
     * de produtos distintos por valores nulos.
     */
    public function forProductKeys(?string $productId, ?string $ean, ?string $codigoErp): self
    {
        $this->query->where(function (Builder $q) use ($productId, $ean, $codigoErp): void {
            if (! empty($productId)) {
                $q->orWhere('product_id', $productId);
            }

            if (! empty($ean)) {
                $q->orWhere('ean', $ean);
            }

            if (! empty($codigoErp)) {
                $q->orWhere('codigo_erp', $codigoErp);
            }
        });

        return $this;
    }

    /**
     * Restringe às vendas do produto, lendo as chaves do objeto (App ou package Product).
     */
    public function forProduct(object $product): self
    {
        return $this->forProductKeys(
            $product->id ?? null,
            $product->ean ?? null,
            $product->codigo_erp ?? null,
        );
    }

    /**
     * Aplica período, promoção e loja a partir do DTO de filtros.
     */
    public function applyFilters(SalesFilters $filters): self
    {
        return $this
            ->betweenDates($filters->saleDateFrom, $filters->saleDateTo)
            ->withPromotion($filters->promotion)
            ->forStore($filters->storeId);
    }

    /**
     * Filtra pelo intervalo de datas de venda (limites inclusivos, ignorados quando null).
     */
    public function betweenDates(?string $from, ?string $to): self
    {
        if (! empty($from)) {
            $this->query->whereDate('sale_date', '>=', $from);
        }

        if (! empty($to)) {
            $this->query->whereDate('sale_date', '<=', $to);
        }

        return $this;
    }

    /**
     * Filtra por loja (ignorado quando null/vazio).
     */
    public function forStore(?string $storeId): self
    {
        if (! empty($storeId)) {
            $this->query->where('store_id', $storeId);
        }

        return $this;
    }

    /**
     * Filtra por indicador de promoção (ignorado quando null/vazio).
     */
    public function withPromotion(?string $promotion): self
    {
        if (! empty($promotion)) {
            $this->query->where('promotion', $promotion);
        }

        return $this;
    }

    /**
     * Expõe o Builder subjacente para reuso (ex.: paginar a listagem de vendas
     * a partir da mesma base de casamento/filtro).
     *
     * @return Builder<Sale>
     */
    public function builder(): Builder
    {
        return $this->query;
    }

    /**
     * Executa o SELECT agregado canônico e retorna a linha bruta (ou null se sem vendas).
     */
    public function aggregate(): ?object
    {
        return (clone $this->query)->selectRaw(self::AGGREGATE_SELECT)->first();
    }

    /**
     * Executa a agregação e devolve o value object com as métricas derivadas.
     */
    public function summary(): SalesSummary
    {
        return SalesSummary::fromAggregate($this->aggregate());
    }
}

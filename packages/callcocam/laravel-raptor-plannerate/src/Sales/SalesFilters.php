<?php

namespace Callcocam\LaravelRaptorPlannerate\Sales;

use Illuminate\Http\Request;

/**
 * DTO de filtros aplicados às consultas de vendas.
 *
 * Centraliza os critérios de período/promoção/loja que antes eram lidos de forma
 * repetida (com nomes de query string diferentes) em ProductController::sales
 * (sale_date_from/to) e ProductSalesController::summary (start_date/end_date).
 */
class SalesFilters
{
    /**
     * @param  string|null  $saleDateFrom  Data inicial do período (YYYY-MM-DD) ou null
     * @param  string|null  $saleDateTo  Data final do período (YYYY-MM-DD) ou null
     * @param  string|null  $promotion  Indicador de promoção ('S'/'N') ou null para ignorar
     * @param  string|null  $storeId  ULID da loja para filtrar ou null para todas
     */
    public function __construct(
        public readonly ?string $saleDateFrom = null,
        public readonly ?string $saleDateTo = null,
        public readonly ?string $promotion = null,
        public readonly ?string $storeId = null,
    ) {}

    /**
     * Constrói os filtros a partir da página de vendas do produto
     * (query string: sale_date_from, sale_date_to, promotion, store_id).
     */
    public static function fromProductSalesRequest(Request $request): self
    {
        return new self(
            saleDateFrom: self::nullableString($request->query('sale_date_from')),
            saleDateTo: self::nullableString($request->query('sale_date_to')),
            promotion: self::nullableString($request->query('promotion')),
            storeId: self::nullableString($request->query('store_id')),
        );
    }

    /**
     * Constrói os filtros a partir do período do planograma no editor
     * (query string: start_date, end_date). Promoção e loja não se aplicam aqui.
     */
    public static function fromPlanogramRequest(Request $request): self
    {
        return new self(
            saleDateFrom: self::nullableString($request->query('start_date')),
            saleDateTo: self::nullableString($request->query('end_date')),
        );
    }

    /**
     * Indica se algum filtro de período foi informado.
     */
    public function hasPeriod(): bool
    {
        return $this->saleDateFrom !== null || $this->saleDateTo !== null;
    }

    /**
     * Normaliza um valor de query string para string não-vazia ou null.
     */
    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}

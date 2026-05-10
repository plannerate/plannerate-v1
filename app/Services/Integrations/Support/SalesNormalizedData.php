<?php

namespace App\Services\Integrations\Support;

class SalesNormalizedData
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $codigoErp,
        public readonly ?string $ean,
        public readonly string $saleDate,
        public readonly ?string $promotion,
        public readonly ?float $totalSaleQuantity,
        public readonly ?float $totalSaleValue,
        public readonly ?float $salePrice,
        public readonly ?float $acquisitionCost,
        public readonly ?float $totalProfitMargin,
        public readonly ?float $valorImpostos,
        public readonly ?float $custoMedioLoja,
        public readonly ?string $storeDocument,
        public readonly array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $raw
     */
    public static function fromMapped(array $mapped, array $raw, ?string $fallbackStoreDocument): ?self
    {
        $codigoErp = self::toStringOrNull($mapped['codigo_erp'] ?? null);
        $saleDate = self::toStringOrNull($mapped['sale_date'] ?? null);

        if ($codigoErp === null || $saleDate === null) {
            return null;
        }

        return new self(
            codigoErp: $codigoErp,
            ean: self::toStringOrNull($mapped['ean'] ?? null),
            saleDate: $saleDate,
            promotion: self::toStringOrNull($mapped['promotion'] ?? null),
            totalSaleQuantity: self::toFloatOrNull($mapped['total_sale_quantity'] ?? null),
            totalSaleValue: self::toFloatOrNull($mapped['total_sale_value'] ?? null),
            salePrice: self::toFloatOrNull($mapped['sale_price'] ?? null),
            acquisitionCost: self::toFloatOrNull($mapped['acquisition_cost'] ?? null),
            totalProfitMargin: self::toFloatOrNull($mapped['total_profit_margin'] ?? null),
            valorImpostos: self::toFloatOrNull($mapped['valor_impostos'] ?? null),
            custoMedioLoja: self::toFloatOrNull($mapped['custo_medio_loja'] ?? null),
            storeDocument: self::normalizeStoreDocument(
                self::toStringOrNull($mapped['store_document'] ?? null) ?? $fallbackStoreDocument
            ),
            raw: $raw,
        );
    }

    private static function toStringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private static function toFloatOrNull(mixed $value): ?float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        return null;
    }

    private static function normalizeStoreDocument(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', $value) ?? '';

        return $normalized !== '' ? $normalized : null;
    }
}

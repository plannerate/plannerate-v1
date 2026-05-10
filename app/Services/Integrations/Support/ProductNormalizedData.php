<?php

namespace App\Services\Integrations\Support;

class ProductNormalizedData
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $codigoErp,
        public readonly string $ean,
        public readonly ?string $name,
        public readonly ?string $brand,
        public readonly ?string $subbrand,
        public readonly ?string $description,
        public readonly ?string $auxiliaryDescription,
        public readonly ?string $additionalInformation,
        public readonly ?string $reference,
        public readonly ?string $color,
        public readonly ?string $fragrance,
        public readonly ?string $flavor,
        public readonly ?string $packagingType,
        public readonly ?string $packagingSize,
        public readonly ?string $measurementUnit,
        public readonly ?string $unitMeasure,
        public readonly ?string $sortimentAttribute,
        public readonly ?float $currentStock,
        public readonly ?string $lastPurchaseDate,
        public readonly ?string $salesStatus,
        public readonly array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $raw
     */
    public static function fromMapped(array $mapped, array $raw): ?self
    {
        $codigoErp = self::toStringOrNull($mapped['codigo_erp'] ?? null);
        $ean = self::toStringOrNull($mapped['ean'] ?? null);

        if ($codigoErp === null || $ean === null) {
            return null;
        }

        return new self(
            codigoErp: $codigoErp,
            ean: $ean,
            name: self::toStringOrNull($mapped['name'] ?? null),
            brand: self::toStringOrNull($mapped['brand'] ?? null),
            subbrand: self::toStringOrNull($mapped['subbrand'] ?? null),
            description: self::toStringOrNull($mapped['description'] ?? null),
            auxiliaryDescription: self::toStringOrNull($mapped['auxiliary_description'] ?? null),
            additionalInformation: self::toStringOrNull($mapped['additional_information'] ?? null),
            reference: self::toStringOrNull($mapped['reference'] ?? null),
            color: self::toStringOrNull($mapped['color'] ?? null),
            fragrance: self::toStringOrNull($mapped['fragrance'] ?? null),
            flavor: self::toStringOrNull($mapped['flavor'] ?? null),
            packagingType: self::toStringOrNull($mapped['packaging_type'] ?? null),
            packagingSize: self::toStringOrNull($mapped['packaging_size'] ?? null),
            measurementUnit: self::toStringOrNull($mapped['measurement_unit'] ?? null),
            unitMeasure: self::toStringOrNull($mapped['unit_measure'] ?? null),
            sortimentAttribute: self::toStringOrNull($mapped['sortiment_attribute'] ?? null),
            currentStock: self::toFloatOrNull($mapped['current_stock'] ?? null),
            lastPurchaseDate: self::toStringOrNull($mapped['last_purchase_date'] ?? null),
            salesStatus: self::toStringOrNull($mapped['sales_status'] ?? null),
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
}


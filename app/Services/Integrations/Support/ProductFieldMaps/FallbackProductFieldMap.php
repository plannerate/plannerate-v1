<?php

namespace App\Services\Integrations\Support\ProductFieldMaps;

class FallbackProductFieldMap implements ProductFieldMap
{
    public function provider(): string
    {
        return 'default';
    }

    public function fields(): array
    {
        return [
            'codigo_erp' => ['codigo_erp'],
            'ean' => ['ean'],
            'name' => ['name', 'nome', 'descricao'],
        ];
    }

    public function passesValidation(array $mapped, array $raw): bool
    {
        return true;
    }
}

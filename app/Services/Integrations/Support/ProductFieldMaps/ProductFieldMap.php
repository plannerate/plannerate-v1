<?php

namespace App\Services\Integrations\Support\ProductFieldMaps;

interface ProductFieldMap
{
    public function provider(): string;

    /**
     * @return array<string, list<string>|callable(array<string, mixed>): mixed>
     */
    public function fields(): array;

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $raw
     */
    public function passesValidation(array $mapped, array $raw): bool;
}

<?php

namespace App\Services\Integrations\Mappers;

interface ProductsResponseMapper
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function mapMany(array $items): array;
}

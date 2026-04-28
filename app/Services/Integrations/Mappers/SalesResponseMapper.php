<?php

namespace App\Services\Integrations\Mappers;

interface SalesResponseMapper
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function mapMany(array $items): array;
}

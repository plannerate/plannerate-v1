<?php

namespace App\Services\Integrations\GesCooper;

use RuntimeException;

class GesCooperEndpoints
{
    /**
     * @var array<string, string>
     */
    private const ENDPOINTS = [
        'token' => 'v1/Token',
        'products' => 'Produtos/Produtos',
    ];

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return self::ENDPOINTS;
    }

    public function get(string $key): string
    {
        if (! array_key_exists($key, self::ENDPOINTS)) {
            throw new RuntimeException('Endpoint GesCooper nao mapeado: '.$key);
        }

        return self::ENDPOINTS[$key];
    }
}

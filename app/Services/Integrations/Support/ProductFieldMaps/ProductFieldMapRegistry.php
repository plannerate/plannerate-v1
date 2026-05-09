<?php

namespace App\Services\Integrations\Support\ProductFieldMaps;

class ProductFieldMapRegistry
{
    /**
     * @var array<string, ProductFieldMap>
     */
    private array $maps;

    public function __construct()
    {
        $providers = [
            new SysmoProductFieldMap,
            new GescooperProductFieldMap,
        ];

        $this->maps = [];
        foreach ($providers as $provider) {
            $this->maps[$provider->provider()] = $provider;
        }
    }

    public function resolve(string $provider): ProductFieldMap
    {
        return $this->maps[$provider] ?? new FallbackProductFieldMap;
    }
}

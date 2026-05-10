<?php

namespace App\Services\Integrations\Support\SalesFieldMaps;

class SalesFieldMapRegistry
{
    /**
     * @var array<string, SalesFieldMap>
     */
    private array $maps;

    public function __construct()
    {
        $providers = [
            new SysmoSalesFieldMap,
            new GescooperSalesFieldMap,
        ];

        $this->maps = [];
        foreach ($providers as $provider) {
            $this->maps[$provider->provider()] = $provider;
        }
    }

    public function resolve(string $provider): SalesFieldMap
    {
        return $this->maps[$provider] ?? new FallbackSalesFieldMap;
    }
}

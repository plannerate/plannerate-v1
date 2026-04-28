<?php

namespace App\Services\Integrations\Sysmo;

use RuntimeException;

class SysmoEndpoints
{
    /**
     * Endpoints disponiveis na API Sysmo.
     *
     * @var array<string, string>
     */
    private const ENDPOINTS = [
        'products' => 'sysmo-integrador-api/api/integradorService/hubprodutos.listar_produtos',
        'product' => 'sysmo-integrador-api/api/integradorService/hubprodutos.consultar_produto',
        'sales' => 'sysmo-integrador-api/api/integradorService/hubvendas.vendas_produtos',
        'categories' => 'sysmo-integrador-api/api/integradorService/hubprodutos.listar_produtos',
        'suppliers' => 'sysmo-integrador-api/api/integradorService/hubfornecedores.listar_fornecedores',
        'customers' => 'sysmo-integrador-api/api/integradorService/hubclientes.listar_clientes',
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
            throw new RuntimeException('Endpoint Sysmo nao mapeado.');
        }

        return self::ENDPOINTS[$key];
    }
}

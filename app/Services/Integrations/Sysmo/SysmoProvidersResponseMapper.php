<?php

namespace App\Services\Integrations\Sysmo;

use App\Services\Integrations\Mappers\ProvidersResponseMapper;
use App\Services\Integrations\Sysmo\Concerns\PicksSysmoMappedValues;

class SysmoProvidersResponseMapper implements ProvidersResponseMapper
{
    use PicksSysmoMappedValues;

    public function mapMany(array $items): array
    {
        return array_map(
            fn (array $item): array => $this->mapItem($item),
            $items,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function mapItem(array $item): array
    {
        /** @var array<string, mixed> $endereco */
        $endereco = is_array($item['endereco'] ?? null) ? $item['endereco'] : [];

        return [
            'code' => $this->pickString($item, ['codigo']),
            'name' => $this->pickString($item, ['razao_social']),
            'cnpj' => $this->pickString($item, ['cpf_cnpj']),
            'description' => $this->pickString($item, ['fantasia']),
            'address_street' => $this->pickString($endereco, ['rua']),
            'address_district' => $this->pickString($endereco, ['bairro']),
            'address_city' => $this->pickString($endereco, ['municipio']),
            'address_state' => $this->pickString($endereco, ['uf']),
            'address_zip_code' => $this->pickString($endereco, ['cep']),
            'address_complement' => $this->pickString($endereco, ['complemento']),
            'raw' => $item,
        ];
    }
}

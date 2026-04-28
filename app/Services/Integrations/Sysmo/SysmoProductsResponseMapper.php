<?php

namespace App\Services\Integrations\Sysmo;

use App\Services\Integrations\Mappers\ProductsResponseMapper;

class SysmoProductsResponseMapper implements ProductsResponseMapper
{
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
        $primarySupplier = $this->extractPrimarySupplier($item);
        $primaryGtin = $this->extractPrimaryGtin($item);

        return [
            'external_id' => $this->normalizeCodigoErp(
                $this->pickString($item, ['produto', 'id', 'codigo', 'codigo_produto', 'produto_id'])
            ),
            'ean' => $this->normalizeGtin(
                $primaryGtin ?? $this->pickString($item, ['ean', 'codigo_barras', 'gtin'])
            ),
            'brand' => $this->pickString($item, ['marca', 'nome_marca']),
            'name' => $this->pickString($item, ['descricao', 'nome', 'nome_produto']),
            'department_code' => $this->pickString($item, ['departamento']),
            'department_description' => $this->pickString($item, ['departamento_descricao']),
            'category_code' => $this->pickString($item, ['categoria']),
            'category_description' => $this->pickString($item, ['categoria_descricao', 'nome_categoria']),
            'supplier_code' => $this->pickString($primarySupplier, ['codigo']),
            'supplier_name' => $this->pickString($primarySupplier, ['razao_social', 'fantasia']),
            'supplier_document' => $this->pickString($primarySupplier, ['cpf_cnpj']),
            'data_sumarizacao' => $this->pickString($item, ['data_sumarizacao']),
            'data_ultima_alteracao' => $this->pickString($item, ['data_ultima_alteracao']),
            'preco_normal' => $this->pickFloat($item, ['preco', 'preco_normal']),
            'preco_promocional' => $this->pickFloat($item, ['preco_promocional']),
            'custo_medio_geral' => $this->pickFloat($item, ['custo_medio_geral']),
            'current_stock' => $this->pickFloatFromPaths($item, ['estoque.disponivel']),
            'status' => $this->pickString($item, ['status', 'situacao']),
            'unit' => $this->pickString($item, ['unidade', 'un']),
            'raw' => $item,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function extractPrimarySupplier(array $item): array
    {
        $suppliers = $item['fornecedores'] ?? null;

        if (! is_array($suppliers)) {
            return [];
        }

        foreach ($suppliers as $supplier) {
            if (! is_array($supplier)) {
                continue;
            }

            if (($supplier['principal'] ?? null) === 'S') {
                return $supplier;
            }
        }

        foreach ($suppliers as $supplier) {
            if (is_array($supplier)) {
                return $supplier;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function extractPrimaryGtin(array $item): ?string
    {
        $gtins = $item['gtins']['completo'] ?? null;

        if (! is_array($gtins)) {
            return null;
        }

        foreach ($gtins as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (($entry['principal'] ?? null) === 'S') {
                return $this->pickString($entry, ['gtin']);
            }
        }

        foreach ($gtins as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $gtin = $this->pickString($entry, ['gtin']);
            if ($gtin !== null) {
                return $gtin;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $keys
     */
    private function pickString(array $item, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $item[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_numeric($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $keys
     */
    private function pickFloat(array $item, array $keys): ?float
    {
        foreach ($keys as $key) {
            $value = $item[$key] ?? null;

            if (is_numeric($value)) {
                return (float) $value;
            }

            if (is_string($value) && is_numeric(str_replace(',', '.', $value))) {
                return (float) str_replace(',', '.', $value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $paths
     */
    private function pickFloatFromPaths(array $item, array $paths): ?float
    {
        foreach ($paths as $path) {
            $value = data_get($item, $path);

            if (is_numeric($value)) {
                return (float) $value;
            }

            if (is_string($value) && is_numeric(str_replace(',', '.', $value))) {
                return (float) str_replace(',', '.', $value);
            }
        }

        return null;
    }

    private function normalizeCodigoErp(?string $codigoErp): ?string
    {
        if ($codigoErp === null) {
            return null;
        }

        $codigoErp = trim($codigoErp);

        $invalidValues = ['N/A', 'n/a', 'NA', 'na', 'NULL', 'null', 'NONE', 'none', '-', ''];

        if (in_array($codigoErp, $invalidValues, true)) {
            return null;
        }

        return $codigoErp;
    }

    private function normalizeGtin(?string $gtin): ?string
    {
        if ($gtin === null) {
            return null;
        }

        $digitsOnly = preg_replace('/\D+/', '', $gtin) ?? '';

        if ($digitsOnly === '' || strlen($digitsOnly) > 13) {
            return null;
        }

        return $digitsOnly;
    }
}

<?php

namespace App\Services\Files\Imports\Connections;

use App\Models\Category;
use App\Models\EanReference;
use App\Services\Files\Imports\ImportExecutionResult;
use Illuminate\Support\Facades\Log;

class EanReferenceByEanConnection implements CategoryImportConnection
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function connect(
        string $tenantId,
        ?string $userId,
        Category $leafCategory,
        array $row,
        ImportExecutionResult $result
    ): void {
        unset($tenantId, $userId, $result);

        $ean = EanReference::normalizeEan((string) ($row['ean'] ?? ''));
        if ($ean === '') {
            return;
        }

        Log::warning('EanReferenceByEanConnection writing reference', [
            'ean' => $ean,
            'connection' => 'landlord',
            'leaf_category_id' => $leafCategory->id,
            'leaf_category_name' => $leafCategory->name,
            'source' => self::class,
        ]);

        EanReference::on('landlord')->updateOrCreate(
            [
                'ean' => $ean,
            ],
            [
                'category_id' => $leafCategory->id,
                'category_name' => $leafCategory->name,
                'category_slug' => $leafCategory->slug,
                'reference_description' => $this->firstValue($row, ['descricao_atual', 'descrição_atual']),
                'brand' => $this->firstValue($row, ['marca_obrigatorio', 'marca']),
                'subbrand' => $this->firstValue($row, ['submarca']),
                'packaging_type' => $this->firstValue($row, ['tipo_de_embalagem_obrigatorio', 'tipo_de_embalagem']),
                'packaging_size' => $this->firstValue($row, [
                    'tamanho_ou_quantidade_da_embalagem_obrigatorio',
                    'tamanho_ou_quantidade_da_embalagem',
                    'tamanho_ou_quantidade_da',
                ]),
                'measurement_unit' => $this->firstValue($row, ['unidade_de_medida_obrigatorio', 'unidade_de_medida']),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $candidates
     */
    private function firstValue(array $row, array $candidates): ?string
    {
        foreach ($candidates as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        foreach ($candidates as $prefix) {
            foreach ($row as $key => $value) {
                if (! str_starts_with((string) $key, $prefix)) {
                    continue;
                }

                $normalizedValue = trim((string) ($value ?? ''));
                if ($normalizedValue !== '') {
                    return $normalizedValue;
                }
            }
        }

        return null;
    }
}

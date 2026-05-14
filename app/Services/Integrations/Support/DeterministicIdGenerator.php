<?php

namespace App\Services\Integrations\Support;

/**
 * Gera IDs determinísticos para registros importados.
 *
 * Usa os campos de unique_by do path config para garantir
 * que o mesmo registro sempre gera o mesmo ID, independente
 * de quantas vezes for importado.
 */
class DeterministicIdGenerator
{
    /**
     * Gera um ID determinístico a partir dos campos unique_by do registro mapeado.
     *
     * @param  array<string, mixed>  $record  Registro já mapeado pelo field_map
     * @param  array<string, mixed>  $pathConfig  Config do path (unique_by, include_store_in_id, id_prefix)
     */
    public function fromRecord(
        string $tenantId,
        string $integrationId,
        array $record,
        array $pathConfig,
        ?string $storeId,
    ): string {
        $uniqueBy = (array) data_get($pathConfig, 'unique_by', []);
        $includeStore = (bool) data_get($pathConfig, 'include_store_in_id', false);
        $prefix = (string) data_get($pathConfig, 'id_prefix', 'I1');

        $parts = [$tenantId, $integrationId];

        if ($includeStore) {
            $parts[] = $storeId ?? 'sem-loja';
        }

        foreach ($uniqueBy as $field) {
            $value = (string) ($record[$field] ?? '');
            $parts[] = preg_replace('/[^A-Za-z0-9]/', '', $value) ?: $value;
        }

        $hash = hash('sha256', implode('|', $parts));

        return $prefix.strtoupper(substr($hash, 0, 24));
    }

    public function productIdFromEan(string $tenantId, string $ean): string
    {
        $prefix = 'P1';
        $hash = hash('sha256', $tenantId.'|'.$ean);

        return $prefix.strtoupper(substr($hash, 0, 24));
    }

    public function productIdFromReference(string $tenantId, string $reference): string
    {
        $prefix = 'P1';
        $hash = hash('sha256', $tenantId.'|'.$reference);

        return $prefix.strtoupper(substr($hash, 0, 24));
    }
}

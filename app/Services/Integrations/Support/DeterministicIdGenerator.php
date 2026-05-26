<?php

namespace App\Services\Integrations\Support;

use Symfony\Component\Uid\Ulid;

/**
 * Gera IDs determinísticos para registros importados.
 *
 * Usa os campos de unique_by do path config para garantir
 * que o mesmo registro sempre gera o mesmo ID, independente
 * de quantas vezes for importado.
 *
 * O ID final é um ULID estruturalmente válido (Crockford base32, 26 chars,
 * primeiro caractere entre 0-7), derivado do SHA-256 das partes que identificam
 * o registro. Por ser derivado de hash, é determinístico — o mesmo conjunto de
 * partes sempre produz o mesmo ULID — porém não carrega timestamp real e não é
 * ordenável cronologicamente. Ser um ULID válido garante que passe na regra de
 * validação `ulid` aplicada a chaves como product_id, category_id e store_id.
 */
class DeterministicIdGenerator
{
    /**
     * Gera um ID determinístico a partir dos campos unique_by do registro mapeado.
     *
     * Por padrão, inclui o integrationId no hash para isolar registros de integrações
     * diferentes. Para recursos compartilhados entre integrações (ex: produtos, cujo ID
     * depende apenas de tenant + EAN), defina `include_integration_in_id: false` no
     * path config da integração.
     *
     * @param  array<string, mixed>  $record  Registro já mapeado pelo field_map
     * @param  array<string, mixed>  $pathConfig  Config do path (unique_by, include_store_in_id, include_integration_in_id, id_prefix)
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
        $includeIntegration = (bool) data_get($pathConfig, 'include_integration_in_id', true);

        $parts = [$tenantId];

        if ($includeIntegration) {
            $parts[] = $integrationId;
        }

        if ($includeStore) {
            $parts[] = $storeId ?? 'sem-loja';
        }

        foreach ($uniqueBy as $field) {
            $value = (string) ($record[$field] ?? '');
            $parts[] = preg_replace('/[^A-Za-z0-9]/', '', $value) ?: $value;
        }

        return $this->ulidFromParts(implode('|', $parts));
    }

    public function productIdFromEan(string $tenantId, string $ean): string
    {
        return $this->ulidFromParts($tenantId.'|'.$ean);
    }

    public function productIdFromReference(string $tenantId, string $reference): string
    {
        return $this->ulidFromParts($tenantId.'|'.$reference);
    }

    /**
     * Codifica a string identificadora em um ULID válido e determinístico.
     *
     * Usa os 16 primeiros bytes do SHA-256 (128 bits) como payload do ULID.
     * Como o valor de 128 bits ocupa apenas 128 dos 130 bits do encoding base32,
     * o primeiro caractere fica sempre entre 0-7, satisfazendo o formato ULID.
     */
    private function ulidFromParts(string $input): string
    {
        $bytes = substr(hash('sha256', $input, true), 0, 16);

        return (string) Ulid::fromBinary($bytes);
    }
}

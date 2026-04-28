<?php

namespace App\Services\Integrations\Support;

class DeterministicIdGenerator
{
    public function productId(string $tenantId, ?string $ean, ?string $codigoErp): string
    {
        $identity = $ean ?? $codigoErp ?? 'sem-chave';
        $hash = md5($tenantId.'|'.$identity);

        return 'P1'.strtoupper(substr($hash, 0, 24));
    }

    public function saleId(
        string $tenantId,
        string $integrationId,
        ?string $storeId,
        string $codigoErp,
        string $saleDate,
        ?string $promotion,
    ): string {
        $uniqueKey = implode('|', [
            $tenantId,
            $integrationId,
            $storeId ?? 'sem-loja',
            preg_replace('/[^A-Za-z0-9]/', '', $codigoErp) ?? $codigoErp,
            preg_replace('/[^0-9]/', '', $saleDate) ?? $saleDate,
            strtoupper($promotion ?? 'N'),
        ]);

        $hash = hash('sha256', $uniqueKey);

        return 'S1'.strtoupper(substr($hash, 0, 24));
    }
}

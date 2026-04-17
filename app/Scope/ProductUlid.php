<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Scope;

use Callcocam\LaravelRaptor\Support\Import\Contracts\GeneratesImportId;

class ProductUlid implements GeneratesImportId
{
    public function generate(array $row): string
    {
        $ean = $row['ean'] ?? null;
        $tenantId = $row['tenant_id'] ?? null;

        if (! $tenantId) {
            throw new \InvalidArgumentException('tenant_id é obrigatório para gerar o ULID do produto.');
        }

        if (! $ean) {
            throw new \InvalidArgumentException('ean é obrigatório para gerar o ULID do produto.');
        }
        // Chave única baseada na constraint única da tabela products
        $uniqueKey = $tenantId.'|'.$ean;

        // Gerar hash determinístico que sempre produz o mesmo resultado
        $hash = md5($uniqueKey);

        // Criar ID determinístico baseado APENAS no hash (sem time)
        // Usa prefixo fixo + hash para garantir formato ULID de 26 chars
        $prefix = 'T1'; // Prefixo fixo para products (T=Tenant, 1=versão)
        $hashComponent = strtoupper(substr($hash, 0, 24)); // 24 chars restantes

        return $prefix.$hashComponent;
    }
}

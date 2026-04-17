<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\DTOs\Sysmo;

use App\DTOs\BaseDTOProcessor;

/**
 * DTO para atualização de estoque via API Sysmo
 *
 * Extrai o campo "estoque.disponivel" de cada produto
 * e retorna os dados necessários para atualizar current_stock na tabela products.
 */
class StockDTO extends BaseDTOProcessor
{
    public function process(array $data, array $params = []): array
    {
        $this->params = $params;

        return $this->processStockItem($data) ?? [];
    }

    private function processStockItem(array $product): ?array
    {
        $ean = $this->getProcessedGtin('gtins', $product, null);

        if (empty($ean) || strlen($ean) > 13) {
            return null;
        }

        $stockData = $this->getProcessedValue('estoque', $product, []);

        if (empty($stockData)) {
            return null;
        }

        $disponivel = (float) ($stockData['disponivel'] ?? 0);

        return [
            'ean' => $ean,
            'current_stock' => $disponivel,
        ];
    }
}

<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate\IAGenerate;

use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\IAGenerate\IAGenerateResultDTO;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de Parsing de Respostas da IA
 * Responsável por converter resposta da LLM em estrutura utilizável
 */
class IAResponseParserService
{
    /**
     * Parsear resposta JSON da IA
     */
    public function parseResponse(
        string $response,
        float $executionTime,
        int $tokensUsed = 0,
        array $shelfMetadata = []
    ): IAGenerateResultDTO {
        try {
            // Limpar resposta (remover markdown code blocks se houver)
            $cleanedResponse = $this->cleanResponse($response);

            // Decodificar JSON com fallback para respostas truncadas/sujas
            $data = $this->decodeJsonWithFallback($cleanedResponse);

            // Validar estrutura obrigatória
            $this->validateStructure($data);

            // Extrair dados
            $reasoning = $data['reasoning'] ?? 'Sem explicação fornecida';
            $confidence = (float) ($data['confidence'] ?? 0.0);
            $allocation = $data['allocation'] ?? [];
            $summary = $data['summary'] ?? [];

            // Construir estrutura de prateleiras
            $shelves = $this->buildShelvesStructure($allocation);

            // Totais
            $totalAllocated = $summary['total_allocated'] ?? count($this->flattenProducts($allocation));
            $totalUnallocated = $summary['total_unallocated'] ?? 0;

            // Metadata adicional
            $metadata = [
                'avg_occupancy' => $summary['avg_occupancy'] ?? 0,
                'shelves_used' => $summary['shelves_used'] ?? count($shelves),
                'warnings' => $summary['warnings'] ?? [],
                'recommendations' => $summary['recommendations'] ?? [],
            ];

            // Validar qualidade da geração e adicionar warnings/recomendações
            $this->validateGenerationQuality(
                $allocation,
                $shelves,
                $metadata,
                $totalAllocated,
                $totalUnallocated,
                $shelfMetadata
            );

            return IAGenerateResultDTO::create(
                totalAllocated: $totalAllocated,
                totalUnallocated: $totalUnallocated,
                shelves: $shelves,
                metadata: $metadata,
                reasoning: $reasoning,
                confidence: $confidence,
                tokensUsed: $tokensUsed,
                executionTime: $executionTime,
            );

        } catch (\Exception $e) {
            Log::error('❌ Erro ao parsear resposta da IA', [
                'error' => $e->getMessage(),
                'response_preview' => substr($response, 0, 500),
            ]);

            throw new \RuntimeException('Erro ao processar resposta da IA: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Limpar resposta removendo markdown code blocks
     */
    protected function cleanResponse(string $response): string
    {
        // Remover ```json e ``` se existirem
        $response = preg_replace('/^```json\s*/m', '', $response);
        $response = preg_replace('/^```\s*/m', '', $response);

        // Extrair apenas o conteúdo JSON (entre { e })
        if (preg_match('/\{[\s\S]*\}/u', $response, $matches)) {
            $response = $matches[0];
        }

        // Remover caracteres de controle problemáticos dentro de strings JSON
        // Substituir newlines literais por espaços dentro de strings
        $response = preg_replace_callback(
            '/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/s',
            function ($matches) {
                $str = $matches[1];
                // Substituir newlines, tabs e outros caracteres de controle por espaços
                $str = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $str);
                // Remover espaços múltiplos
                $str = preg_replace('/\s+/', ' ', $str);

                return '"'.trim($str).'"';
            },
            $response
        );

        // Tentar reparar JSON truncado (fechando arrays e objetos abertos)
        $response = $this->repairTruncatedJson(trim($response));

        return $response;
    }

    /**
     * Decodificar JSON com tentativas de fallback para respostas da IA malformadas
     */
    protected function decodeJsonWithFallback(string $json): array
    {
        // 1) Tentativa direta
        $data = json_decode($json, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        $firstError = json_last_error_msg();

        // 2) Remover controles não-whitespace que quebram JSON
        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $json) ?? $json;
        $data = json_decode($sanitized, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            Log::warning('⚠️ JSON da IA recuperado após sanitização de controles', [
                'first_error' => $firstError,
            ]);

            return $data;
        }

        $secondError = json_last_error_msg();

        // 3) Fallback agressivo: remover TODOS controles (incluindo \n/\t)
        $aggressive = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $sanitized) ?? $sanitized;
        $aggressive = preg_replace('/\s+/', ' ', $aggressive) ?? $aggressive;
        $data = json_decode(trim($aggressive), true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            Log::warning('⚠️ JSON da IA recuperado com fallback agressivo', [
                'first_error' => $firstError,
                'second_error' => $secondError,
            ]);

            return $data;
        }

        throw new \RuntimeException('Erro ao decodificar JSON: '.json_last_error_msg());
    }

    /**
     * Tentar reparar JSON truncado fechando estruturas abertas
     */
    protected function repairTruncatedJson(string $json): string
    {
        // Contar abre/fecha de { } e [ ]
        $openBraces = substr_count($json, '{');
        $closeBraces = substr_count($json, '}');
        $openBrackets = substr_count($json, '[');
        $closeBrackets = substr_count($json, ']');

        // Se o JSON parece completo, retornar
        if ($openBraces === $closeBraces && $openBrackets === $closeBrackets) {
            return $json;
        }

        Log::warning('⚠️ JSON truncado detectado, tentando reparar', [
            'open_braces' => $openBraces,
            'close_braces' => $closeBraces,
            'open_brackets' => $openBrackets,
            'close_brackets' => $closeBrackets,
        ]);

        // Remover última vírgula pendente e strings incompletas
        $json = preg_replace('/,\s*$/', '', $json);
        $json = preg_replace('/"[^"]*$/', '""', $json);

        // Fechar arrays e objetos na ordem correta
        // Analisar estrutura para fechar corretamente
        $stack = [];
        $inString = false;
        $escape = false;

        for ($i = 0; $i < strlen($json); $i++) {
            $char = $json[$i];

            if ($escape) {
                $escape = false;

                continue;
            }

            if ($char === '\\' && $inString) {
                $escape = true;

                continue;
            }

            if ($char === '"') {
                $inString = ! $inString;

                continue;
            }

            if ($inString) {
                continue;
            }

            if ($char === '{' || $char === '[') {
                $stack[] = $char;
            } elseif ($char === '}') {
                if (! empty($stack) && end($stack) === '{') {
                    array_pop($stack);
                }
            } elseif ($char === ']') {
                if (! empty($stack) && end($stack) === '[') {
                    array_pop($stack);
                }
            }
        }

        // Fechar estruturas abertas na ordem reversa
        while (! empty($stack)) {
            $open = array_pop($stack);
            $json .= ($open === '{') ? '}' : ']';
        }

        return $json;
    }

    /**
     * Validar estrutura mínima do JSON
     */
    protected function validateStructure(array $data): void
    {
        if (! isset($data['allocation'])) {
            throw new \RuntimeException('Campo "allocation" é obrigatório na resposta');
        }

        if (! is_array($data['allocation'])) {
            throw new \RuntimeException('Campo "allocation" deve ser um array');
        }
    }

    /**
     * Construir estrutura de prateleiras a partir da alocação
     */
    protected function buildShelvesStructure(array $allocation): array
    {
        $shelves = [];

        foreach ($allocation as $shelfAllocation) {
            $shelfId = $shelfAllocation['shelf_id'] ?? null;

            if (! $shelfId) {
                Log::warning('⚠️ Alocação sem shelf_id ignorada', ['allocation' => $shelfAllocation]);

                continue;
            }

            $products = [];
            foreach ($shelfAllocation['products'] ?? [] as $productData) {
                $products[] = [
                    'product_id' => $productData['product_id'] ?? null,
                    'facings' => (int) ($productData['facings'] ?? 1),
                    'position_x' => (float) ($productData['position_x'] ?? 0),
                    'justification' => $productData['justification'] ?? '',
                ];
            }

            $shelves[] = [
                'shelf_id' => $shelfId,
                'products' => $products,
                'total_products' => count($products),
            ];
        }

        return $shelves;
    }

    /**
     * Achatar produtos de todas as prateleiras
     */
    protected function flattenProducts(array $allocation): array
    {
        $allProducts = [];

        foreach ($allocation as $shelfAllocation) {
            $products = $shelfAllocation['products'] ?? [];
            foreach ($products as $product) {
                if (isset($product['product_id'])) {
                    $allProducts[] = $product['product_id'];
                }
            }
        }

        return $allProducts;
    }

    /**
     * Validar qualidade da geração e adicionar warnings/recomendações
     */
    protected function validateGenerationQuality(
        array $allocation,
        array $shelves,
        array &$metadata,
        int $totalAllocated,
        int $totalUnallocated,
        array $shelfMetadata = []
    ): void {
        $warnings = $metadata['warnings'] ?? [];
        $recommendations = $metadata['recommendations'] ?? [];

        // 1. Validar ocupação média
        $avgOccupancy = (float) ($metadata['avg_occupancy'] ?? 0);
        if ($avgOccupancy < 70) {
            $warnings[] = "⚠️ Ocupação média muito baixa ({$avgOccupancy}%) - meta: 75-85%";
            $recommendations[] = 'Aumente facings dos produtos existentes para atingir 75-85% de ocupação';

            Log::warning('⚠️ Ocupação abaixo do ideal', [
                'avg_occupancy' => $avgOccupancy,
                'target' => '75-85%',
            ]);
        }

        // 2. Validar prateleiras vazias
        $shelvesUsed = count($shelves);
        $emptyShelvesCount = 0;

        foreach ($shelves as $shelf) {
            if (empty($shelf['products'])) {
                $emptyShelvesCount++;
            }
        }

        if ($emptyShelvesCount > 0) {
            $warnings[] = "⚠️ {$emptyShelvesCount} prateleira(s) vazia(s) - todas devem ser utilizadas";
            $recommendations[] = 'Redistribua produtos para ocupar todas as prateleiras disponíveis';

            Log::warning('⚠️ Prateleiras vazias detectadas', [
                'empty_shelves' => $emptyShelvesCount,
                'total_shelves' => $shelvesUsed,
            ]);
        }

        // 3. Validar produtos não alocados
        if ($totalUnallocated > 0) {
            $percentUnallocated = round(($totalUnallocated / ($totalAllocated + $totalUnallocated)) * 100, 1);

            if ($percentUnallocated > 10) {
                $warnings[] = "⚠️ {$totalUnallocated} produtos não alocados ({$percentUnallocated}%)";
                $recommendations[] = 'Considere aumentar número de prateleiras ou reduzir sortimento';

                Log::warning('⚠️ Muitos produtos não alocados', [
                    'unallocated' => $totalUnallocated,
                    'percent' => $percentUnallocated,
                ]);
            }
        }

        // 4. Validar distribuição de produtos por prateleira
        $productsPerShelf = array_map(fn ($shelf) => count($shelf['products'] ?? []), $shelves);
        $maxProducts = ! empty($productsPerShelf) ? max($productsPerShelf) : 0;
        $minProducts = ! empty($productsPerShelf) ? min(array_filter($productsPerShelf)) : 0;

        // Se houver desbalanceamento grande (mais de 3x diferença)
        if ($maxProducts > 0 && $minProducts > 0 && ($maxProducts / $minProducts) > 3) {
            $warnings[] = "⚠️ Desbalanceamento na distribuição de produtos (min: {$minProducts}, max: {$maxProducts})";
            $recommendations[] = 'Redistribua produtos para equilibrar melhor entre as prateleiras';

            Log::warning('⚠️ Distribuição desbalanceada', [
                'min_products_per_shelf' => $minProducts,
                'max_products_per_shelf' => $maxProducts,
                'ratio' => round($maxProducts / $minProducts, 2),
            ]);
        }

        // 5. Validar dispersão de SKU repetido entre prateleiras
        $productShelfIndexes = [];
        foreach ($allocation as $shelfIndex => $shelfAllocation) {
            $shelfId = $shelfAllocation['shelf_id'] ?? null;
            foreach ($shelfAllocation['products'] ?? [] as $productData) {
                $productId = $productData['product_id'] ?? null;
                if (! $productId) {
                    continue;
                }

                $productShelfIndexes[$productId] ??= [];
                $productShelfIndexes[$productId][] = [
                    'shelf_id' => $shelfId,
                    'allocation_index' => $shelfIndex,
                ];
            }
        }

        $productsRepeatedInManyShelves = 0;
        $productsWithNonAdjacentPlacement = 0;

        foreach ($productShelfIndexes as $placements) {
            $uniqueShelfIds = array_values(array_unique(array_filter(array_column($placements, 'shelf_id'))));

            if (count($uniqueShelfIds) > 2) {
                $productsRepeatedInManyShelves++;
            }

            // Se tiver metadata física, usar regra de adjacência por seção/ordem real.
            if (! empty($shelfMetadata) && ! empty($uniqueShelfIds)) {
                $sections = [];

                foreach ($uniqueShelfIds as $shelfId) {
                    if (! isset($shelfMetadata[$shelfId])) {
                        continue;
                    }

                    $sectionKey = (string) ($shelfMetadata[$shelfId]['section_order'] ?? $shelfMetadata[$shelfId]['section_id']);
                    $sections[$sectionKey] ??= [];
                    $sections[$sectionKey][] = (int) ($shelfMetadata[$shelfId]['shelf_order'] ?? 0);
                }

                if (count($sections) > 1) {
                    $productsWithNonAdjacentPlacement++;

                    continue;
                }

                foreach ($sections as $shelfOrders) {
                    sort($shelfOrders);
                    for ($i = 1; $i < count($shelfOrders); $i++) {
                        if (($shelfOrders[$i] - $shelfOrders[$i - 1]) > 1) {
                            $productsWithNonAdjacentPlacement++;
                            break 2;
                        }
                    }
                }

                continue;
            }

            // Fallback: sem metadata física, usa ordem da alocação da IA.
            $uniqueIndexes = array_values(array_unique(array_column($placements, 'allocation_index')));
            sort($uniqueIndexes);
            for ($i = 1; $i < count($uniqueIndexes); $i++) {
                if (($uniqueIndexes[$i] - $uniqueIndexes[$i - 1]) > 1) {
                    $productsWithNonAdjacentPlacement++;
                    break;
                }
            }
        }

        if ($productsRepeatedInManyShelves > 0 || $productsWithNonAdjacentPlacement > 0) {
            $warnings[] = "⚠️ SKU repetidos com dispersão detectada ({$productsRepeatedInManyShelves} em >2 prateleiras, {$productsWithNonAdjacentPlacement} não adjacentes)";
            $recommendations[] = 'Agrupe repetições do mesmo SKU em bloco vertical (prateleira acima/abaixo) na mesma seção';

            Log::warning('⚠️ Dispersão de SKU repetido detectada', [
                'products_repeated_in_many_shelves' => $productsRepeatedInManyShelves,
                'products_with_non_adjacent_placement' => $productsWithNonAdjacentPlacement,
            ]);
        }

        // 6. Log de qualidade geral
        $qualityScore = 100;

        if ($avgOccupancy < 70) {
            $qualityScore -= 20;
        } elseif ($avgOccupancy < 75) {
            $qualityScore -= 10;
        }

        if ($emptyShelvesCount > 0) {
            $qualityScore -= ($emptyShelvesCount * 10);
        }

        if ($totalUnallocated > 0) {
            $percentUnallocated = ($totalUnallocated / ($totalAllocated + $totalUnallocated)) * 100;
            $qualityScore -= min(30, $percentUnallocated);
        }

        if ($productsRepeatedInManyShelves > 0) {
            $qualityScore -= min(30, $productsRepeatedInManyShelves * 6);
        }

        if ($productsWithNonAdjacentPlacement > 0) {
            $qualityScore -= min(35, $productsWithNonAdjacentPlacement * 4);
        }

        $qualityScore = max(0, $qualityScore);

        Log::info('📊 Análise de qualidade da geração', [
            'quality_score' => $qualityScore,
            'avg_occupancy' => $avgOccupancy,
            'shelves_used' => $shelvesUsed,
            'empty_shelves' => $emptyShelvesCount,
            'total_allocated' => $totalAllocated,
            'total_unallocated' => $totalUnallocated,
            'warnings_count' => count($warnings),
            'recommendations_count' => count($recommendations),
            'products_repeated_in_many_shelves' => $productsRepeatedInManyShelves,
            'products_with_non_adjacent_placement' => $productsWithNonAdjacentPlacement,
        ]);

        // Atualizar metadata com warnings e recomendações
        $metadata['warnings'] = $warnings;
        $metadata['recommendations'] = $recommendations;
        $metadata['quality_score'] = $qualityScore;
    }
}

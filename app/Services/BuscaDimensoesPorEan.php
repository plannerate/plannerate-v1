<?php

namespace App\Services;

use App\Ai\Agents\BuscaDimensoesEan;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class BuscaDimensoesPorEan
{
    /**
     * Tamanhos válidos para EAN (dígitos): EAN-8, UPC, EAN-13, ITF-14.
     */
    private const EAN_LENGTHS = [8, 12, 13, 14];

    /**
     * Tempo de vida do cache de dimensões por EAN (30 dias).
     */
    private const CACHE_TTL_DAYS = 30;

    /**
     * Prefixo da chave de cache.
     */
    private const CACHE_KEY_PREFIX = 'dimensoes_ean';

    /**
     * Busca dimensões (altura, largura, profundidade em cm) para um EAN via agente de IA.
     * O nome do produto, quando informado, ajuda a identificar categoria e equivalentes.
     * Resultados são cacheados para evitar novas consultas ao agente para o mesmo EAN.
     *
     * @return array{ean: string, height: float|null, width: float|null, depth: float|null}
     *
     * @throws InvalidArgumentException se o EAN for inválido
     */
    public function buscar(string $ean, ?string $name = null): array
    {
        $ean = $this->normalizarEan($ean);
        if (! $this->eanValido($ean)) {
            throw new InvalidArgumentException('EAN inválido');
        }

        $cacheKey = self::CACHE_KEY_PREFIX.':'.$ean;

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && array_key_exists('ean', $cached)) {
            return $cached;
        }

        $prompt = "Busque as dimensões do produto com EAN: {$ean}.";
        if ($name !== null && $name !== '') {
            $prompt .= " Nome do produto: {$name} (use para identificar categoria e equivalentes se não achar o EAN exato).";
        }

        $agent = new BuscaDimensoesEan;
        $response = $agent->prompt($prompt);

        $result = [
            'ean' => $ean,
            'height' => $this->toFloatOrNull($response['height'] ?? null),
            'width' => $this->toFloatOrNull($response['width'] ?? null),
            'depth' => $this->toFloatOrNull($response['depth'] ?? null),
        ];

        Cache::put($cacheKey, $result, \Carbon\Carbon::now()->addDays(self::CACHE_TTL_DAYS));

        return $result;
    }

    /**
     * Remove do cache o resultado de dimensões para um EAN (útil se o produto for atualizado).
     */
    public function limparCache(string $ean): void
    {
        $ean = $this->normalizarEan($ean);
        if ($ean !== '') {
            Cache::forget(self::CACHE_KEY_PREFIX.':'.$ean);
        }
    }

    private function normalizarEan(string $ean): string
    {
        $ean = preg_replace('/\D/', '', $ean);

        return $ean ?? '';
    }

    private function eanValido(string $ean): bool
    {
        if ($ean === '') {
            return false;
        }

        return in_array(strlen($ean), self::EAN_LENGTHS, true);
    }

    private function toFloatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }
}

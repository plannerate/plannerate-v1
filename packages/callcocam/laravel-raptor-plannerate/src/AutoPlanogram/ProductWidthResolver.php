<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Fonte única de verdade para largura de produto no pipeline de auto-planograma.
 *
 * Centraliza a sanitização: width=0, negativo ou acima do threshold recebem o
 * fallback e geram warning para que o admin corrija o cadastro.
 */
final class ProductWidthResolver
{
    private const DEFAULT_WIDTH_CM = 10.0;

    private const MAX_PLAUSIBLE_WIDTH = 60.0;

    /**
     * Produtos cuja largura caiu no fallback nesta execução.
     *
     * O fallback é a maior fonte silenciosa de imprecisão do planograma: se o cadastro
     * do produto não tem largura (ou tem uma implausível), a gôndola é montada com um
     * chute de 10cm e o resultado "não fecha" sem que ninguém saiba por quê. Rastrear
     * aqui permite mostrar os produtos culpados no relatório da geração, em vez de
     * enterrá-los no log.
     *
     * @var array<string, array{product_id: string, product_name: string, width_raw: float|null, width_used: float, reason: string}>
     */
    private array $fallbackProducts = [];

    public function __construct(
        private float $defaultWidth = self::DEFAULT_WIDTH_CM,
        private float $maxPlausible = self::MAX_PLAUSIBLE_WIDTH,
    ) {}

    /**
     * Retorna a largura válida do produto em cm.
     * Registra warning e rastreia o produto se o valor original for suspeito.
     */
    public function resolve(mixed $product): float
    {
        $raw = isset($product->width) ? (float) $product->width : null;

        if ($raw === null) {
            // Antes era um retorno mudo: produto sem largura cadastrada entrava com 10cm
            // e ninguém ficava sabendo. Agora conta como fallback rastreado.
            $this->trackFallback($product, null, 'missing');

            return $this->defaultWidth;
        }

        if ($raw <= 0) {
            Log::warning('ProductWidthResolver: width inválido (zero ou negativo)', [
                'product_id' => $product->id,
                'product_name' => $product->name ?? '?',
                'width_raw' => $raw,
                'usando' => $this->defaultWidth,
            ]);

            $this->trackFallback($product, $raw, 'invalid');

            return $this->defaultWidth;
        }

        if ($raw > $this->maxPlausible) {
            Log::warning('ProductWidthResolver: width suspeito (acima do threshold)', [
                'product_id' => $product->id,
                'product_name' => $product->name ?? '?',
                'width_raw' => $raw,
                'threshold' => $this->maxPlausible,
                'usando' => $this->defaultWidth,
            ]);

            $this->trackFallback($product, $raw, 'implausible');

            return $this->defaultWidth;
        }

        return $raw;
    }

    /**
     * Produtos que entraram com largura chutada nesta execução (deduplicados por id).
     *
     * @return list<array{product_id: string, product_name: string, width_raw: float|null, width_used: float, reason: string}>
     */
    public function fallbackProducts(): array
    {
        return array_values($this->fallbackProducts);
    }

    /**
     * Zera o rastreamento. Obrigatório no início de cada geração: o resolver é singleton
     * e, no worker de fila, o mesmo processo atende várias gerações em sequência —
     * sem isso, os suspeitos de uma gôndola vazariam para o relatório da seguinte.
     */
    public function reset(): void
    {
        $this->fallbackProducts = [];
    }

    private function trackFallback(mixed $product, ?float $raw, string $reason): void
    {
        $id = (string) ($product->id ?? '');

        if ($id === '') {
            return;
        }

        $this->fallbackProducts[$id] = [
            'product_id' => $id,
            'product_name' => (string) ($product->name ?? '?'),
            'width_raw' => $raw,
            'width_used' => $this->defaultWidth,
            'reason' => $reason,
        ];
    }

    /**
     * Versão em lote — retorna array<product_id, float> para uso no escalonamento.
     *
     * @param  Collection<int, mixed>  $products
     * @return array<string, float>
     */
    public function resolveAll(Collection $products): array
    {
        return $products->mapWithKeys(fn ($p) => [
            $p->id => $this->resolve($p),
        ])->all();
    }
}

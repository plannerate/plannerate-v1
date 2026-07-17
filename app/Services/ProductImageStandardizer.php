<?php

namespace App\Services;

use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Fonte única do "padrão dos tamanhos" das imagens de produto.
 *
 * Decodifica um binário de imagem, calcula a dimensão alvo (a partir das
 * dimensões cadastradas do produto em cm, quando informadas, ou da resolução
 * nativa), clampa o lado maior ao teto de {@see config('plannerate.image.max_side')}
 * preservando a proporção, redimensiona e reencoda como WebP.
 *
 * O canvas do planograma exibe a no máximo ~300px; imagens acima do teto só
 * atrasam a decodificação no primeiro clique de cada módulo. Centralizar aqui
 * evita que as várias trilhas (repositório DO, fallback web, IA, uploads)
 * voltem a divergir no tamanho de saída.
 */
class ProductImageStandardizer
{
    /**
     * Padroniza um binário de imagem para WebP dentro do teto de dimensão.
     *
     * @param  string  $binary  Conteúdo bruto da imagem de origem.
     * @param  float|null  $widthCm  Largura cadastrada do produto (cm), se houver.
     * @param  float|null  $heightCm  Altura cadastrada do produto (cm), se houver.
     * @return string Binário WebP já redimensionado.
     */
    public function encode(string $binary, ?float $widthCm = null, ?float $heightCm = null): string
    {
        $maxSide = (int) config('plannerate.image.max_side', 384);
        $quality = (int) config('plannerate.image.quality', 90);
        $pixelMultiplier = (int) config('plannerate.image.pixel_multiplier', 7);

        $image = Image::decodeBinary($binary);

        // Com dimensão cadastrada (cm) → alvo = cm × multiplier; sem → nativa.
        $targetWidth = (is_numeric($widthCm) && $widthCm > 0)
            ? (int) ($widthCm * $pixelMultiplier)
            : $image->width();

        $targetHeight = (is_numeric($heightCm) && $heightCm > 0)
            ? (int) ($heightCm * $pixelMultiplier)
            : $image->height();

        // Teto de dimensão preservando a proporção.
        if ($targetWidth > $maxSide || $targetHeight > $maxSide) {
            $clampScale = $maxSide / max($targetWidth, $targetHeight);
            $targetWidth = (int) round($targetWidth * $clampScale);
            $targetHeight = (int) round($targetHeight * $clampScale);
        }

        $targetWidth = max(1, $targetWidth);
        $targetHeight = max(1, $targetHeight);

        $image->resize($targetWidth, $targetHeight);

        return (string) $image->encode(new WebpEncoder($quality));
    }
}

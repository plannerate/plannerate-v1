<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Padrão de imagens de produto
    |--------------------------------------------------------------------------
    |
    | Fonte de verdade única para o dimensionamento das imagens de produto
    | derivadas (WebP no disco public). O canvas do planograma exibe a no
    | máximo ~300px, então imagens acima do teto abaixo só desperdiçam bytes
    | e travam a decodificação. Todos os pontos que geram imagem (resolver,
    | jobs, comando de backfill e uploads diretos) leem estes valores.
    |
    */

    'image' => [
        // Teto do lado maior (px) após o resize. Mantido < 512 de propósito.
        'max_side' => (int) env('PLANNERATE_IMAGE_MAX_SIDE', 384),

        // Qualidade do WebP de saída (0-100).
        'quality' => (int) env('PLANNERATE_IMAGE_QUALITY', 90),

        // Fator para converter dimensões cadastradas do produto (cm) em pixels.
        'pixel_multiplier' => (int) env('PLANNERATE_IMAGE_PIXEL_MULTIPLIER', 7),
    ],

];

<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Export;

use Callcocam\LaravelRaptorPlannerate\Concerns\ResolvesGondolaStoreId;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GondolaPrintService
{
    use ResolvesGondolaStoreId;

    public function __construct(
        protected QRCodeService $qrCodeService
    ) {}

    /**
     * Converte URL para base64 para compatibilidade com dompdf
     */
    protected function getImageAsBase64OrUrl(string $imageUrl): string
    {
        if (! $imageUrl) {
            return $this->getPlaceholderImage();
        }

        try {
            // Se a URL contém /storage/, tenta ler do disco public
            if (str_contains($imageUrl, '/storage/')) {
                $path = str_replace(config('app.url').'/storage/', '', $imageUrl);
                $path = preg_replace('|^/storage/|', '', $path);

                if (Storage::disk('public')->exists($path)) {
                    $content = Storage::disk('public')->get($path);
                    $mime = Storage::disk('public')->mimeType($path);

                    return 'data:'.$mime.';base64,'.base64_encode($content);
                }
            }

            // Se começa com /, completa com URL absoluta
            if (str_starts_with($imageUrl, '/')) {
                $fullPath = public_path(ltrim($imageUrl, '/'));
                if (file_exists($fullPath)) {
                    $content = file_get_contents($fullPath);
                    $mime = mime_content_type($fullPath);

                    return 'data:'.$mime.';base64,'.base64_encode($content);
                }

                // Arquivo não está no disco público: tenta via URL absoluta e
                // embute em base64 (nunca devolve URL crua, que sumiria no PDF).
                return $this->fetchRemoteImageAsBase64(config('app.url').$imageUrl);
            }

            // Se já é URL absoluta (http/https), precisa ser BAIXADA e embutida
            // em base64. Devolver a URL crua quebra a exportação por html2canvas
            // (imagem externa não é capturada → produto sai em branco no PDF).
            if (str_starts_with($imageUrl, 'http')) {
                return $this->fetchRemoteImageAsBase64($imageUrl);
            }

            // Fallback: trata como caminho em storage/public
            if (Storage::disk('public')->exists($imageUrl)) {
                $content = Storage::disk('public')->get($imageUrl);
                $mime = Storage::disk('public')->mimeType($imageUrl) ?? 'image/jpeg';

                return 'data:'.$mime.';base64,'.base64_encode($content);
            }

            return $this->getPlaceholderImage();
        } catch (\Exception $e) {
            Log::warning('Erro ao processar imagem', [
                'url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);

            return $this->getPlaceholderImage();
        }
    }

    /**
     * Baixa uma imagem remota (http/https) e devolve em data URI base64.
     *
     * O resultado é cacheado por URL (24h) porque a mesma imagem costuma
     * repetir em vários produtos/aberturas da tela de impressão e o download
     * remoto é o trecho mais lento de `processProductImages`. Em qualquer falha
     * (timeout, status != 2xx, exceção) cai no placeholder base64, garantindo
     * que o html2canvas sempre tenha uma imagem embutida para renderizar.
     */
    protected function fetchRemoteImageAsBase64(string $imageUrl): string
    {
        $cacheKey = 'plannerate:pdf:img:'.md5($imageUrl);

        return Cache::remember($cacheKey, now()->addDay(), function () use ($imageUrl) {
            try {
                $response = Http::timeout(8)->retry(2, 200)->get($imageUrl);

                if ($response->successful()) {
                    // Content-Type pode vir com charset (ex.: "image/jpeg; ...").
                    $mime = trim(explode(';', (string) $response->header('Content-Type'))[0]);
                    $mime = $mime !== '' ? $mime : 'image/jpeg';

                    return 'data:'.$mime.';base64,'.base64_encode($response->body());
                }

                Log::warning('Imagem remota retornou status não-2xx para PDF', [
                    'url' => $imageUrl,
                    'status' => $response->status(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Erro ao baixar imagem remota para PDF', [
                    'url' => $imageUrl,
                    'error' => $e->getMessage(),
                ]);
            }

            return $this->getPlaceholderImage();
        });
    }

    /**
     * Retorna imagem placeholder em base64
     */
    protected function getPlaceholderImage(): string
    {
        try {
            $path = 'img/fallback/fall4.jpg';
            if (Storage::disk('public')->exists($path)) {
                $content = Storage::disk('public')->get($path);

                return 'data:image/jpeg;base64,'.base64_encode($content);
            }
        } catch (\Exception $e) {
            // Falha silenciosa
        }

        // Retorna 1x1 pixel transparente PNG em base64
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    }

    /**
     * Gera PDF com módulos da gôndola (1 módulo por página)
     * Usa medidas precisas do JSON
     */
    public function generatePdfByModules(string $gondolaId, $download = false)
    {
        $gondola = Gondola::findOrFail($gondolaId);

        $gondola->load([
            'sections',
            'sections.shelves',
            'sections.shelves.segments.layer.product' => $this->productWithStoreMetrics($gondola),
        ]);

        $sections = $gondola->sections; // Limitar a 100 módulos para evitar PDFs muito grandes

        // Gerar QR Code da gôndola
        $gondolaQrCode = $this->qrCodeService->generateForGondola($gondolaId);
        $gondolaQrCode = $this->getImageAsBase64OrUrl($gondolaQrCode);

        // Processar todas as imagens para base64
        $this->processProductImages($sections);

        $data = [
            'gondola' => $gondola,
            'sections' => $sections,
            'gondolaQrCode' => $gondolaQrCode,
            'qrCodeService' => $this->qrCodeService,
        ];

        return view('pdf.gondola.modules', $data);
    }

    /**
     * Constraint do eager-load do produto que traz as métricas da loja da gôndola.
     *
     * `current_stock` vive em `product_store` porque é POR LOJA — os selos de
     * Estoque e Ruptura do PDF liam a coluna congelada de `products` antes disto.
     *
     * @return \Closure(Builder): Builder
     */
    protected function productWithStoreMetrics(Gondola $gondola): \Closure
    {
        // resolveGondolaStoreId() faz loadMissing do planograma com 3 colunas. Se ele
        // ainda não estava carregado, descarrega depois: um planograma parcial
        // pendurado faz a view do PDF ler null em qualquer outra coluna.
        $planogramWasLoaded = $gondola->relationLoaded('planogram');

        $storeId = $this->resolveGondolaStoreId($gondola);

        if (! $planogramWasLoaded) {
            $gondola->unsetRelation('planogram');
        }

        return fn ($query) => $query->forStore($storeId);
    }

    /**
     * Processa imagens de produtos convertendo para base64.
     *
     * Produto sem arte fica com `image_url_encoded` nulo de propósito: o
     * accessor `image_url` devolveria a imagem de fallback (img/fallback/*),
     * que esticada na caixa do produto sai distorcida. Sem base64, o front
     * desenha o placeholder SVG, que se adapta a qualquer proporção.
     */
    protected function processProductImages(Collection $sections): void
    {
        foreach ($sections as $section) {
            foreach ($section->shelves as $shelf) {
                foreach ($shelf->segments as $segment) {
                    if ($segment->layer && $segment->layer->product) {
                        $product = $segment->layer->product;

                        $product->image_url_encoded = $product->url
                            ? $this->getImageAsBase64OrUrl($product->image_url)
                            : null;
                    }
                }
            }
        }
    }

    /**
     * Prepara dados da gôndola para visualização
     */
    public function prepareGondolaData(string $gondolaId): array
    {
        $gondola = Gondola::findOrFail($gondolaId);

        $gondola->load([
            'sections',
            'sections.shelves',
            'sections.shelves.segments.layer.product' => $this->productWithStoreMetrics($gondola),
            'sections.shelves.segments.layer.product.category:id,name',
            'planogram',
            'planogram.category',
        ]);

        // Processar imagens de produtos para base64
        $this->processProductImages($gondola->sections);

        return [
            'gondola' => [
                'id' => $gondola->id,
                'name' => $gondola->name,
                'slug' => $gondola->slug,
                'location' => $gondola->location,
                'side' => $gondola->side,
                'flow' => $gondola->flow,
                'scale_factor' => $gondola->scale_factor,
                'alignment' => $gondola->alignment ?? 'default',
                'planogram_id' => $gondola->planogram_id,
                'planogram' => $gondola->planogram ? [
                    'id' => $gondola->planogram->id,
                    'name' => $gondola->planogram->name,
                    'type' => $gondola->planogram->type,
                    'start_date' => $gondola->planogram->start_date
                        ? (is_string($gondola->planogram->start_date)
                            ? Carbon::parse($gondola->planogram->start_date)->format('d/m/Y')
                            : $gondola->planogram->start_date->format('d/m/Y'))
                        : null,
                    'description' => $gondola->planogram->description,
                    'category' => $gondola->planogram->category
                        ? ['name' => $gondola->planogram->category->name]
                        : null,
                ] : null,
            ],
            'sections' => $gondola->sections->map(function ($section) {
                return [
                    'id' => $section->id,
                    'name' => $section->name,
                    'ordering' => $section->ordering,
                    'width' => $section->width,
                    'height' => $section->height,
                    'hole_width' => $section->hole_width,
                    'hole_height' => $section->hole_height,
                    'hole_spacing' => $section->hole_spacing,
                    'base_height' => $section->base_height,
                    'cremalheira_width' => $section->cremalheira_width,
                    'shelves' => $section->shelves->map(function ($shelf) {
                        return [
                            'id' => $shelf->id,
                            'ordering' => $shelf->ordering ?? 1,
                            'shelf_position' => $shelf->shelf_position,
                            'shelf_height' => $shelf->shelf_height,
                            'shelf_width' => $shelf->shelf_width,
                            'shelf_depth' => $shelf->shelf_depth,
                            'product_type' => $shelf->product_type ?? 'normal',
                            'segments' => $shelf->segments->map(function ($segment) {
                                $layer = $segment->layer;
                                $product = $layer?->product;

                                return [
                                    'id' => $segment->id,
                                    'position' => $segment->position ?? 0,
                                    'quantity' => $segment->quantity ?? 1,
                                    'layer' => $layer ? [
                                        'quantity' => $layer->quantity ?? 1,
                                        'product' => $product ? [
                                            'id' => $product->id,
                                            'name' => $product->name,
                                            'ean' => $product->ean,
                                            'codigo_erp' => $product->codigo_erp,
                                            'brand' => $product->brand,
                                            'category' => $product->category?->name,
                                            'category_full_path' => $product->category?->name,
                                            'width' => $product->width ?? 10,
                                            'height' => $product->height ?? 15,
                                            'depth' => $product->depth ?? 0,
                                            'weight' => $product->weight,
                                            // Estoque atual: necessário para os selos de
                                            // Estoque e Ruptura (indicadores source 'product').
                                            'current_stock' => $product->current_stock,
                                            'image_url' => $product->image_url,
                                            'image_url_encoded' => $product->image_url_encoded ?? null,
                                        ] : null,
                                    ] : null,
                                ];
                            })->toArray(),
                        ];
                    })->toArray(),
                ];
            })->toArray(),
        ];
    }
}

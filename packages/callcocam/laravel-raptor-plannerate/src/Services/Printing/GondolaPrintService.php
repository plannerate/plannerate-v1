<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Printing;

use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Services\QRCode\QRCodeService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GondolaPrintService
{
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

                return config('app.url').$imageUrl;
            }

            // Se já é URL absoluta, retorna como está
            if (str_starts_with($imageUrl, 'http')) {
                return $imageUrl;
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
        $gondola = Gondola::with([
            'sections',
            'sections.shelves',
            'sections.shelves.segments.layer.product',
        ])->findOrFail($gondolaId);

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
     * Processa imagens de produtos convertendo para base64
     */
    protected function processProductImages(Collection $sections): void
    {
        foreach ($sections as $section) {
            foreach ($section->shelves as $shelf) {
                foreach ($shelf->segments as $segment) {
                    if ($segment->layer && $segment->layer->product) {
                        $product = $segment->layer->product;
                        $product->image_url_encoded = $this->getImageAsBase64OrUrl($product->image_url);
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
        $gondola = Gondola::with([
            'sections',
            'sections.shelves',
            'sections.shelves.segments.layer.product',
            'sections.shelves.segments.layer.product.category:id,name',
            'planogram',
            'planogram.category',
        ])->findOrFail($gondolaId);

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

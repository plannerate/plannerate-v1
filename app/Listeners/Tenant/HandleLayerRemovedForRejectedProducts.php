<?php

namespace App\Listeners\Tenant;

use App\Models\PlanogramRejectedProduct;
use App\Models\Product;
use Callcocam\LaravelRaptorPlannerate\Enums\PlacementFailureReason;
use Callcocam\LaravelRaptorPlannerate\Events\LayerRemovedEvent;
use Illuminate\Support\Facades\Log;

/**
 * Insere o produto na tabela de rejeitados quando uma layer é removida manualmente
 * de uma gôndola gerada automaticamente (generation_mode = 'auto' ou 'template').
 *
 * Isso garante que o produto continue visível no painel de rejeitados do planograma,
 * permitindo que o usuário entenda o impacto das remoções manuais.
 */
class HandleLayerRemovedForRejectedProducts
{
    /**
     * Processa o evento de remoção de layer.
     *
     * Ignora silenciosamente gôndolas manuais (generation_mode = 'manual' ou null)
     * pois nelas não existe lista de rejeitados a manter.
     */
    public function handle(LayerRemovedEvent $event): void
    {
        // Apenas gôndolas automáticas ou de template geram lista de rejeitados
        if (! in_array($event->gondola->generation_mode, ['auto', 'template'], strict: true)) {
            return;
        }

        $product = $this->loadProduct($event->layer->product_id ?? null);

        if (! $product) {
            return;
        }

        // Evita duplicata: se o produto já está rejeitado nesta gôndola, não insere novamente
        $jaRejeitado = PlanogramRejectedProduct::where('gondola_id', $event->gondola->id)
            ->where('product_id', $product->id)
            ->exists();

        if ($jaRejeitado) {
            return;
        }

        try {
            PlanogramRejectedProduct::create([
                'planogram_id' => $event->gondola->planogram_id,
                'gondola_id' => $event->gondola->id,
                'tenant_id' => $event->gondola->tenant_id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'ean' => $product->ean ?? null,
                'image_url' => $product->image_url ?? null,
                'product_width' => $product->width ?? null,
                'product_height' => $product->height ?? null,
                'rejection_reason' => PlacementFailureReason::ManuallyRemoved,
            ]);
        } catch (\Throwable $e) {
            Log::error('HandleLayerRemovedForRejectedProducts: falha ao inserir produto em rejeitados', [
                'product_id' => $product->id,
                'gondola_id' => $event->gondola->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Carrega o produto pelo ID usando a conexão tenant configurada.
     */
    private function loadProduct(?string $productId): ?Product
    {
        if ($productId === null || $productId === '') {
            return null;
        }

        try {
            return Product::find($productId);
        } catch (\Throwable $e) {
            Log::warning('HandleLayerRemovedForRejectedProducts: falha ao carregar produto', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

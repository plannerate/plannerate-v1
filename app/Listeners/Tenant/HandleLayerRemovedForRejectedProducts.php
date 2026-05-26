<?php

namespace App\Listeners\Tenant;

use App\Enums\PlacementFailureReason;
use App\Models\PlanogramRejectedProduct;
use App\Models\Product;
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
        Log::debug('[LayerEvent] 5/6 Listener::handle: evento recebido', [
            'gondola_id' => $event->gondola->id,
            'generation_mode' => $event->gondola->generation_mode,
            'product_id' => $event->layer->product_id ?? null,
            'layer_id' => $event->layer->id ?? null,
        ]);

        // Apenas gôndolas automáticas ou de template geram lista de rejeitados
        if (! in_array($event->gondola->generation_mode, ['auto', 'template'], strict: true)) {
            Log::debug('[LayerEvent] 5/6 Listener::handle: ignorado (generation_mode não é auto/template)', [
                'generation_mode' => $event->gondola->generation_mode,
            ]);

            return;
        }

        $product = $this->loadProduct($event->layer->product_id ?? null);

        Log::debug('[LayerEvent] 5/6 Listener::handle: produto carregado', [
            'product_id' => $event->layer->product_id ?? null,
            'product_found' => $product !== null,
            'product_name' => $product?->name,
        ]);

        if (! $product) {
            Log::warning('[LayerEvent] 5/6 Listener::handle: produto não encontrado', [
                'product_id' => $event->layer->product_id ?? null,
                'gondola_id' => $event->gondola->id,
            ]);

            return;
        }

        // Evita duplicata: se o produto já está rejeitado nesta gôndola, não insere novamente
        $jaRejeitado = PlanogramRejectedProduct::where('gondola_id', $event->gondola->id)
            ->where('product_id', $product->id)
            ->exists();

        Log::debug('[LayerEvent] 5/6 Listener::handle: verificação de duplicata', [
            'ja_rejeitado' => $jaRejeitado,
            'product_id' => $product->id,
            'gondola_id' => $event->gondola->id,
        ]);

        if ($jaRejeitado) {
            Log::debug('[LayerEvent] 5/6 Listener::handle: produto já está em rejeitados, ignorando');

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

            Log::info('[LayerEvent] 6/6 ✅ Produto inserido em rejeitados', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'gondola_id' => $event->gondola->id,
                'generation_mode' => $event->gondola->generation_mode,
            ]);
        } catch (\Throwable $e) {
            Log::error('[LayerEvent] 6/6 ❌ Falha ao inserir em rejeitados', [
                'product_id' => $product->id,
                'gondola_id' => $event->gondola->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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

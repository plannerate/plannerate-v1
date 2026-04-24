<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api;

use App\Services\ProductRepositoryImageResolver;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Http\Requests\UploadProductImageRequest;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductImageController extends Controller
{
    public function update(Request $request, ProductRepositoryImageResolver $imageResolver)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $product = Product::find($request->product_id);
        if (! $product) {
            return redirect()->back()->withErrors(['product_id' => 'Produto não encontrado!']);
        }

        $product->url = $imageResolver->resolveForProduct($product);
        if (! $product->save()) {
            return redirect()->back()->withErrors(['product_id' => 'Erro ao atualizar imagem do produto!']);
        }

        return redirect()->back()->with('success', 'Imagem atualizada com sucesso!');
    }

    /**
     * Upload manual de imagem do produto
     */
    public function uploadImage(UploadProductImageRequest $request, Product $product)
    {
        try {
            // Valida e obtém a imagem do request
            $image = $request->file('image');

            if (! $image) {
                return back()->withErrors(['image' => 'Nenhuma imagem foi enviada.']);
            }

            // Define o path de armazenamento
            $path = 'products/'.$product->id;

            // Armazena a imagem no disco public
            $filename = $image->store($path, 'public');

            // Atualiza o produto com a nova URL da imagem
            $product->update([
                'image_url' => asset('storage/'.$filename),
            ]);

            return back()->with('success', 'Imagem atualizada com sucesso!');
        } catch (\Exception $e) {
            Log::error('Erro ao fazer upload de imagem do produto', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['image' => 'Erro ao fazer upload da imagem.']);
        }
    }

    /**
     * Remove a imagem do produto (marca como null)
     */
    public function deleteImage($product)
    {
        $product = Product::findOrFail($product);
        try {
            // Atualiza o produto removendo a URL da imagem
            $product->update([
                'url' => null,
            ]);

            return back()->with('success', 'Imagem removida com sucesso!');
        } catch (\Exception $e) {
            Log::error('Erro ao remover imagem do produto', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['image' => 'Erro ao remover a imagem.']);
        }
    }
}

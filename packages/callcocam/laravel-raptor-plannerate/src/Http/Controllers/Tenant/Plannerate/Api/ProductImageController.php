<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Api;

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Http\Requests\UploadProductImageRequest;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ProductImageController extends Controller
{
    public function update(Request $request)
    {

        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);
        $product = Product::find($request->product_id);
        if (! $product) {
            return redirect()->back()->withErrors(['product_id' => 'Produto não encontrado!']);
        }
        $product->url = $this->processImageFromStorage("repositorioimagens/frente/{$product->ean}.png", $product);
        if (! $product->save()) {
            return redirect()->back()->withErrors(['product_id' => 'Erro ao atualizar imagem do produto!']);
        }

        return redirect()->back()->with('success', 'Imagem atualizada com sucesso!');
    }

    protected function processImageFromStorage($storagePath, $product)
    {
        // Usa dimensões diretas do produto (tabela dimensions foi removida)
        // Fator para converter as dimensões do produto (ex: cm) em pixels para exibição.
        // Aumentado para um resultado visual maior. Ajuste se necessário.
        $pixelMultiplier = 7;
        // Qualidade da imagem WebP (0 a 100). 90 é alta qualidade.
        $quality = 90;

        try {
            $width = $product->width;
            $height = $product->height;

            $imageFile = Storage::disk('do')->get($storagePath);

            $url = Storage::disk('do')->url($storagePath);
            if (! $imageFile) {
                Log::warning("Imagem não encontrada para EAN {$product->ean} no caminho {$url}");

                return null;
            }
            Log::info("Processando imagem para EAN {$product->ean} a partir de {$url}");
            $image = Image::read($imageFile);

            if (! is_numeric($width) || $width <= 0) {
                $width = $image->width() / $pixelMultiplier; // Largura padrão em cm
            }
            if (! is_numeric($height) || $height <= 0) {
                $height = $image->height() / $pixelMultiplier; // Altura padrão em cm
            }
            // Calcula o tamanho que a imagem deve ter.
            $targetWidth = (int) ($width * $pixelMultiplier);
            $targetHeight = (int) ($height * $pixelMultiplier);

            // Só redimensiona se a imagem for maior que o tamanho alvo
            // Se for menor ou igual, mantém o tamanho original
            $currentWidth = $image->width();
            $currentHeight = $image->height();

            if ($currentWidth > $targetWidth || $currentHeight > $targetHeight) {
                // Usa resize(): Força a imagem a ter o tamanho alvo, garantindo a proporção visual.
                $image->resize($targetWidth, $targetHeight);
                Log::info("Imagem redimensionada de {$currentWidth}x{$currentHeight} para {$targetWidth}x{$targetHeight}");
            } else {
                Log::info("Imagem já é pequena ({$currentWidth}x{$currentHeight}), mantendo tamanho original");
            }

            // Codifica para WebP com alta qualidade
            $encodedImage = $image->toWebp(quality: $quality);

            $newFileName = sprintf('%s.webp', $product->ean);
            $newPath = sprintf('repositorioimagens/frente/%s', $newFileName);

            Storage::disk('public')->put($newPath, $encodedImage);

            $url = Storage::disk('public')->url($newPath);
            Log::info("Imagem processada para EAN {$product->ean}, salva em {$url}");

            return $newPath;
        } catch (\Exception $e) {
            Log::error("Falha ao processar imagem para EAN {$product->ean}: ".$e->getMessage());

            return null;
        }
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

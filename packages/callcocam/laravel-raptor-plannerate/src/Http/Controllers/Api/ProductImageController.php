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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use League\Flysystem\UnableToWriteFile;
use Throwable;

class ProductImageController extends Controller
{
    public function update(Request $request, ProductRepositoryImageResolver $imageResolver, string $subdomain)
    {
        $request->validate([
            'product_id' => 'required|string',
        ]);

        $product = Product::query()->whereKey((string) $request->product_id)->first();
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
    public function uploadImage(UploadProductImageRequest $request, string $subdomain, string $product)
    {
        $productModel = Product::query()->whereKey($product)->first();

        if ($productModel === null) {
            throw ValidationException::withMessages([
                'product' => 'Produto não encontrado. Confirme se o ID existe neste tenant e se o item não foi excluído.',
            ]);
        }

        try {
            // Valida e obtém a imagem do request
            $image = $request->file('image');

            if (! $image instanceof UploadedFile || ! $image->isValid()) {
                throw ValidationException::withMessages([
                    'image' => 'A imagem enviada e invalida ou esta corrompida.',
                ]);
            }

            // Define o path de armazenamento
            $path = 'products/'.$productModel->id;

            // Armazena a imagem no disco public
            $filename = $image->store($path, 'public');

            if (! is_string($filename) || $filename === '') {
                throw UnableToWriteFile::atLocation($path);
            }

            // Coluna `url` guarda o path no disco public (ver getImageUrlAttribute no modelo Product do editor)
            $productModel->update([
                'url' => $filename,
            ]);

            return back()->with('success', 'Imagem atualizada com sucesso!');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Erro ao fazer upload de imagem do produto', [
                'product_id' => $productModel->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['image' => $this->resolveUploadExceptionMessage($e)]);
        }
    }

    private function resolveUploadExceptionMessage(Throwable $exception): string
    {
        if ($exception instanceof UnableToWriteFile) {
            return 'Nao foi possivel salvar a imagem no armazenamento. Verifique a configuracao do disco e tente novamente.';
        }

        return $exception->getMessage() ?: 'Erro inesperado ao processar o upload da imagem. Tente novamente em instantes.';
    }

    /**
     * Remove a imagem do produto (marca como null)
     */
    public function deleteImage(string $product)
    {
        $productModel = Product::query()->whereKey($product)->first();

        if ($productModel === null) {
            throw ValidationException::withMessages([
                'product' => 'Produto não encontrado. Confirme se o ID existe neste tenant e se o item não foi excluído.',
            ]);
        }

        try {
            // Atualiza o produto removendo a URL da imagem
            $productModel->update([
                'url' => null,
            ]);

            return back()->with('success', 'Imagem removida com sucesso!');
        } catch (\Exception $e) {
            Log::error('Erro ao remover imagem do produto', [
                'product_id' => $productModel->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['image' => 'Erro ao remover a imagem.']);
        }
    }
}

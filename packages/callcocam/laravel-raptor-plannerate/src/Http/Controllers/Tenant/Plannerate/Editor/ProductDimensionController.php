<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor;

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductDimensionController extends Controller
{
    public function update(Request $request, string $planogramId, string $productId)
    {
        $validated = $request->validate([
            'width' => 'required|numeric|min:0',
            'height' => 'required|numeric|min:0',
            'depth' => 'required|numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string|in:cm,mm,m,in',
            'image' => 'nullable|image|max:2048', // 2MB max
        ], [
            'width.required' => 'A largura é obrigatória',
            'width.numeric' => 'A largura deve ser um número',
            'width.min' => 'A largura deve ser maior ou igual a 0',
            'height.required' => 'A altura é obrigatória',
            'height.numeric' => 'A altura deve ser um número',
            'height.min' => 'A altura deve ser maior ou igual a 0',
            'depth.required' => 'A profundidade é obrigatória',
            'depth.numeric' => 'A profundidade deve ser um número',
            'depth.min' => 'A profundidade deve ser maior ou igual a 0',
            'image.image' => 'O arquivo deve ser uma imagem',
            'image.max' => 'A imagem não pode ter mais de 2MB',
        ]);

        try {
            $product = Product::findOrFail($productId);

            DB::beginTransaction();

            // Atualiza dimensões diretamente no produto (tabela dimensions foi removida)
            $product->width = $validated['width'];
            $product->height = $validated['height'];
            $product->depth = $validated['depth'];
            $product->weight = $validated['weight'] ?? $product->weight;
            $product->unit = $validated['unit'] ?? $product->unit ?? 'cm';
            // has_dimensions não existe como coluna; é derivado de width/height/depth

            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($product->url && ! str_starts_with($product->url, 'http')) {
                    Storage::disk('public')->delete($product->url);
                }

                // Store new image
                $path = $request->file('image')->store('products', 'public');
                $product->url = $path;
            }

            $product->save();

            DB::commit();

            return redirect()->back()->with('success', 'Produto atualizado com sucesso');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->withErrors(['error' => 'Erro ao atualizar produto: '.$e->getMessage()]);
        }
    }
}

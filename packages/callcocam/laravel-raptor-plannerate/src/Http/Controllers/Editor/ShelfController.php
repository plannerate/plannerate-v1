<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor;

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Shelf;
use Illuminate\Http\Request;

class ShelfController extends Controller
{
    protected function getResourceLabel(): ?string
    {
        return 'Prateleira';
    }

    protected function resourcePath(): ?string
    {
        return 'tenant';
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:255',
            'shelf_width' => 'nullable|numeric|min:0',
            'shelf_height' => 'nullable|numeric|min:0',
            'shelf_depth' => 'nullable|numeric|min:0',
            'shelf_position' => 'nullable|numeric|min:0',
            'ordering' => 'nullable|integer|min:0',
        ]);

        $shelf = Shelf::findOrFail($id);
        $shelf->update($validated);

        return redirect()->back()->with([
            'success' => 'Prateleira atualizada com sucesso.',
            'shelf' => $shelf,
        ]);
    }

    public function store(Request $request, $sectionId)
    {
        $validated = $request->validate([
            'y_position' => 'required|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'depth' => 'nullable|numeric|min:0',
        ]);

        // Buscar a section para obter a largura
        $section = Section::findOrFail($sectionId);

        // Calcular o próximo ordering
        $maxOrdering = Shelf::where('section_id', $sectionId)->max('ordering') ?? 0;

        $shelf = Shelf::create([
            'section_id' => $sectionId,
            'shelf_width' => $section->width, // Usa a largura da section
            'shelf_height' => $validated['height'] ?? 40, // Altura padrão 40cm
            'shelf_depth' => $validated['depth'] ?? 30,   // Profundidade padrão 30cm
            'shelf_position' => $validated['y_position'], // Posição Y onde foi clicado
            'ordering' => $maxOrdering + 1,
            'code' => 'SHELF-'.time(),
        ]);

        // Carrega relacionamentos
        $shelf->load(['segments.layers.product']);

        return redirect()->back()->with([
            'success' => 'Prateleira criada com sucesso',
            'shelf' => $shelf->toArray(),
        ]);
    }

    public function destroy($id)
    {
        $shelf = Shelf::findOrFail($id);
        $sectionId = $shelf->section_id;

        $shelf->delete();

        return redirect()->back()->with([
            'success' => 'Prateleira excluída com sucesso',
            'deleted_shelf_id' => $id,
            'section_id' => $sectionId,
        ]);
    }
}

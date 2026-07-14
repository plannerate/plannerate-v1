<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor;

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Shelf;
use Illuminate\Http\Request;

class ShelfController extends Controller
{
    public function update(Request $request, ?string $id)
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

    /**
     * Trava/destrava a prateleira contra a geração automática.
     *
     * Endpoint próprio, e não um campo a mais no update(): aquele trata da geometria física da
     * prateleira (largura, altura, posição). Travar é uma decisão de merchandising com efeito
     * destrutivo do outro lado — a próxima geração vai preservar ou reescrever esta prateleira
     * conforme esta flag. Misturar as duas coisas faria um ajuste de altura carregar junto, sem
     * querer, o estado do lock.
     */
    public function toggleLock(Request $request, string $shelf)
    {
        $validated = $request->validate([
            'is_locked' => 'required|boolean',
        ]);

        $model = Shelf::findOrFail($shelf);
        $model->forceFill(['is_locked' => $validated['is_locked']])->save();

        return back()->with('success', $validated['is_locked']
            ? __('plannerate.reoptimization.lock.locked')
            : __('plannerate.reoptimization.lock.unlocked'));
    }

    public function store(Request $request, ?string $sectionId)
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

    public function destroy(?string $id)
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

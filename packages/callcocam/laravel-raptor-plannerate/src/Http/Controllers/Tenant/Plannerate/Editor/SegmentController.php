<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor;

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Segment;
use Illuminate\Http\Request;

class SegmentController extends Controller
{
    protected function getResourceLabel(): ?string
    {
        return 'Segmento';
    }

    protected function resourcePath(): ?string
    {
        return 'tenant';
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:1',
            'ordering' => 'nullable|integer|min:0',
        ]);

        $segment = Segment::findOrFail($id);
        $segment->update($validated);

        return redirect()->back()->with([
            'success' => 'Segmento atualizado com sucesso.',
            'segment' => $segment,
        ]);
    }
}

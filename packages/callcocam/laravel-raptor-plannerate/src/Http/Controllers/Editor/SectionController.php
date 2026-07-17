<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor;

use App\Models\Tenant;
use App\Support\Workflow\GondolaEditGate;
use Callcocam\LaravelRaptorPlannerate\Concerns\UsesPlannerateTenantDatabase;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
use Callcocam\LaravelRaptorPlannerate\Services\Editor\SectionService;
use Callcocam\LaravelRaptorPlannerate\Services\Editor\ShelfStructureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SectionController extends Controller
{
    use UsesPlannerateTenantDatabase;

    public function store(Request $request, string $gondola)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'width' => 'required|numeric|min:0',
            'height' => 'required|numeric|min:0',
            'base_height' => 'nullable|numeric|min:0',
            'base_width' => 'nullable|numeric|min:0',
            'base_depth' => 'nullable|numeric|min:0',
            'cremalheira_width' => 'nullable|numeric|min:0',
            'hole_height' => 'nullable|numeric|min:0',
            'hole_width' => 'nullable|numeric|min:0',
            'hole_spacing' => 'nullable|numeric|min:0',
            'shelf_height' => 'nullable|numeric|min:0',
            'shelf_width' => 'nullable|numeric|min:0',
            'shelf_depth' => 'nullable|numeric|min:0',
            'num_shelves' => 'nullable|integer|min:0',
            'product_type' => 'nullable|string|in:normal,hook',
        ]);

        try {
            $this->plannerateTenantDatabase()->beginTransaction();

            // Obter próximo ordering
            $maxOrdering = Section::where('gondola_id', $gondola)->max('ordering') ?? -1;

            // Criar a seção
            $section = Section::create([
                'tenant_id' => Tenant::current()?->getKey(),
                'user_id' => auth()->id(),
                'gondola_id' => $gondola,
                'name' => $validated['name'],
                'width' => $validated['width'],
                'height' => $validated['height'],
                'base_height' => $validated['base_height'] ?? 17,
                'base_width' => $validated['base_width'] ?? 130,
                'base_depth' => $validated['base_depth'] ?? 40,
                'cremalheira_width' => $validated['cremalheira_width'] ?? 4.00,
                'hole_height' => $validated['hole_height'] ?? 2.00,
                'hole_width' => $validated['hole_width'] ?? 2.00,
                'hole_spacing' => $validated['hole_spacing'] ?? 2.00,
                'num_shelves' => $validated['num_shelves'] ?? 4,
                'ordering' => $maxOrdering + 1,
                'status' => 'published',
            ]);

            // Cria as prateleiras distribuídas pelos furos da cremalheira.
            // Usa a mesma fonte de matemática da criação manual/automática de gôndolas
            // (ShelfStructureService) para que a estrutura física seja idêntica entre os fluxos.
            app(ShelfStructureService::class)->createShelves($section, [
                'shelf_width' => $validated['shelf_width'] ?? 130,
                'shelf_height' => $validated['shelf_height'] ?? 4,
                'shelf_depth' => $validated['shelf_depth'] ?? 40,
                'product_type' => $validated['product_type'] ?? 'normal',
            ], (int) ($validated['num_shelves'] ?? 4));

            $this->plannerateTenantDatabase()->commit();

            // Retorna back() sem preserveState para forçar reload dos dados
            return back();
        } catch (\Exception $e) {
            $this->plannerateTenantDatabase()->rollBack();

            return redirect()->back()->withErrors([
                'error' => 'Erro ao criar seção: '.$e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:255',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'base_width' => 'nullable|numeric|min:0',
            'base_height' => 'nullable|numeric|min:0',
            'base_depth' => 'nullable|numeric|min:0',
            'cremalheira_width' => 'nullable|numeric|min:0',
            'hole_height' => 'nullable|numeric|min:0',
            'hole_spacing' => 'nullable|numeric|min:0',
            'hole_width' => 'nullable|numeric|min:0',
            'ordering' => 'nullable|integer|min:0',
        ]);

        $section = Section::findOrFail($id);
        $section->update($validated);

        return redirect()->back()->with([
            'success' => 'Seção atualizada com sucesso.',
            'section' => $section,
        ]);
    }

    /**
     * Retorna informações de uma seção
     */
    public function show(string $id)
    {
        try {
            $section = Section::findOrFail($id);

            return response()->json([
                'data' => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'code' => $section->code,
                    'gondola_id' => $section->gondola_id,
                    'ordering' => $section->ordering,
                    'width' => $section->width,
                    'height' => $section->height,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Seção não encontrada: '.$e->getMessage(),
            ], 404);
        }
    }

    public function destroy(string $id)
    {
        try {
            $section = Section::findOrFail($id);
            $sectionName = $section->name;

            // Soft delete (devido ao SoftDeletes trait)
            $section->delete();

            return redirect()->back()->with([
                'success' => "Seção '{$sectionName}' excluída com sucesso.",
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'error' => 'Erro ao excluir seção: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Transfere uma seção para outra gôndola
     */
    public function transfer(Request $request, string $sectionId)
    {
        $tenantConnectionName = config('multitenancy.tenant_database_connection_name');
        $gondolasTable = is_string($tenantConnectionName) && $tenantConnectionName !== ''
            ? $tenantConnectionName.'.gondolas'
            : 'gondolas';

        $validated = $request->validate([
            'gondola_id' => ['required', 'string', Rule::exists($gondolasTable, 'id')],
        ]);

        try {
            $this->plannerateTenantDatabase()->beginTransaction();

            $section = Section::findOrFail($sectionId);
            $targetGondolaId = $validated['gondola_id'];
            $sourceGondolaId = $section->gondola_id;

            // Verifica se não é a mesma gôndola
            if ($sourceGondolaId === $targetGondolaId) {
                return back()->withErrors([
                    'error' => 'A seção já está nesta gôndola.',
                ]);
            }

            // Obter o próximo ordering na gôndola de destino
            $maxOrdering = Section::where('gondola_id', $targetGondolaId)->max('ordering') ?? -1;

            // Atualiza a seção
            $section->update([
                'gondola_id' => $targetGondolaId,
                'ordering' => $maxOrdering + 1,
            ]);

            // Fecha a lacuna de ordering deixada na gôndola de origem
            if ($sourceGondolaId) {
                app(SectionService::class)->reorderByOrdering((string) $sourceGondolaId);
            }

            $this->plannerateTenantDatabase()->commit();

            return back();
        } catch (\Exception $e) {
            $this->plannerateTenantDatabase()->rollBack();

            Log::error('Erro ao transferir seção', [
                'section_id' => $sectionId,
                'gondola_id' => $validated['gondola_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Erro ao transferir seção: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Copia (deep copy) uma seção inteira — com prateleiras, segmentos e
     * camadas — para outra gôndola, sem remover a original.
     */
    public function copyToGondola(Request $request, string $sectionId, GondolaEditGate $gate)
    {
        $tenantConnectionName = config('multitenancy.tenant_database_connection_name');
        $gondolasTable = is_string($tenantConnectionName) && $tenantConnectionName !== ''
            ? $tenantConnectionName.'.gondolas'
            : 'gondolas';

        $validated = $request->validate([
            'gondola_id' => ['required', 'string', Rule::exists($gondolasTable, 'id')],
        ]);

        $targetGondolaId = $validated['gondola_id'];

        // O middleware gondola.editable gateia a gôndola de ORIGEM (via {section}).
        // A cópia grava na gôndola de DESTINO, então valida a edição do destino aqui.
        if ($gate->kanbanActive()) {
            $user = $request->user();

            if ($user === null || ! $gate->decide($user, $targetGondolaId)->allowsEditing()) {
                return back()->withErrors([
                    'error' => 'Você não tem permissão para editar a gôndola de destino.',
                ]);
            }
        }

        try {
            $this->plannerateTenantDatabase()->beginTransaction();

            app(SectionService::class)->deepCopyToGondola($sectionId, $targetGondolaId);

            $this->plannerateTenantDatabase()->commit();

            return back();
        } catch (\Exception $e) {
            $this->plannerateTenantDatabase()->rollBack();

            Log::error('Erro ao copiar seção', [
                'section_id' => $sectionId,
                'gondola_id' => $targetGondolaId,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Erro ao copiar seção: '.$e->getMessage(),
            ]);
        }
    }
}

<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor;

use Callcocam\LaravelRaptorPlannerate\Concerns\UsesPlannerateTenantDatabase;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Http\Requests\Tenant\Plannerate\Editor\SaveChangesRequest;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\PlanogramChangeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Controller responsável por salvar mudanças (deltas) no planograma
 *
 * Arquitetura:
 * - Frontend envia apenas mudanças (deltas), não o estado completo
 * - Backend aplica mudanças transacionalmente
 * - Validação via FormRequest
 * - Lógica de negócio delegada para Services
 * - Acesso a dados via Repositories
 * - Usa ULIDs gerados no frontend para novas entidades
 */
class SaveChangesController extends Controller
{
    use UsesPlannerateTenantDatabase;

    public function __construct(
        private PlanogramChangeService $changeService
    ) {}

    /**
     * Recebe payload com mudanças e aplica ao banco de dados
     *
     * Payload esperado:
     * {
     *   "gondola_id": "01234...",
     *   "changes": [
     *     {
     *       "type": "shelf_move",              // Tipo da mudança
     *       "entityType": "shelf",             // Tipo da entidade
     *       "entityId": "01234...",            // ULID da entidade
     *       "data": { ... },                   // Dados a serem atualizados
     *       "timestamp": 1234567890            // Timestamp da mudança
     *     }
     *   ],
     *   "metadata": {
     *     "total_changes": 5,
     *     "last_modified": 1234567890
     *   }
     * }
     */
    public function __invoke(SaveChangesRequest $request)
    {
        $validated = $request->validated();

        try {
            // Inicia transação para garantir atomicidade
            $this->plannerateTenantDatabase()->beginTransaction();

            // Processa mudanças via service
            $changesApplied = $this->changeService->processChanges(
                $validated['gondola_id'],
                $validated['changes']
            );

            // Invalida cache de produtos quando há mudanças em layers/produtos
            $this->invalidateProductsCacheIfNeeded(
                $validated['gondola_id'],
                $validated['changes']
            );

            $this->plannerateTenantDatabase()->commit();

            // Log::info('💾 Mudanças salvas', [
            //     'gondola_id' => $validated['gondola_id'],
            //     'changes_received' => count($validated['changes']),
            //     'changes_applied' => $changesApplied,
            // ]);

            return redirect()->back()->with([
                'success' => "✅ {$changesApplied} mudanças salvas com sucesso!",
                'changes_applied' => $changesApplied,
            ]);
        } catch (\Exception $e) {
            $this->plannerateTenantDatabase()->rollBack();

            Log::error('❌ Erro ao salvar mudanças', [
                'gondola_id' => $validated['gondola_id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Erro ao salvar mudanças: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Invalida cache de produtos quando há mudanças relacionadas a layers/produtos
     *
     * Isso garante que a lista de produtos disponíveis seja atualizada
     * após adicionar/remover produtos das prateleiras
     */
    private function invalidateProductsCacheIfNeeded(string $gondolaId, array $changes): void
    {
        // Verifica se há mudanças que afetam produtos (layer_create, layer_update, product_*)
        $affectsProducts = collect($changes)->contains(function ($change) {
            return in_array($change['type'], [
                'layer_create',
                'layer_update',
                'product_placement',
                'product_update',
                'product_removal',
            ]);
        });

        if (! $affectsProducts) {
            return;
        }

        // Busca gondola e planogram para invalidar cache
        $gondola = Gondola::with('planogram')->find($gondolaId);
        if (! $gondola) {
            return;
        }

        // Invalida cache de produtos usando pattern matching
        // Formato: products_planogram_{planogram_id}_category_{category_id}_client_{client_id}_*
        $cachePattern = sprintf(
            'products_planogram_%s_category_%s_client_%s_*',
            $gondola->planogram_id,
            $gondola->planogram->category_id ?? 'null',
            $gondola->planogram->client_id ?? 'null'
        );

        // Laravel Cache não suporta wildcard delete nativamente
        // Então vamos invalidar as combinações mais comuns
        $pagesToInvalidate = [1, 2, 3]; // Primeiras páginas
        $searchVariations = ['', 'null']; // Com e sem busca
        $usedVariations = ['true', 'false']; // Com e sem filtro de usados

        foreach ($pagesToInvalidate as $page) {
            foreach ($searchVariations as $search) {
                foreach ($usedVariations as $used) {
                    $key = sprintf(
                        'products_planogram_%s_category_%s_client_%s_page_%s_search_%s_used_%s',
                        $gondola->planogram_id,
                        $gondola->planogram->category_id ?? 'null',
                        $gondola->planogram->client_id ?? 'null',
                        $page,
                        $search === '' ? md5('') : $search,
                        $used
                    );
                    Cache::forget($key);
                }
            }
        }

        Log::info('🗑️ Cache de produtos invalidado', [
            'gondola_id' => $gondolaId,
            'planogram_id' => $gondola->planogram_id,
            'pattern' => $cachePattern,
        ]);
    }
}

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
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\PlanogramChangeService;
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
}

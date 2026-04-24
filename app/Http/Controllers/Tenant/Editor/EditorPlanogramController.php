<?php

namespace App\Http\Controllers\Tenant\Editor;

use App\Http\Controllers\Controller;
use App\Models\Gondola;
use App\Models\User;
use App\Support\Tenancy\InteractsWithTenantContext;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Concerns\HasWorkflowToggle;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\GondolaAnalysis;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\GondolaPayloadService;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class EditorPlanogramController extends Controller
{
    use InteractsWithTenantContext, HasWorkflowToggle;

    public function edit(string $subdomain, Gondola $record)
    {
        // Delegar a lógica para o Editor\GondolaController

        $record->load([
            'planogram.gondolas',
            'planogram.category',
            'sections.gondola:id,scale_factor',
            'sections.shelves.segments.layer.product',
        ]);
        // Até aqui vai bem rapido
        $availableUsers = $this->getAvailableUsers($record->tenant_id);
        $recordData = app(GondolaPayloadService::class)->buildEditorPayload($record, $this->isWorkflowEnabled());

        if (
            $this->isWorkflowEnabled() &&
            (! data_get($recordData, 'planogram.gondolas') || data_get($recordData, 'planogram.gondolas') === [])
        ) {
            abort(403, 'Planograma sem gôndolas. Não existe nenhuma gôndola associada a esta etapa do planograma.');
        }

        // Carregar análises mais recentes
        $abcAnalysis = GondolaAnalysis::getLatestAbcAnalysis($record->id);
        $stockAnalysis = GondolaAnalysis::getLatestStockAnalysis($record->id);

        return Inertia::render('tenant/editor/Plannerate', [
            'record' => $recordData,
            'availableUsers' => $availableUsers,
            'abcAnalysis' => $abcAnalysis,
            'stockAnalysis' => $stockAnalysis,
            'aiModelOptions' => $this->getAiModelOptions(),
            'strategyOptions' => $this->getStrategyOptions(),
            'backRoute' => null, // Pode ser ajustado para retornar à visão do planograma ou lista de gôndolas
            'saveChangesRoute' =>null, // A edição é salva via API, então essa rota pode ser nula ou apontar para uma rota de API se necessário
            'analysis' => [
                'abc' => $abcAnalysis?->toAbcFormattedArray(),
                'stock' => $stockAnalysis?->toStockFormattedArray(),
            ],
            'permissions' => [
                'can_create_gondola' => $this->canCreateGondola($record->planogram), // Pode ser ajustado para verificar permissões reais
                'can_update_gondola' => auth()->user()->can('tenant.gondolas.edit'),
                'can_remove_gondola' => auth()->user()->can('tenant.gondolas.delete'), // Exemplo: só pode remover se não tiver seções
                'can_autogenate_gondola' => auth()->user()->can('tenant.gondolas.autogenerate'), // Permissão para autogerar gôndola
                'can_autogenate_gondola_ia' => auth()->user()->can('tenant.gondolas.autogenerate.ia'), // Permissão para autogerar gôndola IA
            ],
        ]);
    }


    protected function getAvailableUsers(string $tenantId): array
    {
        return Cache::remember("tenant_{$tenantId}_users_v2", now()->addMinutes(30), function () use ($tenantId) {
            return User::select('id', 'name')
                ->orderBy('name')
                ->get()
                ->map(static fn($user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                ])
                ->values()
                ->all();
        });
    }


    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    protected function getAiModelOptions(): array
    {
        return [
            [
                'value' => 'gpt-4o-mini',
                'label' => 'GPT-4o Mini',
                'description' => '~$0.10 - Rápido e econômico',
            ],
            [
                'value' => 'gpt-4o',
                'label' => 'GPT-4o',
                'description' => '~$0.50 - Melhor qualidade',
            ],
            [
                'value' => 'claude-sonnet-4-6',
                'label' => 'Claude Sonnet 4.6',
                'description' => '~$0.30 - Anthropic qualidade',
            ],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    protected function getStrategyOptions(): array
    {
        return [
            [
                'value' => 'abc',
                'label' => 'ABC',
                'description' => 'Prioriza produtos A (80% das vendas), depois B, depois C. Ideal para maximizar vendas.',
            ],
            [
                'value' => 'sales',
                'label' => 'Vendas',
                'description' => 'Ordena por volume total de vendas. Produtos mais vendidos têm prioridade.',
            ],
            [
                'value' => 'margin',
                'label' => 'Margem',
                'description' => 'Prioriza produtos com maior margem de contribuição. Foca em lucratividade.',
            ],
            [
                'value' => 'mix',
                'label' => 'Mix',
                'description' => 'Balanceado: 40% ABC + 40% Vendas + 20% Margem. Equilibra vendas e lucro.',
            ],
        ];
    }

    
    protected function canCreateGondola($model): bool
    {
        if (! $this->isWorkflowEnabled()) {
            return auth()->user()->can('tenant.gondolas.create');
        }

        

        return false;
    }
}

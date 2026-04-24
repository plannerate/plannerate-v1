<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate\SectionGenerate;

use Callcocam\LaravelRaptorPlannerate\Ai\Agents\PlanogramSectionAllocator;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\RankedProductDTO;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\SectionGenerate\SectionAllocationResultDTO;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider as PrismProvider;

/**
 * Alocação por IA (Laravel AI SDK) para UMA section.
 *
 * Usa PlanogramSectionAllocator + SectionContextBuilder.
 * Requer OPENAI_API_KEY no .env (Laravel AI lê de config/ai.php do pacote).
 * Em caso de falha (API key ausente, timeout, etc.), retorna alocação vazia para fallback.
 */
class SectionAIAllocator
{
    public function __construct(
        protected SectionContextBuilder $contextBuilder,
        protected PlanogramSectionAllocator $agent,
    ) {}

    /**
     * Alocar produtos na section usando o Agent.
     *
     * @param  Collection<int, RankedProductDTO>  $rankedProducts
     */
    public function allocate(Section $section, Collection $rankedProducts): SectionAllocationResultDTO
    {
        $limit = 40;
        $products = $rankedProducts->take($limit);

        if ($products->isEmpty()) {
            $unallocated = $rankedProducts->map(fn ($rp) => (string) $rp->product->id)->all();

            return new SectionAllocationResultDTO(
                reasoning: 'Nenhum produto para alocar.',
                allocation: [],
                unallocated: $unallocated,
                unallocatedByReason: ['no_products' => count($unallocated)],
                unallocatedReasonByProduct: collect($unallocated)->mapWithKeys(
                    fn ($id) => [(string) $id => 'no_products']
                )->all(),
                allocationDiagnostics: [
                    'split_candidates' => 0,
                    'split_resolved' => 0,
                    'split_failed' => 0,
                ],
            );
        }

        try {
            $context = $this->contextBuilder->build($section, $products);
            $response = $this->agent->prompt($context, provider: PrismProvider::Anthropic->value, model: 'claude-3-5-haiku-latest', timeout: 90);

            return SectionAllocationResultDTO::fromAgentResponse($response->toArray());
        } catch (\Throwable $e) {
            Log::warning('Falha na alocação por IA para section, retornando vazio para fallback externo', [
                'section_id' => $section->id,
                'error' => $e->getMessage(),
            ]);

            $unallocated = $rankedProducts->map(fn ($rp) => (string) $rp->product->id)->all();

            return new SectionAllocationResultDTO(
                reasoning: 'IA indisponível: '.$e->getMessage(),
                allocation: [],
                unallocated: $unallocated,
                unallocatedByReason: ['ai_unavailable' => count($unallocated)],
                unallocatedReasonByProduct: collect($unallocated)->mapWithKeys(
                    fn ($id) => [(string) $id => 'ai_unavailable']
                )->all(),
                allocationDiagnostics: [
                    'split_candidates' => 0,
                    'split_resolved' => 0,
                    'split_failed' => 0,
                ],
            );
        }
    }
}

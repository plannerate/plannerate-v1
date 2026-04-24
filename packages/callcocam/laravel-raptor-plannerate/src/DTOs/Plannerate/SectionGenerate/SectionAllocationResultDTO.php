<?php

namespace Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\SectionGenerate;

use Illuminate\Support\Collection;

/**
 * Resultado da alocação de uma section (resposta do PlanogramSectionAllocator).
 *
 * - allocation: itens alocados (shelf_id, product_id, facings)
 * - reasoning: texto explicativo da IA
 * - unallocated: ids de produtos que não couberam nesta section
 * - unallocatedByReason: contagem de não alocados por motivo
 * - unallocatedReasonByProduct: motivo por id de produto não alocado
 * - allocationDiagnostics: métricas internas da alocação (split candidates/resolved)
 */
readonly class SectionAllocationResultDTO
{
    /** @var array<int, SectionAllocationItemDTO> */
    public array $allocation;

    /** @var array<int, string> */
    public array $unallocated;

    /** @var array<string, int> */
    public array $unallocatedByReason;

    /** @var array<string, string> */
    public array $unallocatedReasonByProduct;

    /** @var array<string, int|float> */
    public array $allocationDiagnostics;

    public function __construct(
        public string $reasoning,
        array $allocation,
        array $unallocated = [],
        array $unallocatedByReason = [],
        array $unallocatedReasonByProduct = [],
        array $allocationDiagnostics = [],
    ) {
        $this->allocation = array_values(array_map(
            fn ($item) => $item instanceof SectionAllocationItemDTO
                ? $item
                : SectionAllocationItemDTO::fromArray(is_array($item) ? $item : []),
            $allocation
        ));
        $this->unallocated = array_values($unallocated);
        $this->unallocatedByReason = $unallocatedByReason;
        $this->unallocatedReasonByProduct = $unallocatedReasonByProduct;
        $this->allocationDiagnostics = $allocationDiagnostics;
    }

    /**
     * Criar a partir da resposta estruturada do Agent (Laravel AI SDK).
     *
     * @param  array{reasoning: string, allocation: array<int, array{shelf_id?: string, product_id?: string, facings?: int}>, unallocated: array<int, string>}  $response
     */
    public static function fromAgentResponse(array $response): self
    {
        $allocation = [];
        foreach ($response['allocation'] ?? [] as $item) {
            if (is_array($item)) {
                $allocation[] = SectionAllocationItemDTO::fromArray($item);
            }
        }

        return new self(
            reasoning: $response['reasoning'] ?? '',
            allocation: $allocation,
            unallocated: $response['unallocated'] ?? [],
            unallocatedByReason: ['unknown' => count($response['unallocated'] ?? [])],
            unallocatedReasonByProduct: collect($response['unallocated'] ?? [])->mapWithKeys(
                fn ($id) => [(string) $id => 'unknown']
            )->all(),
            allocationDiagnostics: [
                'split_candidates' => 0,
                'split_resolved' => 0,
                'split_failed' => 0,
            ],
        );
    }

    public function allocationCollection(): Collection
    {
        return collect($this->allocation);
    }
}

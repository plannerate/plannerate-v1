<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\IAGenerate;

/**
 * DTO de Resultado da Geração de Planogramas com IA
 */
readonly class IAGenerateResultDTO
{
    public function __construct(
        public int $totalAllocated,
        public int $totalUnallocated,
        public array $shelves,
        public array $metadata,
        public string $reasoning,
        public float $confidence,
        public int $tokensUsed,
        public float $executionTime,
    ) {}

    /**
     * Criar DTO a partir de dados brutos
     */
    public static function create(
        int $totalAllocated,
        int $totalUnallocated,
        array $shelves,
        array $metadata = [],
        string $reasoning = '',
        float $confidence = 0.0,
        int $tokensUsed = 0,
        float $executionTime = 0.0,
    ): self {
        return new self(
            totalAllocated: $totalAllocated,
            totalUnallocated: $totalUnallocated,
            shelves: $shelves,
            metadata: $metadata,
            reasoning: $reasoning,
            confidence: $confidence,
            tokensUsed: $tokensUsed,
            executionTime: $executionTime,
        );
    }

    /**
     * Converter para array
     */
    public function toArray(): array
    {
        return [
            'total_allocated' => $this->totalAllocated,
            'total_unallocated' => $this->totalUnallocated,
            'shelves' => $this->shelves,
            'metadata' => $this->metadata,
            'reasoning' => $this->reasoning,
            'confidence' => $this->confidence,
            'tokens_used' => $this->tokensUsed,
            'execution_time' => $this->executionTime,
        ];
    }
}

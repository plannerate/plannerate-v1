<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\IAGenerate;

/**
 * DTO de Configuração para Geração de Planogramas com IA
 */
readonly class IAGenerateConfigDTO
{
    public function __construct(
        public ?string $categoryId,
        public string $strategy,
        public ?string $subcategoryId = null,
        public ?string $brandId = null,
        public bool $respectSeasonality = true,
        public bool $applyVisualGrouping = true,
        public bool $intelligentOrdering = true,
        public bool $loadBalancing = true,
        public ?string $additionalInstructions = null,
        public ?string $model = 'gpt-4o-mini',
        public int $maxTokens = 16000,
        public float $temperature = 0.7,
    ) {}

    /**
     * Criar DTO a partir de array de request validado
     */
    public static function fromArray(array $data): self
    {
        return new self(
            categoryId: $data['category_id'] ?? null,
            strategy: $data['strategy'],
            subcategoryId: $data['subcategory_id'] ?? null,
            brandId: $data['brand_id'] ?? null,
            respectSeasonality: $data['respect_seasonality'] ?? true,
            applyVisualGrouping: $data['apply_visual_grouping'] ?? true,
            intelligentOrdering: $data['intelligent_ordering'] ?? true,
            loadBalancing: $data['load_balancing'] ?? true,
            additionalInstructions: $data['additional_instructions'] ?? null,
            model: $data['model'] ?? 'gpt-4o-mini',
            maxTokens: $data['max_tokens'] ?? 16000,
            temperature: $data['temperature'] ?? 0.7,
        );
    }

    /**
     * Converter para array
     */
    public function toArray(): array
    {
        return [
            'category_id' => $this->categoryId,
            'strategy' => $this->strategy,
            'subcategory_id' => $this->subcategoryId,
            'brand_id' => $this->brandId,
            'respect_seasonality' => $this->respectSeasonality,
            'apply_visual_grouping' => $this->applyVisualGrouping,
            'intelligent_ordering' => $this->intelligentOrdering,
            'load_balancing' => $this->loadBalancing,
            'additional_instructions' => $this->additionalInstructions,
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
        ];
    }
}

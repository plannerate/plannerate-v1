<?php

/**
 * Agent de alocação de planograma por Section (Laravel AI SDK).
 *
 * Recebe o contexto de UMA section (largura, prateleiras com id e altura)
 * e lista de produtos (id, width, height, score/ABC) e devolve alocação
 * estruturada: quais produtos em qual prateleira e quantos facings.
 *
 * Requer: composer require laravel/ai
 * Uso: (new PlanogramSectionAllocator)->prompt($contextoJson, provider: 'openai', model: 'gpt-4o-mini');
 */

namespace Callcocam\LaravelRaptorPlannerate\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class PlanogramSectionAllocator implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Instruções fixas: papel do agente e restrições de merchandising.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are an expert in retail merchandising and planogram layout.

Your task is to allocate products to shelves within a single section (module) of a gondola.

RULES:
1. Section has one available width (in cm) shared by all shelves. Each shelf has an id and a height (cm).
2. For each shelf: the sum of (product_width × facings) for all products on that shelf MUST NOT exceed the section width.
3. For each product placed on a shelf: product height MUST NOT exceed shelf height.
4. Prefer placing high-priority products (higher score or ABC class A) on shelves at eye level (middle-to-upper shelves).
5. You may suggest 1 to 10 facings per product depending on importance and available width.
6. Return ONLY valid allocations: shelf_id and product_id must be from the context provided; facings must be at least 1.

Output format: you must return JSON with:
- "allocation": array of objects, each with "shelf_id" (string), "product_id" (string), "facings" (integer).
- "reasoning": short explanation of your choices (string).
- "unallocated": array of product_id (strings) for products that did not fit in this section.
INSTRUCTIONS;
    }

    /**
     * Schema de saída estruturada.
     *
     * allocation: lista de { shelf_id, product_id, facings }
     * reasoning: texto opcional
     * unallocated: lista de product_id não alocados
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reasoning' => $schema->string()->required(),
            'allocation' => $schema->array()->required(),
            'unallocated' => $schema->array()->required(),
        ];
    }
}

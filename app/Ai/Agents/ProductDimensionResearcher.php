<?php

namespace App\Ai\Agents;

use App\Ai\Tools\FetchCosmosBluesoft;
use App\Ai\Tools\SearchLocalProductDimensions;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\WebSearch;
use Stringable;

#[Provider(Lab::Gemini)]
#[Model('gemini-2.5-flash')]
#[MaxSteps(15)]
class ProductDimensionResearcher implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    public function __construct(public readonly Product $product) {}

    public function instructions(): Stringable|string
    {
        $path = base_path('resources/ai/dimension-researcher-instructions.txt');

        return file_exists($path) ? file_get_contents($path) : '';
    }

    public function tools(): iterable
    {
        return [
            new SearchLocalProductDimensions,
            new FetchCosmosBluesoft,
            (new WebSearch)->max(5)->allow([
                'paodeacucar.com',
                'carrefour.com.br',
                'atacadao.com.br',
                'cosmos.bluesoft.com.br',
                'mercadolivre.com.br',
            ]),
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'found' => $schema->boolean()->required(),
            'width' => $schema->number()->description('Largura em cm'),
            'height' => $schema->number()->description('Altura em cm'),
            'depth' => $schema->number()->description('Profundidade em cm'),
            'weight' => $schema->number()->description('Peso em gramas'),
            'unit' => $schema->string()->description('Unidade das dimensões — sempre "cm"'),
            'source' => $schema->string()
                ->enum(['local_similarity', 'cosmos', 'web_search', 'not_found'])
                ->description('Fonte das dimensões'),
            'source_url' => $schema->string()->description('URL da fonte principal'),
            'confidence' => $schema->string()
                ->enum(['high', 'medium', 'low'])
                ->description('Nível de confiança: high|medium|low'),
            'reasoning' => $schema->string()
                ->description('Explicação em PT-BR de como as dimensões foram obtidas'),
            'warnings' => $schema->array()
                ->items($schema->string())
                ->description('Avisos sobre a qualidade ou limitações dos dados'),
            'similar_product_id' => $schema->string()
                ->description('ID do produto usado como referência (apenas source=local_similarity)'),
        ];
    }
}

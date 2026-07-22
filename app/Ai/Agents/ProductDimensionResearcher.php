<?php

namespace App\Ai\Agents;

use App\Ai\Tools\FetchCosmosBluesoft;
use App\Ai\Tools\SearchLocalProductDimensions;
use App\Models\Product;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Gemini)]
#[Model('gemini-flash-latest')]
#[MaxSteps(15)]
class ProductDimensionResearcher implements Agent, HasTools
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
        ];
    }
}

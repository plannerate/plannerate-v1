<?php

namespace App\Providers;

use App\Services\AutoPlanogram\Adjacency\AdjacencyResolverInterface;
use App\Services\AutoPlanogram\Adjacency\RuleBasedResolver;
use App\Services\AutoPlanogram\Grouping\BlockGrouperInterface;
use App\Services\AutoPlanogram\Grouping\HierarchicalBlockGrouper;
use App\Services\AutoPlanogram\Placement\GreedyShelfPlacer;
use App\Services\AutoPlanogram\Placement\PlacementEngineInterface;
use App\Services\AutoPlanogram\Placement\PlanogramWriter;
use App\Services\AutoPlanogram\Placement\PlanogramWriterInterface;
use App\Services\AutoPlanogram\Scoring\CompositeScorer;
use App\Services\AutoPlanogram\Scoring\ProductScorerInterface;
use App\Services\AutoPlanogram\Validation\PlanogramValidator;
use App\Services\AutoPlanogram\Validation\Rules\AdjacencyRule;
use App\Services\AutoPlanogram\Validation\Rules\BlockIntegrityRule;
use App\Services\AutoPlanogram\Validation\Rules\EmptyShelfRule;
use App\Services\AutoPlanogram\Validation\Rules\FacingMinimumRule;
use App\Services\AutoPlanogram\Validation\Rules\SectionCapacityRule;
use App\Services\AutoPlanogram\Validation\Rules\ShelfLevelRule;
use App\Services\AutoPlanogram\Validation\Rules\UnplacedProductsRule;
use Illuminate\Support\ServiceProvider;

class AutoPlanogramServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProductScorerInterface::class, CompositeScorer::class);
        $this->app->bind(BlockGrouperInterface::class, HierarchicalBlockGrouper::class);
        $this->app->bind(AdjacencyResolverInterface::class, RuleBasedResolver::class);
        $this->app->bind(PlacementEngineInterface::class, GreedyShelfPlacer::class);
        $this->app->bind(PlanogramWriterInterface::class, PlanogramWriter::class);

        $this->app->singleton(PlanogramValidator::class, function () {
            $validator = new PlanogramValidator([
                new BlockIntegrityRule,
                new AdjacencyRule,
                new ShelfLevelRule,
                new FacingMinimumRule,
                new SectionCapacityRule,
                new EmptyShelfRule,
                new UnplacedProductsRule,
            ]);

            return $validator;
        });
    }
}

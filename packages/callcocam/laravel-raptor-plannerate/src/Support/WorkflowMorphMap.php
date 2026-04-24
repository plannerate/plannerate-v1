<?php

namespace Callcocam\LaravelRaptorPlannerate\Support;

use Callcocam\LaravelRaptorPlannerate\Models\Workflow\GondolaWorkflow;
use Callcocam\LaravelRaptorPlannerate\Models\Workflow\PlanogramWorkflow;

final class WorkflowMorphMap
{
    public const LEGACY_PLANOGRAM_WORKFLOW = 'App\\Models\\Workflow\\PlanogramWorkflow';

    public const LEGACY_GONDOLA_WORKFLOW = 'App\\Models\\Workflow\\GondolaWorkflow';

    /**
     * @return array<int, string>
     */
    public static function planogramWorkflowTypes(): array
    {
        return [
            PlanogramWorkflow::class,
            self::LEGACY_PLANOGRAM_WORKFLOW,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function gondolaWorkflowTypes(): array
    {
        return [
            GondolaWorkflow::class,
            self::LEGACY_GONDOLA_WORKFLOW,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function morphAliases(): array
    {
        return [
            self::LEGACY_PLANOGRAM_WORKFLOW => PlanogramWorkflow::class,
            self::LEGACY_GONDOLA_WORKFLOW => GondolaWorkflow::class,
        ];
    }

    public static function normalizeWorkableType(?string $type): ?string
    {
        return match ($type) {
            self::LEGACY_PLANOGRAM_WORKFLOW => PlanogramWorkflow::class,
            self::LEGACY_GONDOLA_WORKFLOW => GondolaWorkflow::class,
            default => $type,
        };
    }

    public static function resolveWorkableModelClass(?string $type): ?string
    {
        $normalized = self::normalizeWorkableType($type);

        return $normalized && class_exists($normalized) ? $normalized : null;
    }
}

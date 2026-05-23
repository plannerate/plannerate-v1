<?php

namespace App\Services\AutoPlanogram;

use App\Services\AutoPlanogram\DTO\PlanogramOutput;

/**
 * Resultado da orquestração de geração (AutoGenerationRunner).
 */
final class AutoGenerationResult
{
    public function __construct(
        public readonly PlanogramOutput $output,
        /** ID do template sintetizado quando a gôndola foi promovida de automático para template-mode */
        public readonly ?string $synthTemplateId,
    ) {}
}

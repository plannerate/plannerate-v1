<?php

namespace App\Services\AutoPlanogram\Validation;

use App\Enums\ValidationSeverity;

final readonly class ValidationResult
{
    /**
     * @param  array<int, string>  $affectedProductIds
     */
    public function __construct(
        public string $ruleName,
        public ValidationSeverity $severity,
        public string $message,
        public array $affectedProductIds = [],
        public ?string $affectedShelfId = null,
        public ?string $affectedSectionId = null,
    ) {}

    /**
     * Create an info-level validation result.
     */
    public static function info(
        string $ruleName,
        string $message,
        array $affectedProductIds = [],
        ?string $affectedShelfId = null,
        ?string $affectedSectionId = null,
    ): self {
        return new self(
            $ruleName,
            ValidationSeverity::Info,
            $message,
            $affectedProductIds,
            $affectedShelfId,
            $affectedSectionId,
        );
    }

    /**
     * Create a warning-level validation result.
     */
    public static function warning(
        string $ruleName,
        string $message,
        array $affectedProductIds = [],
        ?string $affectedShelfId = null,
        ?string $affectedSectionId = null,
    ): self {
        return new self(
            $ruleName,
            ValidationSeverity::Warning,
            $message,
            $affectedProductIds,
            $affectedShelfId,
            $affectedSectionId,
        );
    }

    /**
     * Create an error-level validation result.
     */
    public static function error(
        string $ruleName,
        string $message,
        array $affectedProductIds = [],
        ?string $affectedShelfId = null,
        ?string $affectedSectionId = null,
    ): self {
        return new self(
            $ruleName,
            ValidationSeverity::Error,
            $message,
            $affectedProductIds,
            $affectedShelfId,
            $affectedSectionId,
        );
    }
}

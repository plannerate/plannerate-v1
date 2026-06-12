<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO;

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Validation\ValidationResult;

/**
 * Relatório de validação do planograma gerado.
 *
 * @phpstan-type ReportArray array{passed: bool, warnings: list<string>, results: list<array{rule_name: string, severity: string, message: string, affected_product_ids: list<string>, affected_shelf_id: ?string, affected_section_id: ?string}>, error_count: int, warning_count: int, info_count: int}
 */
final readonly class ValidationReport
{
    /**
     * @param  array<int, ValidationResult>  $results
     */
    public function __construct(
        public bool $passed,
        /** @var list<string> */
        public array $warnings = [],
        public array $results = [],
        public int $errorCount = 0,
        public int $warningCount = 0,
        public int $infoCount = 0,
    ) {}

    public static function passed(): self
    {
        return new self(passed: true);
    }

    /**
     * Create a report from validation results.
     *
     * @param  array<int, ValidationResult>  $results
     */
    public static function fromResults(array $results): self
    {
        $errorCount = 0;
        $warningCount = 0;
        $infoCount = 0;
        $warnings = [];

        foreach ($results as $result) {
            match ($result->severity->value) {
                'error' => $errorCount++,
                'warning' => $warningCount++,
                'info' => $infoCount++,
                default => null,
            };

            if ($result->severity->value === 'warning') {
                $warnings[] = $result->message;
            }
        }

        return new self(
            passed: $errorCount === 0,
            warnings: $warnings,
            results: $results,
            errorCount: $errorCount,
            warningCount: $warningCount,
            infoCount: $infoCount,
        );
    }

    /**
     * @return ReportArray
     */
    public function toArray(): array
    {
        return [
            'passed' => $this->passed,
            'warnings' => $this->warnings,
            'results' => array_map(function (ValidationResult $result) {
                return [
                    'rule_name' => $result->ruleName,
                    'severity' => $result->severity->value,
                    'message' => $result->message,
                    'affected_product_ids' => $result->affectedProductIds,
                    'affected_shelf_id' => $result->affectedShelfId,
                    'affected_section_id' => $result->affectedSectionId,
                ];
            }, $this->results),
            'error_count' => $this->errorCount,
            'warning_count' => $this->warningCount,
            'info_count' => $this->infoCount,
        ];
    }
}

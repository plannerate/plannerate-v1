<?php

namespace App\Services\AutoPlanogram\Template;

final class TemplateImportReport
{
    public int $templatesCreated = 0;

    public int $subtemplatesCreated = 0;

    public int $slotsCreated = 0;

    public int $slotsUpdated = 0;

    /** @var list<string> */
    public array $errors = [];

    /** @var list<string> */
    public array $warnings = [];

    /**
     * Slots cuja categoria não foi resolvida pelo nome informado na planilha.
     * Precisam de ajuste manual no wizard de template.
     *
     * @var list<array{category_name: string, module: int, shelf_order: int, sugestao: string}>
     */
    public array $slotsWithoutCategory = [];

    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}

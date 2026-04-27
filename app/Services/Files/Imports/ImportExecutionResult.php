<?php

namespace App\Services\Files\Imports;

class ImportExecutionResult
{
    public int $rowsProcessed = 0;

    public int $categoriesCreated = 0;

    public int $categoriesUpdated = 0;

    public int $productsLinked = 0;

    /**
     * @var array<int, string>
     */
    public array $warnings = [];

    /**
     * @var array<int, string>
     */
    public array $errors = [];

    public function addWarning(string $warning): void
    {
        $this->warnings[] = $warning;
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }
}

<?php

namespace App\Contracts;

interface ProductImageAiEditor
{
    public function process(string $sourcePath, string $targetPath): string;
}

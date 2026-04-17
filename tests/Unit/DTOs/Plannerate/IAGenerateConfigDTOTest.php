<?php

use App\DTOs\Plannerate\IAGenerate\IAGenerateConfigDTO;

uses(Tests\TestCase::class);

it('uses max tokens default 16000 when not provided', function () {
    $config = IAGenerateConfigDTO::fromArray([
        'strategy' => 'mix',
    ]);

    expect($config->maxTokens)->toBe(16000);
});

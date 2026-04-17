<?php

it('resolves source import commands and legacy aliases', function () {
    $commands = [
        'import:source-main',
        'import:legacy-main',
        'import:source-client',
        'import:legacy-client',
        'import:source-categories',
        'import:legacy-categories',
        'import:source-product-category',
        'import:legacy-product-category',
    ];

    foreach ($commands as $command) {
        $this->artisan($command.' --help')->assertExitCode(0);
    }
});

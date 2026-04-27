<?php

use Tests\TestCase;

uses(TestCase::class);

test('auth split layout uses translation keys for visible marketing copy', function (): void {
    $component = file_get_contents(resource_path('js/layouts/auth/AuthSplitLayout.vue'));
    $translations = require lang_path('pt_BR/app.php');

    expect($component)->toContain("import { useT } from '@/composables/useT'");
    expect($component)->toContain('const { t } = useT();');

    expect($component)->not->toContain('Retail Precision Engine');
    expect($component)->not->toContain('Organize. Integre.');
    expect($component)->not->toContain('Controle.');
    expect($component)->not->toContain('Onde quiser. Quando');
    expect($component)->not->toContain('Eleve seu espaço de varejo');
    expect($component)->not->toContain('Precisão de inventário');
    expect($component)->not->toContain('Sincronização Cloud');
    expect($component)->not->toContain('Implantações globais');

    foreach ([
        'auth_layout.badge',
        'auth_layout.headline_line_1',
        'auth_layout.headline_line_2',
        'auth_layout.headline_highlight_line_1',
        'auth_layout.headline_highlight_line_2',
        'auth_layout.description',
        'auth_layout.stats.inventory_precision',
        'auth_layout.stats.cloud_sync',
        'auth_layout.stats.global_deployments',
    ] as $key) {
        expect(data_get($translations, $key))->toBeString()->not->toBeEmpty();
    }
});

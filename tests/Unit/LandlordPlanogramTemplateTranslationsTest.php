<?php

test('landlord planogram template translation keys are available', function (): void {
    $translations = require lang_path('pt_BR/app.php');

    foreach ([
        'landlord.planogram_templates.navigation',
        'landlord.planogram_templates.title',
        'landlord.planogram_templates.description',
        'landlord.planogram_templates.actions.create',
        'landlord.planogram_templates.actions.import',
        'landlord.planogram_templates.fields.code_name',
        'landlord.planogram_templates.fields.department',
        'landlord.planogram_templates.fields.subtemplates',
        'landlord.planogram_templates.fields.products',
        'landlord.planogram_templates.fields.shared_with',
        'landlord.planogram_templates.fields.status',
        'landlord.planogram_templates.fields.created_at',
        'landlord.planogram_templates.fields.actions',
        'landlord.planogram_templates.status.active',
        'landlord.planogram_templates.status.inactive',
        'landlord.planogram_templates.search_placeholder',
        'landlord.planogram_templates.filter_label',
        'landlord.planogram_templates.clear_label',
        'landlord.planogram_templates.empty',
        'landlord.planogram_templates.empty_action',
        'landlord.planogram_templates.create.description',
        'landlord.planogram_templates.import.title',
        'landlord.planogram_templates.import.description',
        'landlord.planogram_templates.show.confirm_delete',
        'landlord.planogram_templates.shares.title',
        'landlord.planogram_templates.shares.messages.shared',
        'landlord.planogram_templates.messages.created',
        'landlord.planogram_templates.messages.updated',
        'landlord.planogram_templates.messages.imported',
        'landlord.planogram_templates.messages.import_warnings',
        'landlord.planogram_templates.messages.deleted',
    ] as $key) {
        expect(data_get($translations, $key))->toBeString()->not->toBeEmpty();
    }
});

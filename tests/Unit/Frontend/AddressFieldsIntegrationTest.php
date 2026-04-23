<?php

test('address fields component defines nested payload fields', function (): void {
    $content = file_get_contents(resource_path('js/components/form/AddressFields.vue'));

    expect($content)->toContain("prefix: 'address'");
    expect($content)->toContain("inputName('name')");
    expect($content)->toContain("inputName('zip_code')");
    expect($content)->toContain("inputName('street')");
    expect($content)->toContain("inputName('number')");
    expect($content)->toContain("inputName('complement')");
    expect($content)->toContain("inputName('reference')");
    expect($content)->toContain("inputName('additional_information')");
    expect($content)->toContain("inputName('district')");
    expect($content)->toContain("inputName('city')");
    expect($content)->toContain("inputName('state')");
    expect($content)->toContain("inputName('country')");
    expect($content)->toContain("inputName('is_default')");
    expect($content)->toContain("inputName('status')");
});

test('store and provider forms include shared address fields component', function (): void {
    $storeForm = file_get_contents(resource_path('js/pages/tenant/stores/Form.vue'));
    $providerForm = file_get_contents(resource_path('js/pages/tenant/providers/Form.vue'));

    expect($storeForm)->toContain("import AddressFields from '@/components/form/AddressFields.vue'");
    expect($storeForm)->toContain('<AddressFields :errors="errors" />');
    expect($providerForm)->toContain("import AddressFields from '@/components/form/AddressFields.vue'");
    expect($providerForm)->toContain('<AddressFields :errors="errors" />');
});

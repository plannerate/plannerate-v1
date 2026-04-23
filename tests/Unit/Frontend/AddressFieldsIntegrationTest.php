<?php

use Tests\TestCase;

uses(TestCase::class);

test('address fields component defines nested payload fields', function (): void {
    $content = file_get_contents(resource_path('js/components/form/AddressFields.vue'));

    expect($content)->toContain("prefix: 'address'");
    expect($content)->toContain("import CepLookupField from '@/components/form/CepLookupField.vue'");
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

test('store provider and cluster forms include shared address fields component', function (): void {
    $storeForm = file_get_contents(resource_path('js/pages/tenant/stores/Form.vue'));
    $providerForm = file_get_contents(resource_path('js/pages/tenant/providers/Form.vue'));
    $clusterForm = file_get_contents(resource_path('js/pages/tenant/clusters/Form.vue'));

    expect($storeForm)->toContain("import AddressFields from '@/components/form/AddressFields.vue'");
    expect($storeForm)->toContain('<AddressFields :errors="errors" />');
    expect($providerForm)->toContain("import AddressFields from '@/components/form/AddressFields.vue'");
    expect($providerForm)->toContain('<AddressFields :errors="errors" />');
    expect($clusterForm)->toContain("import AddressFields from '@/components/form/AddressFields.vue'");
    expect($clusterForm)->toContain('<AddressFields :errors="errors" />');
});

test('cep lookup component exists and emits resolved payload', function (): void {
    $component = file_get_contents(resource_path('js/components/form/CepLookupField.vue'));

    expect($component)->toContain("emits('resolved'");
    expect($component)->toContain('https://viacep.com.br/ws/');
    expect($component)->toContain('zip_code_lookup_failed');
});

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

test('store and provider forms include shared address fields component', function (): void {
    $storeForm = file_get_contents(resource_path('js/pages/tenant/stores/Form.vue'));
    $providerForm = file_get_contents(resource_path('js/pages/tenant/providers/Form.vue'));

    expect($storeForm)->toContain("import CepLookupField from '@/components/form/CepLookupField.vue'");
    expect($storeForm)->toContain("v-show=\"activeTab === 'identificacao'\"");
    expect($storeForm)->not->toContain("v-show=\"activeTab === 'endereco'\"");
    expect($storeForm)->toContain('name="address[zip_code]"');
    expect($storeForm)->toContain('name="address[street]"');
    expect($storeForm)->toContain('name="address[number]"');
    expect($storeForm)->toContain('name="address[complement]"');
    expect($storeForm)->toContain('name="address[district]"');
    expect($storeForm)->toContain('name="address[city]"');
    expect($storeForm)->toContain('name="address[state]"');
    expect($storeForm)->toContain('name="address[country]"');
    expect($storeForm)->toContain('@resolved="onAddressCepResolved"');
    expect($providerForm)->toContain('name="address[zip_code]"');
    expect($providerForm)->toContain('name="address[street]"');
    expect($providerForm)->toContain('name="address[number]"');
    expect($providerForm)->toContain('name="address[complement]"');
    expect($providerForm)->toContain('name="address[district]"');
    expect($providerForm)->toContain('name="address[city]"');
    expect($providerForm)->toContain('name="address[state]"');
    expect($providerForm)->toContain('name="address[country]"');
});

test('store form includes map editor in store map tab', function (): void {
    $storeForm = file_get_contents(resource_path('js/pages/tenant/stores/Form.vue'));
    $mapField = file_get_contents(resource_path('js/components/form/FormMapField.vue'));

    expect($storeForm)->toContain("import FormMapField from '@/components/form/FormMapField.vue'");
    expect($storeForm)->toContain("v-show=\"activeTab === 'mapa_da_loja'\"");
    expect($storeForm)->toContain('v-model="storeMap"');
    expect($storeForm)->toContain(':visible="activeTab === \'mapa_da_loja\'"');
    expect($mapField)->toContain('`${column.name}[image]`');
    expect($mapField)->toContain('`${column.name}[regions]`');
});

test('store form keeps requested registration field order', function (): void {
    $storeForm = file_get_contents(resource_path('js/pages/tenant/stores/Form.vue'));

    $codePos = strpos($storeForm, 'label="Código loja"');
    $namePos = strpos($storeForm, 'label="Nome da loja"');
    $clusterPos = strpos($storeForm, 'label="Cluster"');
    $cnpjPos = strpos($storeForm, 'label="CNPJ"');
    $emailPos = strpos($storeForm, 'label="Email"');
    $phonePos = strpos($storeForm, 'label="Telefone"');

    $cepPos = strpos($storeForm, 'label="Cep"');
    $streetPos = strpos($storeForm, 'label="Rua"');
    $numberPos = strpos($storeForm, 'label="Numero"');
    $complementPos = strpos($storeForm, 'label="Complemento"');
    $districtPos = strpos($storeForm, 'label="Bairro"');
    $cityPos = strpos($storeForm, 'label="Cidade"');
    $statePos = strpos($storeForm, 'label="Estado"');
    $countryPos = strpos($storeForm, 'label="Pais"');

    expect($codePos)->not->toBeFalse();
    expect($namePos)->not->toBeFalse();
    expect($clusterPos)->not->toBeFalse();
    expect($cnpjPos)->not->toBeFalse();
    expect($emailPos)->not->toBeFalse();
    expect($phonePos)->not->toBeFalse();

    expect($cepPos)->not->toBeFalse();
    expect($streetPos)->not->toBeFalse();
    expect($numberPos)->not->toBeFalse();
    expect($complementPos)->not->toBeFalse();
    expect($districtPos)->not->toBeFalse();
    expect($cityPos)->not->toBeFalse();
    expect($statePos)->not->toBeFalse();
    expect($countryPos)->not->toBeFalse();

    expect($codePos < $namePos)->toBeTrue();
    expect($namePos < $clusterPos)->toBeTrue();
    expect($clusterPos < $cnpjPos)->toBeTrue();
    expect($cnpjPos < $emailPos)->toBeTrue();
    expect($emailPos < $phonePos)->toBeTrue();

    expect($cepPos < $streetPos)->toBeTrue();
    expect($streetPos < $numberPos)->toBeTrue();
    expect($numberPos < $complementPos)->toBeTrue();
    expect($complementPos < $districtPos)->toBeTrue();
    expect($districtPos < $cityPos)->toBeTrue();
    expect($cityPos < $statePos)->toBeTrue();
    expect($statePos < $countryPos)->toBeTrue();
});

test('map canvas keeps a usable zoom and emits pan events used by parent', function (): void {
    $mapCanvas = file_get_contents(resource_path('js/components/form/partials/maps/MapCanvas.vue'));
    $mapField = file_get_contents(resource_path('js/components/form/FormMapField.vue'));

    expect($mapCanvas)->toContain("emit('update:pan-x'");
    expect($mapCanvas)->toContain("emit('update:pan-y'");
    expect($mapCanvas)->toContain('containerWidth <= 0');
    expect($mapCanvas)->toContain('Math.max(0.1, Math.min(scale, 1))');
    expect($mapField)->toContain('fitMapToVisibleContainer');
});

test('cep lookup component exists and emits resolved payload', function (): void {
    $component = file_get_contents(resource_path('js/components/form/CepLookupField.vue'));

    expect($component)->toContain("emits('resolved'");
    expect($component)->toContain('https://viacep.com.br/ws/');
    expect($component)->toContain('zip_code_lookup_failed');
});

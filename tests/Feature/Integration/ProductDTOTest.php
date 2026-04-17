<?php

use App\DTOs\Sysmo\ProductDTO as SysmoProductDTO;
use App\DTOs\Visao\ProductDTO as VisaoProductDTO;
use Illuminate\Support\Str;

beforeEach(function () {
    // Configura IDs fake para testes (sem banco)
    config(['app.current_tenant_id' => Str::ulid()->toString()]);
    
    $this->clientId = Str::ulid()->toString();
    $this->storeId = Str::ulid()->toString();
    $this->userId = Str::ulid()->toString();
    
    $this->params = [
        'client_id' => $this->clientId,
        'store_id' => $this->storeId,
        'tenant_id' => config('app.current_tenant_id'),
        'user_id' => $this->userId,
    ];
});

describe('Sysmo ProductDTO', function () {
    it('processa produto válido da Sysmo', function () {
        $rawData = [
            'produto' => '123456',
            'descricao' => 'Produto Teste',
            'descricao_comercial' => 'Descrição Comercial',
            'cadastro_ativo' => 'S',
            'ativo_na_empresa' => 'S',
            'pertence_ao_mix' => 'S',
            'gtins' => [
                ['gtin' => '7891234567890', 'tipo' => 'EAN13']
            ],
            'marca' => ['descricao' => 'Marca Teste'],
            'unidade_venda_descricao' => 'UN',
            'fornecedores' => [
                [
                    'cpf_cnpj' => '12.345.678/0001-90',
                    'razao_social' => 'Fornecedor Teste LTDA',
                    'fantasia' => 'Fornecedor Teste',
                    'principal' => 'S',
                ]
            ]
        ];

        $dto = new SysmoProductDTO();
        $result = $dto->process($rawData, $this->params);

        expect($result)->toBeArray()
            ->and($result)->toHaveKeys(['product', 'ean', 'additional_data', 'client_product', 'product_store', 'providers'])
            ->and($result['product'])->toHaveKey('id')
            ->and($result['product']['ean'])->toBe('7891234567890')
            ->and($result['product']['name'])->toBe('Produto Teste')
            ->and($result['product']['status'])->toBe('published')
            ->and($result['providers'])->toHaveKeys(['providers', 'pivots'])
            ->and($result['providers']['providers'])->toHaveCount(1)
            ->and($result['providers']['pivots'])->toHaveCount(1);
    });

    it('rejeita produto sem GTIN', function () {
        $rawData = [
            'produto' => '123456',
            'descricao' => 'Produto Sem GTIN',
            'cadastro_ativo' => 'S',
            'ativo_na_empresa' => 'S',
            'pertence_ao_mix' => 'S',
            'gtins' => [], // Vazio
        ];

        $dto = new SysmoProductDTO();
        $result = $dto->process($rawData, $this->params);

        expect($result)->toBeArray()->toBeEmpty();
    });

    it('rejeita produto inativo', function () {
        $rawData = [
            'produto' => '123456',
            'descricao' => 'Produto Inativo',
            'cadastro_ativo' => 'N', // Inativo
            'ativo_na_empresa' => 'S',
            'pertence_ao_mix' => 'S',
            'gtins' => [['gtin' => '7891234567890']],
        ];

        $dto = new SysmoProductDTO();
        $result = $dto->process($rawData, $this->params);

        expect($result)->toBeArray()->toBeEmpty();
    });
});

describe('Visao ProductDTO', function () {
    it('processa produto válido da Visão', function () {
        $rawData = [
            'produto' => 60591,
            'empresa' => 1,
            'descricao' => 'DAREX ABRACADEIRA NYLON4,8X2 C/20 1381B',
            'descricao_comercial' => 'DAREX ABRACADEIRA NYLON4,8X2 C/20 1381B',
            'descricao_reduzida' => 'DAREX ABRACADEIRA NY',
            'cadastro_ativo' => 'S',
            'gtin' => '7896660720975',
            'unidade_medida' => 'UN',
            'marca_descricao' => 'DIVERSOS',
            'departamento_descricao' => 'BAZAR/ELETRODOMESTICOS',
            'segmento_descricao' => 'FERRAMENTAS',
            'subsegmento_descricao' => 'DAREX',
            'fornecedores' => [
                [
                    'codigo' => 50038,
                    'cpf_cnpj' => '82.496.738/0001-56',
                    'fantasia' => 'DAREX LTDA',
                    'principal' => 'S',
                    'razao_social' => 'DAREX COM  DIST  FERR  MAT  E  LTDA',
                ]
            ]
        ];

        $dto = new VisaoProductDTO();
        $result = $dto->process($rawData, $this->params);

        expect($result)->toBeArray()
            ->and($result)->toHaveKeys(['product', 'ean', 'additional_data', 'client_product', 'product_store', 'providers'])
            ->and($result['product']['ean'])->toBe('7896660720975')
            ->and($result['product']['name'])->toBe('DAREX ABRACADEIRA NYLON4,8X2 C/20 1381B')
            ->and($result['product']['status'])->toBe('published')
            ->and($result['additional_data']['brand'])->toBe('DIVERSOS')
            ->and($result['additional_data']['additional_information'])->toContain('FERRAMENTAS')
            ->and($result['providers'])->toHaveKeys(['providers', 'pivots'])
            ->and($result['providers']['providers'])->toHaveCount(1)
            ->and($result['providers']['providers'][0]['code'])->toBe(50038); // code é int na Visão
    });

    it('rejeita produto sem GTIN', function () {
        $rawData = [
            'produto' => 60591,
            'descricao' => 'Produto Sem GTIN',
            'cadastro_ativo' => 'S',
            'gtin' => '', // Vazio
        ];

        $dto = new VisaoProductDTO();
        $result = $dto->process($rawData, $this->params);

        expect($result)->toBeArray()->toBeEmpty();
    });

    it('rejeita produto inativo', function () {
        $rawData = [
            'produto' => 60591,
            'descricao' => 'Produto Inativo',
            'cadastro_ativo' => 'N', // Inativo
            'gtin' => '7896660720975',
        ];

        $dto = new VisaoProductDTO();
        $result = $dto->process($rawData, $this->params);

        expect($result)->toBeArray()->toBeEmpty();
    });
});

describe('IDs Determinísticos', function () {
    it('gera mesmo ID para mesmo EAN + tenant (Sysmo)', function () {
        $rawData = [
            'produto' => '123456',
            'descricao' => 'Produto Teste',
            'cadastro_ativo' => 'S',
            'ativo_na_empresa' => 'S',
            'pertence_ao_mix' => 'S',
            'gtins' => [
                ['gtin' => '7891234567890', 'tipo' => 'EAN13', 'principal' => 'S']
            ],
        ];

        $dto1 = new SysmoProductDTO();
        $result1 = $dto1->process($rawData, $this->params);

        $dto2 = new SysmoProductDTO();
        $result2 = $dto2->process($rawData, $this->params);

        expect($result1)->toBeArray()->not->toBeEmpty()
            ->and($result1['product']['id'])->toBe($result2['product']['id']);
    });

    it('gera mesmo ID para mesmo EAN + tenant (Visão)', function () {
        $rawData = [
            'produto' => 60591,
            'descricao' => 'Produto Teste',
            'cadastro_ativo' => 'S',
            'gtin' => '7896660720975',
        ];

        $dto1 = new VisaoProductDTO();
        $result1 = $dto1->process($rawData, $this->params);

        $dto2 = new VisaoProductDTO();
        $result2 = $dto2->process($rawData, $this->params);

        expect($result1['product']['id'])->toBe($result2['product']['id']);
    });
});


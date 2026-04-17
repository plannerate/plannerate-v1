<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Form\Fields;

use Callcocam\LaravelRaptor\Support\Form\Columns\Types\SectionField;

class VisaoApiField extends SectionField
{
    public function __construct($name, $label = null)
    {
        parent::__construct($name, $label);

        $this->component('form-field-visao-api');

        $this->valueUsing(function ($data) {
            $integrationData = data_get($data, 'client_integration', []);

            if (! is_array($integrationData)) {
                $integrationData = [];
            }

            $integrationData['integration_type'] = 'visao';

            if (! is_array($integrationData['authentication_headers'] ?? null)) {
                $integrationData['authentication_headers'] = [];
            }

            if (! is_array($integrationData['authentication_body'] ?? null)) {
                $integrationData['authentication_body'] = [];
            }

            if (! is_array($integrationData['config'] ?? null)) {
                $integrationData['config'] = [];
            }

            return [
                'client_integration' => $integrationData,
            ];
        })
            ->defaultUsing(function ($model) {
                if ($model && $model->client_integration) {
                    $data = $model->client_integration->toArray();
                    $data['integration_type'] = 'visao';

                    if (! is_array($data['authentication_headers'] ?? null)) {
                        $data['authentication_headers'] = [];
                    }

                    if (! is_array($data['authentication_body'] ?? null)) {
                        $data['authentication_body'] = [
                            'pagina' => '1',
                        ];
                    }

                    if (! is_array($data['config'] ?? null)) {
                        $data['config'] = [
                            'document_name' => 'emp_cnpj',
                            'periodo' => '120',
                        ];
                    }
                    return $data;
                }

                return [
                    'integration_type' => 'visao',
                    'authentication_headers' => [],
                    'authentication_body' => [
                        'pagina' => '1',
                    ],
                    'config' => [
                        'document_name' => 'emp_cnpj',
                        'periodo' => '120',
                    ],
                ];
            })
            ->relationship('client_integration')
            ->nested()
            ->visible(auth()->user()->isAdmin())
            ->collapsible()
            ->fields([
                // integration_type
                \Callcocam\LaravelRaptor\Support\Form\Columns\Types\HiddenField::make('integration_type')
                    ->default('visao'),
                // api_url
                \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('api_url')
                    ->label('URL da API')
                    ->placeholder('Digite a URL da API')
                    ->columnSpanFull(),
                // external_name
                \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('external_name')
                    ->label('Nome do codigo ERP')
                    ->placeholder('Digite o nome do codigo ERP, vindos da integração')
                    ->default('produto')
                    ->columnSpanThree(),
                // external_name_ean
                \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('external_name_ean')
                    ->label('Nome do codigo EAN')
                    ->placeholder('Digite o nome do codigo EAN, vindos da integração')
                    ->default('ean')
                    ->columnSpanThree(),
                // external_name_sale_date
                \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('external_name_sale_date')
                    ->label('Nome da data de venda, Externo')
                    ->default('data_venda')
                    ->placeholder('Digite o nome da data de venda, vindos da integração')
                    ->columnSpanThree(),
                // http_method
                \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('http_method')
                    ->label('Método HTTP')
                    ->options([
                        'GET' => 'GET',
                        'POST' => 'POST',
                    ])
                    ->default('POST')
                    ->columnSpanThree(),
                // authentication_headers
                \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SectionField::make('authentication_headers')
                    ->label('Cabeçalhos HTTP')
                    ->nested()
                    ->fields([
                        \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('auth_username')
                            ->label('Usuário')
                            ->required()
                            ->columnSpanSix(),
                        \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('auth_password')
                            ->label('Senha')
                            ->required()
                            ->columnSpanSix(),
                    ])
                    ->columnSpanFull(),
                // authentication_body
                \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SectionField::make('authentication_body')
                    ->label('Corpo HTTP')
                    ->nested()
                    ->fields([
                        \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('pagina')
                            ->label('Página Inicial')
                            ->required()
                            ->default('1')
                            ->columnSpanSix(),
                        \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('registros_por_pagina')
                            ->label('Registros por Página')
                            ->required()
                            ->default('1000')
                            ->columnSpanSix(),
                    ]),
                \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SectionField::make('config')
                    ->label('Configurações Adicionais')
                    ->nested()
                    ->fields([
                        \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('document_name')
                            ->label('Nome do campo do documento')
                            ->required()
                            ->default('emp_cnpj')
                            ->columnSpanSix(),
                        \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('periodo')
                            ->label('Período de atualização (em dias)')
                            ->required()
                            ->default('365')
                            ->columnSpanSix(),
                    ])->columnSpanFull(),
            ])
            ->columnSpanFull();
    }
}

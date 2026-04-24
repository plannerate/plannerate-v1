<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Form;

use Callcocam\LaravelRaptor\Support\Form\Columns\Types\CheckboxField;
use Callcocam\LaravelRaptor\Support\Form\Columns\Types\NumberField;
use Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField;
use Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField;

class GondolaModalForm
{
    public static function make($model = null, $canCreate = true): array
    {
        $tenant = app('tenant');
        $config = data_get($tenant, 'settings.gondola', config('plannerate.defaults.gondola', []));

        return [
            TextField::make('gondolaName')
                ->label('Nome da Gôndola')
                ->required()
                ->rules(['required', 'string', 'max:255'])
                ->placeholder('Digite o nome da gôndola')
                ->default(data_get($config, 'gondolaName', config('plannerate.defaults.gondola.gondolaName', 'Gôndola Padrão')))
                ->columnSpanFull(),
            SelectField::make('flow')
                ->label('Fluxo de Trabalho')
                ->helperText('Defina o fluxo da gôndola.')
                ->options([
                    [
                        'value' => 'left_to_right',
                        'label' => 'Esquerda para Direita',
                    ],
                    [
                        'value' => 'right_to_left',
                        'label' => 'Direita para Esquerda',
                    ],
                ])
                ->default(data_get($config, 'flow', config('plannerate.defaults.gondola.flow', 'Padrão')))
                ->columnSpanSix(),
            SelectField::make('productType')
                ->label('Tipo de Produto')
                ->required()
                ->options([
                    [
                        'value' => 'normal',
                        'label' => 'Normal',
                    ],
                ])
                ->rules(['required', 'string', 'max:255'])
                ->placeholder('Digite o tipo de produto para esta gôndola')
                ->default(data_get($config, 'productType', config('plannerate.defaults.gondola.productType', 'Indefinido')))
                ->columnSpanSix(),
            NumberField::make('height')
                ->label('Altura')
                ->required()
                ->rules(['required', 'numeric', 'min:0'])
                ->placeholder('Digite a altura da gôndola')
                ->default(data_get($config, 'height', config('plannerate.defaults.gondola.height', 0)))
                ->columnSpanFour(),
            NumberField::make('width')
                ->label('Largura')
                ->required()
                ->rules(['required', 'numeric', 'min:0'])
                ->placeholder('Digite a largura da gôndola')
                ->default(data_get($config, 'width', config('plannerate.defaults.gondola.width', 0)))
                ->columnSpanFour(),
            NumberField::make('numModules')
                ->label('Módulos')
                ->required()
                ->rules(['required', 'numeric', 'min:0'])
                ->placeholder('Digite o número de módulos')
                ->default(data_get($config, 'numModules', config('plannerate.defaults.gondola.num_modules', 0)))
                ->columnSpanFour(),
            NumberField::make('baseHeight')
                ->label('Alt. Base')
                ->required()
                ->rules(['required', 'numeric', 'min:0'])
                ->placeholder('Digite a altura da base da gôndola')
                ->default(data_get($config, 'baseHeight', config('plannerate.defaults.gondola.baseHeight', 0)))
                ->columnSpanFour(),
            NumberField::make('baseDepth')
                ->label('Prof. Base')
                ->required()
                ->rules(['required', 'numeric', 'min:0'])
                ->placeholder('Digite a profundidade da base da gôndola')
                ->default(data_get($config, 'baseDepth', config('plannerate.defaults.gondola.baseDepth', 0)))
                ->columnSpanFour(),
            NumberField::make('rackWidth')
                ->label('Larg. da Cremalheira')
                ->required()
                ->rules(['required', 'numeric', 'min:0'])
                ->placeholder('Digite a largura da cremalheira da gôndola')
                ->default(data_get($config, 'rackWidth', config('plannerate.defaults.gondola.rackWidth', 0)))
                ->columnSpanFour(),
            NumberField::make('holeHeight')
                ->label('Alt. do Furo')
                ->required()
                ->rules(['required', 'numeric', 'min:0'])
                ->placeholder('Digite a altura do furo da gôndola')
                ->default(data_get($config, 'holeHeight', config('plannerate.defaults.gondola.holeHeight', 0)))
                ->columnSpanFour(),
            NumberField::make('holeWidth')
                ->label('Lar. do Furo')
                ->required()
                ->rules(['required', 'numeric', 'min:0'])
                ->placeholder('Digite a largura do furo da gôndola')
                ->default(data_get($config, 'holeWidth', config('plannerate.defaults.gondola.holeWidth', 0)))
                ->columnSpanFour(),
            NumberField::make('holeSpacing')
                ->label('Esp. dos Furos')
                ->required()
                ->rules(['required', 'numeric', 'min:0'])
                ->placeholder('Digite o espaçamento entre os furos da gôndola')
                ->default(data_get($config, 'holeSpacing', config('plannerate.defaults.gondola.holeSpacing', 0)))
                ->columnSpanFour(),
            NumberField::make('shelfHeight')
                ->label('Alt. da Prateleira')
                ->required()
                ->rules(['required', 'numeric', 'min:0'])
                ->placeholder('Digite a altura da prateleira da gôndola')
                ->default(data_get($config, 'shelfHeight', config('plannerate.defaults.gondola.shelfHeight', 0)))
                ->columnSpanFour(),
            NumberField::make('shelfDepth')
                ->label('Prof. da Prateleira')
                ->required()
                ->rules(['required', 'numeric', 'min:0'])
                ->placeholder('Digite a profundidade da prateleira da gôndola')
                ->default(data_get($config, 'shelfDepth', config('plannerate.defaults.gondola.shelfDepth', 0)))
                ->columnSpanFour(),
            NumberField::make('numShelves')
                ->label('Núm. de Prateleiras')
                ->required()
                ->rules(['required', 'numeric', 'min:0'])
                ->placeholder('Digite o número de prateleiras da gôndola')
                ->default(data_get($config, 'numShelves', config('plannerate.defaults.gondola.numShelves', 0)))
                ->columnSpanFour(),
            CheckboxField::make('auto_start_workflow')
                ->label('Iniciar workflow automaticamente')
                ->visibleWhen(function () use ($canCreate) {
                    return $canCreate;
                })
                ->helperText('Se marcado, o workflow será iniciado automaticamente após a criação da gôndola. e vc será o responsavel')
                ->columnSpanFull(),
        ];
    }
}

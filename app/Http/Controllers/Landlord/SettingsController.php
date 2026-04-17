<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Http\Controllers\Landlord;

use Callcocam\LaravelRaptor\Http\Controllers\LandlordController;
use Callcocam\LaravelRaptor\Models\Tenant;
use Callcocam\LaravelRaptor\Support\Form\Columns\Types\NumberField;
use Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField;
use Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField;
use Callcocam\LaravelRaptor\Support\Form\Form;
use Callcocam\LaravelRaptor\Support\Pages\Execute;
use Callcocam\LaravelRaptor\Support\Pages\Index;
use Callcocam\LaravelRaptor\Support\Table\Columns\Types\DateColumn;
use Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn;
use Callcocam\LaravelRaptor\Support\Table\Confirm;
use Callcocam\LaravelRaptor\Support\Table\TableBuilder; 
use Illuminate\Http\Request;

class SettingsController extends LandlordController
{
    /**
     * Define o model que será usado pelo controller
     */
    public function model(): ?string
    {
        return config('raptor.landlord.models.tenant', \Callcocam\LaravelRaptor\Models\Tenant::class);
    }

    public function getPages(): array
    {
        return [
            'index' => Index::route('/configuracoes/inquilinos')
                ->label('Settings')
                ->name('tenants.settings.index')
                ->icon('Settings')
                ->group('Sistema')
                ->groupCollapsible(true)
                ->order(18)
                ->middlewares(['auth', 'verified']),
            'execute' => Execute::route('/configuracoes/inquilinos/execute/actions')
                ->label('Executar Inquilinos')
                ->name('tenants.settings.execute')
                ->middlewares(['auth', 'verified']),
        ];
    }

    protected function table(TableBuilder $table): TableBuilder
    {
        $table->columns([
            TextColumn::make('name')
                ->label('Nome')
                ->sortable()
                ->searchable(),

            TextColumn::make('domain')
                ->label('Domínio')
                ->sortable()
                ->searchable(),

            DateColumn::make('created_at')
                ->label('Criado em')
                ->format('d/m/Y H:i')
                ->sortable(),
            DateColumn::make('updated_at')
                ->label('Atualizado em')
                ->format('d/m/Y H:i')
                ->sortable(),
        ]);

        $table->filters([
            \Callcocam\LaravelRaptor\Support\Table\Filters\SelectFilter::make('status')
                ->label('Status')
                ->options([
                    'draft' => 'Rascunho',
                    'published' => 'Publicado',
                ]),
            \Callcocam\LaravelRaptor\Support\Table\Filters\TrashedFilter::make(),
        ]);

        $table->actions(function (?Tenant $model) {

            return [
                \Callcocam\LaravelRaptor\Support\Actions\Types\ModalAction::make('tenants.settings.gondolas')
                    ->label('Gôndolas')
                    ->slideoverRight()
                    ->actionType('actions')
                    ->modalTitle('Gôndolas - Configuração inicial da gôndola')
                    ->modalDescription('Configuração inicial da gôndola')
                    ->url(function ($target, $request = null) {
                        return route('landlord.tenants.settings.execute', ['record' => $request->route('record')]);
                    })
                    ->columns([
                        // "gondolaName" => "GND-2602-1841"
                        TextField::make('gondolaName')
                            ->label('Nome da gôndola')
                            ->default(data_get($model, 'settings.gondola.gondolaName', 'GND-2602-1841'))
                            ->placeholder('Nome da gôndola')
                            ->columnSpanFull(),
                        // "location" => "Corredor 1"
                        TextField::make('location')
                            ->label('Localização')
                            ->default(data_get($model, 'settings.gondola.location', 'Corredor 1'))
                            ->required()
                            ->rules(['required', 'string', 'max:255'])
                            ->placeholder('Localização')
                            ->columnSpanFull(),
                        // "side" => "A"
                        TextField::make('side')
                            ->label('Lado')
                            ->default(data_get($model, 'settings.gondola.side', 'A'))
                            ->required()
                            ->rules(['required', 'string', 'max:255'])
                            ->placeholder('Lado')
                            ->columnSpan(3),
                        // "scaleFactor" => 3
                        NumberField::make('scaleFactor')
                            ->label('Fator de escala')
                            ->default(data_get($model, 'settings.gondola.scaleFactor', 3))
                            ->required()
                            ->rules(['required', 'numeric', 'min:1'])
                            ->placeholder('Fator de escala')
                            ->columnSpan(3),
                        // "flow" => "left_to_right"
                        SelectField::make('flow')
                            ->label('Fluxo')
                            ->default(data_get($model, 'settings.gondola.flow', 'left_to_right'))
                            ->required()
                            ->rules(['required', 'string', 'max:255'])
                            ->placeholder('Fluxo')
                            ->options([
                                'left_to_right' => 'Esquerda para direita',
                                'right_to_left' => 'Direita para esquerda',
                            ])
                            ->columnSpan(6),
                        // "height" => 200
                        NumberField::make('height')
                            ->label('Altura')
                            ->default(data_get($model, 'settings.gondola.height', 200))
                            ->required()
                            ->rules(['required', 'numeric', 'min:1'])
                            ->placeholder('Altura')
                            ->columnSpan(4),
                        // "width" => 100
                        NumberField::make('width')
                            ->label('Largura')
                            ->default(data_get($model, 'settings.gondola.width', 100))
                            ->required()
                            ->rules(['required', 'numeric', 'min:1'])
                            ->placeholder('Largura')
                            ->columnSpan(4),
                        // "numModules" => 4
                        NumberField::make('numModules')
                            ->label('Nº de módulos')
                            ->default(data_get($model, 'settings.gondola.numModules', 4))
                            ->required()
                            ->rules(['required', 'numeric', 'min:1'])
                            ->placeholder('Nº de módulos')
                            ->columnSpan(4),
                        // "baseHeight" => 20
                        NumberField::make('baseHeight')
                            ->label('Altura da base')
                            ->default(data_get($model, 'settings.gondola.baseHeight', 20))
                            ->required()
                            ->rules(['required', 'numeric', 'min:1'])
                            ->placeholder('Altura da base')
                            ->columnSpan(4),
                        // "baseWidth" => 100
                        NumberField::make('baseWidth')
                            ->label('Largura da base')
                            ->default(data_get($model, 'settings.gondola.baseWidth', 100))
                            ->required()
                            ->rules(['required', 'numeric', 'min:1'])
                            ->placeholder('Largura da base')
                            ->columnSpan(4),
                        // "baseDepth" => 50
                        NumberField::make('baseDepth')
                            ->label('Prof. da Base')
                            ->default(data_get($model, 'settings.gondola.baseDepth', 50))
                            ->required()
                            ->rules(['required', 'numeric', 'min:1'])
                            ->placeholder('Prof. da Base')
                            ->columnSpan(4),
                        // "rackWidth" => 4
                        NumberField::make('rackWidth')
                            ->label('Larg. da Cremalheira')
                            ->default(data_get($model, 'settings.gondola.rackWidth', 4))
                            ->required()
                            ->rules(['required', 'numeric', 'min:1'])
                            ->placeholder('Larg. da Cremalheira')
                            ->columnSpan(4),
                        // "holeHeight" => 3
                        NumberField::make('holeHeight')
                            ->label('Alt. do Furo')
                            ->default(data_get($model, 'settings.gondola.holeHeight', 3))
                            ->required()
                            ->rules(['required', 'numeric', 'min:1'])
                            ->placeholder('Alt. do Furo')
                            ->columnSpan(4),
                        // "holeWidth" => 2
                        NumberField::make('holeWidth')
                            ->label('Larg. do Furo')
                            ->default(data_get($model, 'settings.gondola.holeWidth', 2))
                            ->required()
                            ->rules(['required', 'numeric', 'min:1'])
                            ->placeholder('Larg. do Furo')
                            ->columnSpan(4),
                        // "holeSpacing" => 2
                        NumberField::make('holeSpacing')
                            ->label('Esp. vertical do Furo')
                            ->default(data_get($model, 'settings.gondola.holeSpacing', 2))
                            ->required()
                            ->rules(['required', 'numeric', 'min:1'])
                            ->placeholder('Esp. vertical do Furo')
                            ->columnSpan(4),
                        // "shelfHeight" => 4
                        NumberField::make('shelfHeight')
                            ->label('Esp. da Prateleira')
                            ->default(data_get($model, 'settings.gondola.shelfHeight', 4))
                            ->required()
                            ->rules(['required', 'numeric', 'min:1'])
                            ->placeholder('Esp. da Prateleira')
                            ->columnSpan(4),
                        // "shelfWidth" => 100
                        NumberField::make('shelfWidth')
                            ->label('Larg. da Prateleira')
                            ->default(data_get($model, 'settings.gondola.shelfWidth', 100))
                            ->required()
                            ->rules(['required', 'numeric', 'min:1'])
                            ->placeholder('Larg. da Prateleira')
                            ->columnSpan(4),
                        // "shelfDepth" => 40
                        NumberField::make('shelfDepth')
                            ->label('Prof. da Prateleira')
                            ->required()
                            ->default(data_get($model, 'settings.gondola.shelfDepth', 40))
                            ->rules(['required', 'numeric', 'min:1'])
                            ->placeholder('Prof. da Prateleira')
                            ->columnSpan(4),
                        // "numShelves" => 4
                        NumberField::make('numShelves')
                            ->label('Nº de Prateleiras')
                            ->required()
                            ->default(data_get($model, 'settings.gondola.numShelves', 4))
                            ->rules(['required', 'numeric', 'min:1'])
                            ->placeholder('Nº de Prateleiras')
                            ->columnSpan(4),
                        // "productType" => "normal"
                        SelectField::make('productType')
                            ->label('Tipo de Produto')
                            ->required()
                            ->default(data_get($model, 'settings.gondola.productType', 'normal'))
                            ->rules(['required', 'string', 'max:255'])
                            ->placeholder('Tipo de Produto')
                            ->options([
                                'normal' => 'Normal',
                                'special' => 'Especial',
                            ])
                            ->columnSpan(4),
                        // "notes" => null
                    ])
                    ->callback(function (Request $request, $data = [], $model = null) {
                        if (! $model) {
                            $model = $this->model()::findOrFail($request->input('id'));
                        }
                        $settings = data_get($model, 'settings', []);
                        $gondola = data_get($settings, 'gondola', []);
                        $gondola = array_merge($gondola, [
                            'gondolaName' => $request->input('gondolaName'),
                            'location' => $request->input('location'),
                            'side' => $request->input('side'),
                            'scaleFactor' => $request->input('scaleFactor'),
                            'flow' => $request->input('flow'),
                            'height' => $request->input('height'),
                            'width' => $request->input('width'),
                            'numModules' => $request->input('numModules'),
                            'baseHeight' => $request->input('baseHeight'),
                            'baseWidth' => $request->input('baseWidth'),
                            'baseDepth' => $request->input('baseDepth'),
                            'rackWidth' => $request->input('rackWidth'),
                            'holeHeight' => $request->input('holeHeight'),
                            'holeWidth' => $request->input('holeWidth'),
                            'holeSpacing' => $request->input('holeSpacing'),
                            'shelfHeight' => $request->input('shelfHeight'),
                            'shelfWidth' => $request->input('shelfWidth'),
                            'shelfDepth' => $request->input('shelfDepth'),
                            'numShelves' => $request->input('numShelves'),
                            'productType' => $request->input('productType'),
                            'notes' => $request->input('notes'),
                        ]);
                        $settings['gondola'] = $gondola;
                        $model->settings = $settings;
                        $model->save();

                        return redirect()->back()->with('success', 'Configurações salvas com sucesso.');
                    }),

            ];
        });

        $table->bulkActions([
            //
        ]);

        $table->headerActions([
            \Callcocam\LaravelRaptor\Support\Actions\Types\ConfirmAction::make('tenants.settings.confirm')
                ->label('Modelos')
                ->actionType('header')
                ->icon('File')
                ->url(function ($target, $request = null) {
                    return route('landlord.tenants.settings.execute', ['record' => $request->route('record')]);
                })
                ->emptyRecordAllowed()
                ->confirm(
                    Confirm::make('Tem certeza que deseja importar os modelos?')
                )
                ->callback(function ($request = null) {
                    dd($request->all());

                    return route('tenants.settings.execute', ['record' => $request->route('record')]);
                }),
        ]);

        return $table;
    }

    protected function form(Form $form): Form
    {
        $form->columns([]);

        return $form;
    }

    /**
     * Define o resource path para as views
     */
    protected function resourcePath(): string
    {
        return 'landlord';
    }

    protected function beforeDelete(string $id): void {}

    protected function beforeForceDelete(string $id): void {}

    protected function afterCreate(Request $request, $model): void {}

    protected function afterUpdate(Request $request, $model): void {}

    protected function afterDelete(string $id, $model): void
    {
        //
    }

    protected function afterRestore(string $id, $model): void {}
}

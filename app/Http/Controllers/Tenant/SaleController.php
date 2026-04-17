<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\HasClientVisibilityRule;
use App\Models\Store;
use Callcocam\LaravelRaptor\Http\Controllers\AbstractController;
use Callcocam\LaravelRaptor\Support\Form\Form;
use Callcocam\LaravelRaptor\Support\Info\InfoList;
use Callcocam\LaravelRaptor\Support\Pages\Create;
use Callcocam\LaravelRaptor\Support\Pages\Edit;
use Callcocam\LaravelRaptor\Support\Pages\Execute;
use Callcocam\LaravelRaptor\Support\Pages\Index;
use Callcocam\LaravelRaptor\Support\Table\TableBuilder;
use Illuminate\Database\Eloquent\Builder;

class SaleController extends AbstractController
{
    use HasClientVisibilityRule;

    protected function queryBuilder(): Builder
    {
        return app($this->model())->newQuery()->orderBy('sale_date', 'desc');
    }

    public function getPages(): array
    {
        return [
            'index' => Index::route('/sales')
                ->label('Vendas')
                ->name('sales.index')
                ->icon('ChartLine')
                ->group('Analytics')
                ->groupCollapsible(true)
                ->order(30)
                ->visible(fn () => $this->hasCurrentClientContext())
                ->middlewares(['auth', 'verified']),
            'create' => Create::route('/sales/create')
                ->label('Criar Sale')
                ->name('sales.create')
                ->visible(fn () => $this->hasCurrentClientContext())
                ->middlewares(['auth', 'verified']),
            'edit' => Edit::route('/sales/{record}/edit')
                ->label('Editar Sale')
                ->name('sales.edit')
                ->visible(fn () => $this->hasCurrentClientContext())
                ->middlewares(['auth', 'verified']),
            'execute' => Execute::route('/sales/execute/actions')
                ->label('Executar Sale')
                ->name('sales.execute')
                ->visible(fn () => $this->hasCurrentClientContext())
                ->middlewares(['auth', 'verified']),
        ];
    }

    protected function infolist(InfoList $infoList): InfoList
    {
        $infoList->columns([
            \Callcocam\LaravelRaptor\Support\Info\Columns\Types\CardColumn::make('info')
                ->title('Informações da Venda')
                ->description('Identificação')
                ->collapsible(true, true)
                ->columns([
                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('ean')
                        ->label('EAN')
                        ->icon('Barcode'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('codigo_erp')
                        ->label('Código ERP')
                        ->icon('Code'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\DateColumn::make('sale_date')
                        ->label('Data da Venda')
                        ->format('d/m/Y')
                        ->icon('Calendar'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('promotion')
                        ->label('Promoção')
                        ->icon('Tag'),
                ]),

            \Callcocam\LaravelRaptor\Support\Info\Columns\Types\CardColumn::make('valores')
                ->title('Valores e Margens')
                ->description('Financeiro')
                ->collapsible(true, true)
                ->columns([
                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('acquisition_cost')
                        ->label('Custo de Aquisição')
                        // ->money('BRL')
                        ->icon('DollarSign'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('sale_price')
                        ->label('Preço de Venda')
                        // ->money('BRL')
                        ->icon('ShoppingBag'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('total_profit_margin')
                        ->label('Margem de Lucro Unitária')
                        ->suffix('%')
                        ->icon('TrendingUp'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('margem_contribuicao')
                        ->label('Margem de Contribuição')
                        // ->money('BRL')
                        ->icon('PiggyBank'),
                ]),

            \Callcocam\LaravelRaptor\Support\Info\Columns\Types\CardColumn::make('quantidade')
                ->title('Quantidade e Total')
                ->description('Volume de vendas')
                ->collapsible(true, true)
                ->columns([
                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('total_sale_quantity')
                        ->label('Quantidade Vendida')
                        ->icon('Package'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('total_sale_value')
                        ->label('Valor Total da Venda')
                        // ->money('BRL')
                        ->icon('DollarSign'),
                ]),

            \Callcocam\LaravelRaptor\Support\Info\Columns\Types\DateColumn::make('created_at')
                ->label('Criado em')
                ->format('d/m/Y H:i'),

            \Callcocam\LaravelRaptor\Support\Info\Columns\Types\DateColumn::make('updated_at')
                ->label('Atualizado em')
                ->format('d/m/Y H:i'),
        ]);

        return $infoList;
    }

    protected function form(Form $form): Form
    {
        $form->columns([
            // \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('client_id')
            //     ->label('Cliente')
            //     ->relationship('client', 'name')
            //     ->required()
            //     ->searchable()
            //     ->columnSpan(6),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('store_id')
                ->label('Loja')
                ->options(Store::query()
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray())
                ->required()
                ->columnSpan(6),

            // \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('product_id')
            //     ->label('Produto')
            //     ->relationship('product', 'name')
            //     ->required()
            //     ->searchable()
            //     ->columnSpan(6),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('ean')
                ->label('EAN - Código de Barras')
                ->rules(['string', 'max:13'])
                ->placeholder('Digite o EAN')
                ->columnSpan(6),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('codigo_erp')
                ->label('Código ERP')
                ->rules(['string', 'max:255'])
                ->placeholder('Código no sistema ERP')
                ->columnSpan(6),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\DateField::make('sale_date')
                ->label('Data da Venda')
                ->format('d-m-Y')
                ->required()
                ->columnSpan(6),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('promotion')
                ->label('Promoção')
                ->rules(['string', 'max:255'])
                ->placeholder('Nome da promoção (se houver)')
                ->columnSpan(6),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\MoneyField::make('acquisition_cost')
                ->label('Custo de Aquisição (R$)')
                ->required()
                ->rules(['required', 'numeric', 'min:0'])
                ->prefix('R$')
                ->columnSpan(3),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\MoneyField::make('sale_price')
                ->label('Preço de Venda (R$)')
                ->required()
                ->rules(['required', 'numeric', 'min:0'])
                ->prefix('R$')
                ->columnSpan(3),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\MoneyField::make('total_profit_margin')
                ->label('Margem de Lucro Unitária (%)')
                ->rules(['numeric', 'min:0'])
                ->suffix('%')
                ->columnSpan(3),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\MoneyField::make('margem_contribuicao')
                ->label('Margem de Contribuição (R$)')
                ->rules(['numeric'])
                ->prefix('R$')
                ->helperText('total_sale_value - impostos - custo_medio')
                ->columnSpan(3),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\NumberField::make('total_sale_quantity')
                ->label('Quantidade Vendida')
                ->required()
                ->rules(['required', 'integer', 'min:1'])
                ->default(1)
                ->columnSpan(6),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\NumberField::make('total_sale_value')
                ->label('Valor Total da Venda (R$)')
                ->required()
                ->rules(['required', 'numeric', 'min:0'])
                ->step(0.01)
                ->prefix('R$')
                ->columnSpan(6),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextareaField::make('extra_data')
                ->label('Dados Extras da API')
                ->placeholder('JSON com empresa_id, promoção, impostos, etc.')
                ->helperText('Dados extras vindos da API de integração')
                ->rows(4)
                ->columnSpanFull(),
        ]);

        return $form;
    }

    protected function table(TableBuilder $table): TableBuilder
    {
        $table->columns([
            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\DateColumn::make('sale_date')
                ->label('Data')
                ->format('d/m/Y')
                ->sortable()
                ->searchable(),

            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('store.name')
                ->label('Loja'),

            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('codigo_erp')
                ->label('Código ERP')
                ->searchable()
                ->sortable()
                ->limit(30),

            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('ean')
                ->label('EAN')
                ->searchable(),

            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('total_sale_quantity')
                ->label('Qtd.')
                ->sortable(),

            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('sale_price')
                ->label('Preço Unit.')
                // ->money('BRL')
                ->sortable(),

            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('total_sale_value')
                ->label('Total')
                // ->money('BRL')
                ->sortable(),

            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('total_profit_margin')
                ->label('Margem %')
                ->suffix('%')
                ->sortable(),

            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('promotion')
                ->label('Promoção')
                ->badge()
                ->color('info'),

            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\DateColumn::make('created_at')
                ->label('Cadastrado em')
                ->format('d/m/Y H:i')
                ->sortable(),
        ]);

        $table->filters([
            \Callcocam\LaravelRaptor\Support\Table\Filters\DateRangeFilter::make('sale_date')
                ->label('Período de Vendas'),

            \Callcocam\LaravelRaptor\Support\Table\Filters\SelectFilter::make('client_id')
                ->label('Cliente')
                ->options(function () {
                    return \App\Models\Client::orderBy('name')->pluck('name', 'id')->toArray();
                }),

            // \Callcocam\LaravelRaptor\Support\Table\Filters\RelationshipFilter::make('store_id')
            //     ->label('Loja')
            //     ->relationship('store', 'name'),

            // \Callcocam\LaravelRaptor\Support\Table\Filters\RelationshipFilter::make('product_id')
            //     ->label('Produto')
            //     ->relationship('product', 'name'),

            \Callcocam\LaravelRaptor\Support\Table\Filters\TrashedFilter::make(),
        ]);

        $table->actions([
            \Callcocam\LaravelRaptor\Support\Actions\Types\ViewAction::make('sales.show'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\EditAction::make('sales.edit'),

            // Edição Rápida
            // \Callcocam\LaravelRaptor\Support\Actions\Types\ModalAction::make('update')
            //     ->label('Edição Rápida')
            //     ->slideoverRight()
            //     ->columns([
            //         \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('name')
            //             ->label('Nome')
            //             ->required()
            //             ->columnSpanFull(),
            //         \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('slug')
            //             ->label('Slug')
            //             ->required()
            //             ->columnSpanFull(),
            //         \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextareaField::make('description')
            //             ->label('Descrição')
            //             ->columnSpanFull(),
            //     ]),

            \Callcocam\LaravelRaptor\Support\Actions\Types\RestoreAction::make('sales.restore'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\ForceDeleteAction::make('sales.forceDelete'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\DeleteAction::make('sales.destroy'),
        ]);

        $table->bulkActions([
            // Bulk actions
        ]);

        $table->headerActions([
            \Callcocam\LaravelRaptor\Support\Actions\Types\CreateAction::make('sales.create'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\ExportAction::make('sales.export'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\ImportAction::make('sales.import'),
        ]);

        return $table;
    }

    protected function resourcePath(): ?string
    {
        return 'tenant';
    }
}

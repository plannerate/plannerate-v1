<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 *
 * Controller para gerenciar dimensões dos produtos.
 * As dimensões agora são campos diretos na tabela products (width, height, depth, weight, unit, dimension_status).
 */

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\HasClientVisibilityRule;
use App\Models\Product;
use App\Services\BuscaDimensoesPorEan;
use Callcocam\LaravelRaptor\Http\Controllers\AbstractController;
use Callcocam\LaravelRaptor\Support\Form\Form;
use Callcocam\LaravelRaptor\Support\Info\InfoList;
use Callcocam\LaravelRaptor\Support\Pages\Index;
use Callcocam\LaravelRaptor\Support\Table\TableBuilder;
use InvalidArgumentException;

/**
 * Controller para gerenciar dimensões dos produtos.
 * Usa o Model Product diretamente, já que as dimensões são campos diretos na tabela products.
 */
class DimensionController extends AbstractController
{
    use HasClientVisibilityRule;

    /**
     * Retorna o model usado pelo controller
     */
    public function model(): ?string
    {
        return Product::class;
    }

    /**
     * Retorna o nome curto do recurso
     */
    protected function getResourceLabel(): ?string
    {
        return 'Dimensão do Produto';
    }

    /**
     * Retorna o label do nome plural do recurso
     */
    protected function getResourcePluralLabel(): ?string
    {
        return 'Dimensões dos Produtos';
    }

    public function getPages(): array
    {
        return [
            'index' => Index::route('/dimensions')
                ->label('Dimensões')
                ->name('dimensions.index')
                ->icon('AlignHorizontalSpaceAround')
                ->group('Catálogo')
                ->groupCollapsible(true)
                ->order(16)
                ->visible(fn () => $this->hasCurrentClientContext())
                ->middlewares(['auth', 'verified'])
                ->resource(Product::class),
        ];
    }

    protected function infolist(InfoList $infoList): InfoList
    {
        $infoList->columns([
            \Callcocam\LaravelRaptor\Support\Info\Columns\Types\CardColumn::make('info')
                ->title('Informações do Produto')
                ->description('Dados básicos do produto')
                ->collapsible(true, true)
                ->columns([
                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('name')
                        ->label('Nome do Produto')
                        ->icon('Package'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('ean')
                        ->label('EAN')
                        ->icon('Barcode'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('unit')
                        ->label('Unidade de Medida')
                        ->icon('Ruler'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('dimension_status')
                        ->label('Status da Dimensão')
                        ->badge()
                        ->format(fn ($value) => $value === 'published' ? 'Publicado' : 'Rascunho')
                        ->color(fn ($value) => $value === 'published' ? 'success' : 'secondary'),
                ]),

            \Callcocam\LaravelRaptor\Support\Info\Columns\Types\CardColumn::make('dimensions')
                ->title('Dimensões')
                ->description('Medidas físicas do produto')
                ->collapsible(true, true)
                ->columns([
                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('height')
                        ->label('Altura (cm)')
                        ->icon('ArrowUpDown')
                        ->format(fn ($value) => $value ? number_format($value, 2, ',', '.').' cm' : 'N/A'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('width')
                        ->label('Largura (cm)')
                        ->icon('Maximize2')
                        ->format(fn ($value) => $value ? number_format($value, 2, ',', '.').' cm' : 'N/A'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('depth')
                        ->label('Profundidade (cm)')
                        ->icon('Move3d')
                        ->format(fn ($value) => $value ? number_format($value, 2, ',', '.').' cm' : 'N/A'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('weight')
                        ->label('Peso (g)')
                        ->icon('Weight')
                        ->format(fn ($value) => $value ? number_format($value, 2, ',', '.').' g' : 'N/A'),
                ]),

            \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('description')
                ->label('Descrição do Produto')
                ->columnSpanFull(),

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
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('id')
                ->label('Produto')
                ->required()
                ->rules(['required'])
                ->options(function () {
                    return Product::query()
                        ->whereNotNull('ean')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->searchable()
                ->columnSpan(12)
                ->helperText('Selecione o produto para editar suas dimensões'),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('unit')
                ->label('Unidade de Medida')
                ->required()
                ->rules(['required', 'string', 'max:10'])
                ->options([
                    'cm' => 'CM - Centímetros',
                    'mm' => 'MM - Milímetros',
                    'm' => 'M - Metros',
                    'in' => 'IN - Polegadas',
                ])
                ->default('cm')
                ->columnSpan(6),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('dimension_status')
                ->label('Status da Dimensão')
                ->required()
                ->rules(['required'])
                ->options([
                    'draft' => 'Rascunho',
                    'published' => 'Publicado',
                ])
                ->default('published')
                ->columnSpan(6),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\NumberField::make('height')
                ->label('Altura (cm)')
                ->rules(['nullable', 'numeric', 'min:0'])
                ->step(0.01)
                ->placeholder('0.00')
                ->columnSpan(3)
                ->helperText('Altura do produto em centímetros'),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\NumberField::make('width')
                ->label('Largura (cm)')
                ->rules(['nullable', 'numeric', 'min:0'])
                ->step(0.01)
                ->placeholder('0.00')
                ->columnSpan(3)
                ->helperText('Largura do produto em centímetros'),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\NumberField::make('depth')
                ->label('Profundidade (cm)')
                ->rules(['nullable', 'numeric', 'min:0'])
                ->step(0.01)
                ->placeholder('0.00')
                ->columnSpan(3)
                ->helperText('Profundidade do produto em centímetros'),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\NumberField::make('weight')
                ->label('Peso (gramas)')
                ->rules(['nullable', 'numeric', 'min:0'])
                ->step(0.01)
                ->placeholder('0.00')
                ->columnSpan(3)
                ->helperText('Peso do produto em gramas'),
        ]);

        return $form;
    }

    protected function table(TableBuilder $table): TableBuilder
    {
        $table->columns([

            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('name')
                ->label('Nome')
                ->searchable()
                ->sortable()->columnSpanFour(),

            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('ean')
                ->label('EAN')
                ->searchable()
                ->sortable()->columnSpanTwo(),

            // height, width, depth
            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('height')
                ->label('Altura')
                ->searchable()
                ->sortable()
                ->editable()
                ->executeUrl(route('tenant.dimensions.execute'))
                ->columnSpanTwo(),
            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('width')
                ->label('Largura')
                ->searchable()
                ->sortable()
                ->editable()
                ->executeUrl(route('tenant.dimensions.execute'))
                ->columnSpanTwo(),
            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('depth')
                ->label('Profundidade')
                ->searchable()
                ->sortable()
                ->editable()
                ->executeUrl(route('tenant.dimensions.execute'))
                ->columnSpanTwo(),
        ]);

        $table->filters([
            \Callcocam\LaravelRaptor\Support\Table\Filters\SelectFilter::make('dimension_status')
                ->label('Status da Dimensão')
                ->options([
                    'draft' => 'Rascunho',
                    'published' => 'Publicado',
                ]),

            \Callcocam\LaravelRaptor\Support\Table\Filters\SelectFilter::make('unit')
                ->label('Unidade')
                ->options([
                    'cm' => 'CM',
                    'mm' => 'MM',
                    'm' => 'M',
                    'in' => 'IN',
                ]),

            \Callcocam\LaravelRaptor\Support\Table\Filters\TrashedFilter::make(),
        ]);

        $table->actions([
            // \Callcocam\LaravelRaptor\Support\Actions\Types\EditAction::make('dimensions.edit'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\ConfirmAction::make('dimensions.searchByEan')
                ->label('Buscar por EAN')
                ->actionType('actions')
                ->hidden(fn ($record) => ! empty($record->width))
                ->callback(function (?\Illuminate\Http\Request $request = null) {
                    $request->validate(['ean' => 'required|string|max:20']);
                    $product = Product::where('ean', $request->input('ean'))->first();
                    if (! $product) {
                        return [
                            'notification' => [
                                'title' => 'Produto não encontrado.',
                                'text' => 'Produto não encontrado.',
                                'type' => 'error',
                            ],
                        ];
                    }
                    try {
                        $ean = preg_replace('/\D/', '', $request->input('ean'));
                        $name = $product->name;
                        $dimensoes = app(BuscaDimensoesPorEan::class)->buscar($ean, $name);
                        $width = data_get($dimensoes, 'width');
                        $height = data_get($dimensoes, 'height');
                        $depth = data_get($dimensoes, 'depth');
                        if (empty($width) || empty($height) || empty($depth)) {
                            return [
                                'notification' => [
                                    'title' => 'Dimensões do produto não encontradas.',
                                    'text' => sprintf('Pesquisei o EAN %s, mas não encontrei nenhuma informação de produto correspondente nas bases públicas que eu acessei (incluindo catálogos de produtos brasileiros e referências de mercado).', $request->input('ean')),
                                    'type' => 'error',
                                ],
                            ];
                        }
                        $product->height = $height;
                        $product->width = $width;
                        $product->depth = $depth;
                        $product->save();

                        return [
                            'notification' => [
                                'title' => 'Dimensões do produto encontradas.',
                                'text' => sprintf('Altura: %s, Largura: %s, Profundidade: %s', $height, $width, $depth),
                                'type' => 'success',
                            ],
                        ];
                    } catch (InvalidArgumentException $e) {
                        return [
                            'notification' => [
                                'title' => 'Erro ao buscar dimensões.',
                                'text' => $e->getMessage(),
                                'type' => 'error',
                            ],
                        ];
                    } catch (\Throwable $e) {
                        return [
                            'notification' => [
                                'title' => 'Erro ao buscar dimensões.',
                                'text' => 'Erro ao buscar dimensões. Tente novamente.',
                                'type' => 'error',
                            ],
                        ];
                    }
                })
                ->url(route('tenant.dimensions.execute')),
        ]);

        $table->bulkActions([
            // Bulk actions
        ]);

        $table->headerActions([
            \Callcocam\LaravelRaptor\Support\Actions\Types\ExportAction::make('dimensions.export')
            ->exportColumns(['name', 'ean', 'height', 'width', 'depth', 'weight', 'unit', 'dimension_status'])
                ->model(Product::class),
        ]);

        return $table;
    }

    protected function resourcePath(): ?string
    {
        return 'tenant';
    }
}

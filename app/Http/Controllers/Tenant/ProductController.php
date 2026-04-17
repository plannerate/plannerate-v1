<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Http\Controllers\Tenant;

use App\Concerns\BelongsToConnection;
use App\Http\Controllers\Concerns\HasClientVisibilityRule;
use App\Jobs\Export\ExportProductJob;
use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Scope\ProductUlid;
use Callcocam\LaravelRaptor\Http\Controllers\AbstractController;
use Callcocam\LaravelRaptor\Support\Form\Form;
use Callcocam\LaravelRaptor\Support\Info\InfoList;
use Callcocam\LaravelRaptor\Support\Pages\Index;
use Callcocam\LaravelRaptor\Support\Table\TableBuilder;
use Callcocam\LaravelRaptor\Traits\HandlesFileImports;
use Closure;
use Illuminate\Http\Request;

class ProductController extends AbstractController
{
    use BelongsToConnection, HandlesFileImports, HasClientVisibilityRule;

    /**
     * Configura a largura máxima do container
     * Opções: full, 7xl, 6xl, 5xl, 4xl, 3xl, 2xl, xl, lg, md, sm, xs
     * Padrão: 7xl
     */
    protected Closure|string|null $maxWidth = 'full'; // Exemplo: usando largura completa

    protected array $importSettings = [
        'disk' => 'public',
        'directory' => 'imports/products',
        'mapping' => [
            'name' => 'Nome do Produto',
            'ean' => 'EAN',
            'codigo_erp' => 'Código ERP',
            'hierarchy_path' => 'Categoria ID',
            'width' => 'Largura',
            'height' => 'Altura',
            'depth' => 'Profundidade',
            'weight' => 'Peso',
            'unit' => 'Unidade',
            'url' => 'URL da Imagem',
            'status' => 'Status',
            // Adicione outros mapeamentos conforme necessário
        ],
    ];

    // public function edit(Request $request, string $record)
    // {
    //     $model = $this->model()::findOrFail($record);
    //     dd(data_get($model, 'mercadologico_cascading'));
    // }
    // protected function beforeUpdate(Request $request, string $id)
    // {
    //     dd($request->all());
    // }

    /**
     * Retorna o nome curto do recurso
     */
    protected function getResourceLabel(): ?string
    {
        return 'Produto';
    }

    /**
     * Retorna o label do nome plural do recurso
     */
    protected function getResourcePluralLabel(): ?string
    {
        return 'Produtos';
    }

    public function getPages(): array
    {
        return [
            'index' => Index::route('/products')
                ->label('Produtos')
                ->name('products.index')
                ->icon('Package')
                ->group('Catálogo')
                ->groupCollapsible(true)
                ->order(14)
                ->visible(fn () => $this->hasCurrentClientContext())
                ->resource(Product::class)
                ->middlewares(['auth', 'verified']),
        ];
    }

    protected function infolist(InfoList $infoList): InfoList
    {
        $infoList->columns([
            \Callcocam\LaravelRaptor\Support\Info\Columns\Types\CardColumn::make('product_info')
                ->title('Informações do Produto')
                ->helperText('Dados básicos do produto')
                ->collapsible(true, true)
                ->columns([
                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('name')
                        ->label('Nome do Produto')
                        ->icon('Package'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('sku')
                        ->label('SKU')
                        ->icon('Hash'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('category.name')
                        ->label('Categoria')
                        ->icon('FolderTree'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('brand.name')
                        ->label('Marca')
                        ->icon('Tag'),
                ]),

            \Callcocam\LaravelRaptor\Support\Info\Columns\Types\CardColumn::make('pricing_info')
                ->title('Informações de Preço')
                ->collapsible(true, true)
                ->columns([
                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('price')
                        ->label('Preço')
                        ->prefix('R$')
                        ->icon('DollarSign'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\StatusColumn::make('status')
                        ->label('Status'),
                ]),

            \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('description')
                ->label('Descrição')
                ->icon('FileText'),

            \Callcocam\LaravelRaptor\Support\Info\Columns\Types\CardColumn::make('dimensions_info')
                ->title('Dimensões')
                ->helperText('Dimensões físicas do produto')
                ->collapsible(true, true)
                ->columns([
                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('formatted_width')
                        ->label('Largura')
                        ->suffix(' cm')
                        ->icon('MoveHorizontal'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('formatted_height')
                        ->label('Altura')
                        ->suffix(' cm')
                        ->icon('MoveVertical'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('formatted_depth')
                        ->label('Profundidade')
                        ->suffix(' cm')
                        ->icon('Move'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('formatted_weight')
                        ->label('Peso')
                        ->suffix(' g')
                        ->icon('Weight'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('unit')
                        ->label('Unidade')
                        ->badge(),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('dimensions_label')
                        ->label('Dimensão')
                        ->badge(),
                ]),

            \Callcocam\LaravelRaptor\Support\Info\Columns\Types\CardColumn::make('additional_data_info')
                ->title('Dados Adicionais')
                ->helperText('Informações adicionais sobre o produto')
                ->collapsible(true, true)
                ->columns([
                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('type')
                        ->label('Tipo')
                        ->icon('Tag'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('reference')
                        ->label('Referência')
                        ->icon('Hash'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('codigo_erp')
                        ->label('Código ERP')
                        ->icon('Barcode'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('brand')
                        ->label('Marca')
                        ->icon('Tag'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('subbrand')
                        ->label('Submarca')
                        ->icon('Tag'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('color')
                        ->label('Cor')
                        ->icon('Palette'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('fragrance')
                        ->label('Fragrância')
                        ->icon('Flower'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('flavor')
                        ->label('Sabor')
                        ->icon('Cookie'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('packaging_type')
                        ->label('Tipo de Embalagem')
                        ->icon('Package'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('packaging_size')
                        ->label('Tamanho da Embalagem')
                        ->icon('Ruler'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('packaging_content')
                        ->label('Conteúdo da Embalagem')
                        ->icon('PackageOpen'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('measurement_unit')
                        ->label('Unidade de Medida')
                        ->icon('Ruler'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('unit_measure')
                        ->label('Unidade de Medida Alternativa')
                        ->icon('Ruler'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('sortiment_attribute')
                        ->label('Atributo de Sortimento')
                        ->icon('Tags'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('auxiliary_description')
                        ->label('Descrição Auxiliar')
                        ->icon('FileText'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('additional_information')
                        ->label('Informações Adicionais')
                        ->icon('Info'),
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
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('name')
                ->label('Nome do Produto')
                ->required()
                ->rules(['required', 'string', 'max:255'])
                ->placeholder('Digite o nome do produto')
                ->columnSpan('7'),  // 6/12 = 50%

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('ean')
                ->label('EAN')
                ->required()
                ->rules(function ($record) {
                    return ['required', 'string', 'max:255', 'unique:products,ean'.($record ? ",{$record->id}" : '')];
                })
                ->placeholder('Código único do produto')
                ->columnSpan('5'),  // 6/12 = 50%

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\UploadField::make('image_url')
                ->realName('url')
                ->label('Imagem do Produto')
                ->image() // Validação de imagem + preview local
                ->directory('products/images')
                ->disk('public')
                ->deleteOldFiles(true)
                ->placeholder('Selecione a imagem do produto')
                ->columnSpan('12'),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\CascadingField::make('mercadologico_cascading')
                ->label('Categoria')
                ->required()
                ->fieldsUsing('category_id')
                ->queryUsingCascading(Category::query())
                ->fields([
                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('segmento_varejista')
                        ->label('Segmento Varejista')
                        ->options(Category::query()->whereNull('category_id')->pluck('name', 'id')->toArray())
                        ->placeholder('Selecione a categoria pai')
                        ->columnSpan('4'),
                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('departamento')
                        ->label('Departamento')
                        ->dependsOn('segmento_varejista')
                        ->placeholder('Selecione o departamento')
                        ->columnSpan('4'),
                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('subdepartamento')
                        ->label('Subdepartamento')
                        ->dependsOn('departamento')
                        ->placeholder('Selecione o subdepartamento')
                        ->columnSpan('4'),
                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('categoria')
                        ->label('Categoria')
                        ->dependsOn('subdepartamento')
                        ->placeholder('Selecione a categoria')
                        ->columnSpan('6'),
                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('subcategoria')
                        ->label('Subcategoria')
                        ->dependsOn('categoria')
                        ->placeholder('Selecione a subcategoria')
                        ->columnSpan('6'),
                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('segmento')
                        ->label('Segmento')
                        ->dependsOn('subcategoria')
                        ->placeholder('Selecione o segmento')
                        ->columnSpan('6'),
                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('subsegmento')
                        ->label('Subsegmento')
                        ->dependsOn('segmento')
                        ->placeholder('Selecione o subsegmento')
                        ->columnSpan('6'),
                ])
                ->placeholder('Selecione uma categoria')
                ->columnSpan('12'),  // 6/12 = 50%

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('status')
                ->label('Status')
                ->required()
                ->options([
                    'draft' => 'Rascunho',
                    'published' => 'Publicado',
                ])
                ->default('draft')
                ->columnSpan('12'),  // 8/12 = 66.66%

            // Seção: Dimensões
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SectionField::make('dimensions_section', 'Dimensões')
                ->helperText('Dimensões físicas do produto')

                ->fields([
                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\NumberField::make('width')
                        ->label('Largura (cm)')
                        ->placeholder('0.00')
                        ->helperText('Largura do produto em centímetros')
                        ->step(0.01)
                        ->columnSpan('2')->rules(['required', 'numeric', 'min:0']),

                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\NumberField::make('height')
                        ->label('Altura (cm)')
                        ->placeholder('0.00')
                        ->helperText('Altura do produto em centímetros')
                        ->step(0.01)
                        ->columnSpan('2')->required()->rules(['required', 'numeric', 'min:0']),

                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\NumberField::make('depth')
                        ->label('Profundidade (cm)')
                        ->placeholder('0.00')
                        ->helperText('Profundidade do produto em centímetros')
                        ->step(0.01)
                        ->columnSpan('2')->required()->rules(['required', 'numeric', 'min:0']),

                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\NumberField::make('weight')
                        ->label('Peso (g)')
                        ->placeholder('0.00')
                        ->helperText('Peso do produto em gramas')
                        ->step(0.01)
                        ->columnSpan('2')->required()->rules(['required', 'numeric', 'min:0']),

                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('unit')
                        ->label('Unidade')
                        ->options([
                            'cm' => 'Centímetros (cm)',
                            'm' => 'Metros (m)',
                            'g' => 'Gramas (g)',
                            'kg' => 'Quilogramas (kg)',
                        ])->default('cm')
                        ->columnSpan('4')->required(),

                    // has_dimensions é calculado (width, height, depth > 0); não editável manualmente
                ])
                ->columnSpanFull(),

            // Seção: Dados Adicionais
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SectionField::make('additional_data_section', 'Dados Adicionais')
                ->helperText('Informações adicionais sobre o produto')
                ->fields([
                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('type')
                        ->label('Tipo')
                        ->placeholder('Tipo do produto')
                        ->columnSpan('3'),

                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('reference')
                        ->label('Referência')
                        ->placeholder('Referência do produto')
                        ->columnSpan('3'),

                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('codigo_erp')
                        ->label('Código ERP')
                        ->placeholder('Código no sistema ERP')
                        ->columnSpan('3'),

                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('brand')
                        ->label('Marca')
                        ->placeholder('Marca do produto')
                        ->columnSpan('3'),

                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('subbrand')
                        ->label('Submarca')
                        ->placeholder('Submarca do produto')
                        ->columnSpan('3'),

                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('color')
                        ->label('Cor')
                        ->placeholder('Cor do produto')
                        ->columnSpan('3'),

                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('fragrance')
                        ->label('Fragrância')
                        ->placeholder('Fragrância do produto')
                        ->columnSpan('3'),

                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('flavor')
                        ->label('Sabor')
                        ->placeholder('Sabor do produto')
                        ->columnSpan('3'),

                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('packaging_type')
                        ->label('Tipo de Embalagem')
                        ->placeholder('Tipo de embalagem')
                        ->columnSpan('3'),

                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('packaging_size')
                        ->label('Tamanho da Embalagem')
                        ->placeholder('Tamanho da embalagem')
                        ->columnSpan('3'),

                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('packaging_content')
                        ->label('Conteúdo da Embalagem')
                        ->placeholder('Conteúdo da embalagem')
                        ->columnSpan('3'),

                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('measurement_unit')
                        ->label('Unidade de Medida')
                        ->placeholder('Unidade de medida')
                        ->columnSpan('3'),

                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('unit_measure')
                        ->label('Unidade de Medida Alternativa')
                        ->placeholder('Unidade de medida alternativa')
                        ->columnSpan('3'),

                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('sortiment_attribute')
                        ->label('Atributo de Sortimento')
                        ->placeholder('Atributo de sortimento')
                        ->columnSpan('4'),

                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextareaField::make('auxiliary_description')
                        ->label('Descrição Auxiliar')
                        ->placeholder('Descrição auxiliar do produto')
                        ->rows(3)
                        ->columnSpan('6'),

                    \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextareaField::make('additional_information')
                        ->label('Informações Adicionais')
                        ->placeholder('Informações adicionais sobre o produto')
                        ->rows(3)
                        ->columnSpan('6'),
                ])
                ->columnSpanFull(),
        ]);

        return $form;
    }

    protected function table(TableBuilder $table): TableBuilder
    {

        $table
            ->columns([
                \Callcocam\LaravelRaptor\Support\Table\Columns\Types\ImageColumn::make('image_url')
                    ->label('URL da Imagem')
                    ->size(50)
                    ->rounded()
                    ->clickable()->columnSpanTwo()->rowSpan('4'),
                \Callcocam\LaravelRaptor\Support\Table\Columns\Types\StatusColumn::make('status')
                    ->label('Status')
                    ->executeUrl(route('tenant.products.execute'))->columnSpanTwo(),
                \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('name')
                    ->label('Nome')
                    ->primary()
                    ->searchable()
                    ->columns([
                        \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('ean')
                            ->label('SKU')
                            ->searchable()
                            ->sortable()->columnSpanTwo(),
                        \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('codigo_erp')
                            ->label('Código ERP')
                            ->sortable()
                            ->searchable()->columnSpanTwo(),
                    ])
                    ->sortable()->columnSpanThree(),

                \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('hierarchy_path')
                    ->label('Hierarquia')->columns([
                        \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('current_stock')
                            ->label('Estoque Atual')
                            ->sortable()
                            ->searchable()->columnSpanTwo(),
                        \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('height')
                            ->label('Altura')
                            ->sortable()->columnSpanTwo(),
                        \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('width')
                            ->label('Largura')
                            ->sortable()->columnSpanTwo(),
                        \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('depth')
                            ->label('Profundidade')
                            ->sortable()->columnSpanTwo(),
                        \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('weight')
                            ->label('Peso')
                            ->sortable()->columnSpanTwo(),
                    ])
                    ->sortable(false)->columnSpanFull(), // Remove searchable pois é um accessor, não coluna DB

            ]);

        $table->filters([
            \Callcocam\LaravelRaptor\Support\Table\Filters\SelectCascadingFilter::make('category_id')
                ->label('Categoria')
                ->queryUsingCascading(Category::query())
                ->fieldsUsing('category_id')
                ->includeParentsOption(true)
                ->levelFieldNames([
                    'segmento_varejista', 'departamento', 'subdepartamento',
                    'categoria', 'subcategoria', 'segmento', 'subsegmento',
                ])
                ->fields([
                    \Callcocam\LaravelRaptor\Support\Table\Filters\SelectFilter::make('segmento_varejista')
                        ->label('Segmento Varejista')
                        ->options(Category::query()->whereNull('category_id')->pluck('name', 'id')->toArray()),
                    \Callcocam\LaravelRaptor\Support\Table\Filters\SelectFilter::make('departamento')
                        ->label('Departamento')
                        ->dependsOn('segmento_varejista'),
                    \Callcocam\LaravelRaptor\Support\Table\Filters\SelectFilter::make('subdepartamento')
                        ->label('Subdepartamento')
                        ->dependsOn('departamento'),
                    \Callcocam\LaravelRaptor\Support\Table\Filters\SelectFilter::make('categoria')
                        ->label('Categoria')
                        ->dependsOn('subdepartamento'),
                    \Callcocam\LaravelRaptor\Support\Table\Filters\SelectFilter::make('subcategoria')
                        ->label('Subcategoria')
                        ->dependsOn('categoria'),
                    \Callcocam\LaravelRaptor\Support\Table\Filters\SelectFilter::make('segmento')
                        ->label('Segmento')
                        ->dependsOn('subcategoria'),
                    \Callcocam\LaravelRaptor\Support\Table\Filters\SelectFilter::make('subsegmento')
                        ->label('Subsegmento')
                        ->dependsOn('segmento'),
                ]),

            \Callcocam\LaravelRaptor\Support\Table\Filters\NullableFilter::make('url')
                ->label('Imagem')
                ->labels('Possui Imagem', 'Sem Imagem')
                ->placeholder('Possui imagem do produto'),
            \Callcocam\LaravelRaptor\Support\Table\Filters\TextFilter::make('id')
                ->label('Código do Produto')
                ->placeholder('Digite o código do produto')
                ->queryUsing(function ($query, $value) {
                    $query->where('id', 'like', '%'.$value.'%');
                }),
            \Callcocam\LaravelRaptor\Support\Table\Filters\TextFilter::make('codigo_erp')
                ->label('Código ERP')
                ->placeholder('Digite o código ERP do produto')
                ->queryUsing(function ($query, $value) {
                    $query->where('codigo_erp', 'like', '%'.$value.'%');
                }),
            \Callcocam\LaravelRaptor\Support\Table\Filters\SelectFilter::make('current_stock')
                ->label('Estoque Atual')
                ->options([
                    'in_stock' => 'Em Estoque',
                    'out_of_stock' => 'Sem Estoque',
                ])->queryUsing(function ($query, $value) {
                    if ($value === 'in_stock') {
                        $query->where('current_stock', '>', 0)->orWhereNotNull('current_stock');
                    }
                    if ($value === 'out_of_stock') {
                        $query->where(function ($q) {
                            $q->where('current_stock', '<=', 0)->orWhereNull('current_stock');
                        });
                    }
                }),
            \Callcocam\LaravelRaptor\Support\Table\Filters\SelectFilter::make('status')
                ->label('Status')
                ->options([
                    'draft' => 'Rascunho',
                    'published' => 'Publicado',
                ]),
            \Callcocam\LaravelRaptor\Support\Table\Filters\SelectFilter::make('has_dimensions')
                ->label('Dimensão')
                ->options([
                    [
                        'label' => 'Com dimensão',
                        'value' => '1',
                    ],
                    [
                        'label' => 'Sem dimensão',
                        'value' => '0',
                    ],
                ])
                ->placeholder('Todos')
                ->queryUsing(function ($query, $value) {
                    if ($value === '' || $value === null) {
                        return;
                    }
                    // Aceita valor ('1'/'0'), boolean ou rótulo enviado pelo front
                    $hasDimensions = match (true) {
                        $value === '1' || $value === true || $value === 'Com dimensão' => true,
                        $value === '0' || $value === false || $value === 'Sem dimensão' => false,
                        default => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                    };
                    $query->where('has_dimensions', $hasDimensions);
                }),

            \Callcocam\LaravelRaptor\Support\Table\Filters\TrashedFilter::make(),
        ]);

        $table->actions([
            \Callcocam\LaravelRaptor\Support\Actions\Types\EditAction::make('products.edit'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\RestoreAction::make('products.restore'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\ForceDeleteAction::make('products.forceDelete'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\DeleteAction::make('products.destroy'),
        ]);
        $table->bulkActions([
            // Bulk actions serão implementadas em breve
        ]);

        $table->headerActions([
            \Callcocam\LaravelRaptor\Support\Actions\Types\CreateAction::make('products.create'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\ExportAction::make('products.export')
                ->useJob(true)
                ->job(ExportProductJob::class)
                ->callback(function (Request $request) {
                    $user = $request->user();
                    $clientId = config('app.current_client_id');
                    $client = $clientId ? Client::find($clientId) : null;
                    $filters = array_merge(
                        [
                            'tenant_id' => config('app.current_tenant_id'),
                            'client_id' => $clientId,
                        ],
                        $request->all()
                    );
                    $fileName = 'produtos-'.now()->format('Y-m-d-H-i-s').'.xlsx';
                    $filePath = 'exports/'.$fileName;
                    ExportProductJob::dispatch($filters, $fileName, $filePath, 'Produtos', $user->id, $client?->id, $client?->database, $client?->tenant_id);

                    return [
                        'notification' => [
                            'title' => 'Exportação iniciada',
                            'text' => 'Sua exportação está sendo processada. Você receberá uma notificação quando estiver pronta para download.',
                            'type' => 'info',
                        ],
                    ];
                }),

            \Callcocam\LaravelRaptor\Support\Actions\Types\ImportAction::make('products.import')
                ->useJob() // Recomendado para arquivos grandes (milhares de linhas)
                ->column(\Callcocam\LaravelRaptor\Support\Form\Columns\Types\CheckboxField::make('clean_data', 'Limpar dados existentes')->default(false))
                ->sheets([
                    \Callcocam\LaravelRaptor\Support\Import\Columns\Sheet::make('Tabela de produtos')
                        ->chunkSize(1000) // Reduz memória em planilhas grandes
                        ->updateBy(['tenant_id', 'ean']) // Atualiza existente por tenant + EAN; senão insere
                        ->generateIdUsing(ProductUlid::class)
                        ->modelClass(Product::class)
                        ->columns([
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('name')
                                ->label('descricao')  // Nome da coluna no Excel
                                ->required()
                                ->rules(['required', 'string', 'max:255']),

                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('ean')
                                ->label('ean')  // Nome da coluna no Excel
                                ->required()
                                ->unique()
                                ->rules(['required', 'string', 'max:13']),

                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('codigo_erp')
                                ->label('codigo_erp')  // Nome da coluna no Excel
                                ->required()
                                ->unique()
                                ->rules(['required', 'string']),

                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('description')
                                ->label('descricao'),  // Mesma coluna que name, pode usar também
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportNumber::make('height')
                                ->label('Altura')
                                ->sheet('Tabela dimensões')
                                ->float(),

                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportNumber::make('width')
                                ->label('Largura')
                                ->sheet('Tabela dimensões')
                                ->float(),

                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportNumber::make('depth')
                                ->label('Profundidade')
                                ->sheet('Tabela dimensões')
                                ->float(),

                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportNumber::make('reference')
                                ->label('Referência')
                                ->sheet('Tabela dimensões'),
                            // fragrance
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('fragrance')
                                ->label('Fragrância')
                                ->sheet('Tabela dados adicionais'),
                            // flavor
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('flavor')
                                ->label('Sabor')
                                ->sheet('Tabela dados adicionais'),
                            // color
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('color')
                                ->label('Cor')
                                ->sheet('Tabela dados adicionais'),
                            // brand
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('brand')
                                ->label('Marca')
                                ->sheet('Tabela dados adicionais'),
                            // subbrand
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('subbrand')
                                ->label('Submarca')
                                ->sheet('Tabela dados adicionais'),
                            // packaging_type
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('packaging_type')
                                ->label('Tipo de embalagem')
                                ->sheet('Tabela dados adicionais'),
                            // packaging_size
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('packaging_size')
                                ->label('Tamanho ou quantidade da embalagem')
                                ->sheet('Tabela dados adicionais'),
                            // measurement_unit
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('measurement_unit')
                                ->label('Unidade de medida')
                                ->sheet('Tabela dados adicionais'),
                            // unit_measure
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('unit_measure')
                                ->label('Unidade de medida')
                                ->sheet('Tabela dados adicionais'),
                            // auxiliary_description
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('auxiliary_description')
                                ->label('Descrição auxiliar')
                                ->sheet('Tabela dados adicionais'),
                            // additional_information
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('additional_information')
                                ->label('Informação adicional')
                                ->sheet('Tabela dados adicionais'),

                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('user_id')
                                ->hidden()
                                ->defaultValue(fn () => auth()->id()),

                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('tenant_id')
                                ->hidden()
                                ->defaultValue(fn () => config('app.current_tenant_id')),

                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('client_id')
                                ->hidden()
                                ->defaultValue(fn () => config('app.current_client_id')),

                        ])
                        ->addSheet('Tabela dimensões', 'ean')
                        ->addSheet('Tabela dados adicionais', 'ean'),
                ]),
        ]);

        return $table;
    }

    /**
     * Prepara os dados do produto para inserção no banco,
     * removendo campos problemáticos e convertendo arrays em JSON
     */
    private function prepareProductDataForInsert(array $productData, string $clientId): array
    {
        // Remove campos que são appends (calculados) e não devem ir para o banco
        $fieldsToRemove = [
            'mercadologico_cascading',
            'image_url',
            'hierarchy_path',
            'formatted_height',
            'formatted_width',
            'formatted_depth',
            'formatted_weight',
            'category', // Campo de relacionamento
        ];

        // Remove campos problemáticos
        foreach ($fieldsToRemove as $field) {
            unset($productData[$field]);
        }

        // Converte arrays para JSON se necessário
        foreach ($productData as $key => $value) {
            if (is_array($value)) {
                $productData[$key] = json_encode($value);
            }
        }

        // Gera novo ID e atualiza dados obrigatórios
        $productData['id'] = \Illuminate\Support\Str::ulid();
        $productData['client_id'] = $clientId;
        $productData['created_at'] = now();
        $productData['updated_at'] = now();
        $productData['deleted_at'] = null; // Remove soft delete se existir

        return $productData;
    }

    protected function queryBuilder(): \Illuminate\Database\Eloquent\Builder
    {
        return Product::query()->with('category');
    }

    protected function resourcePath(): ?string
    {
        return 'tenant';
    }
}

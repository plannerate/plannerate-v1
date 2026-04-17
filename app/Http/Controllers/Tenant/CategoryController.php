<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\HasClientVisibilityRule;
use App\Jobs\Export\ExportCategoryJob;
use App\Models\Category;
use App\Models\Client;
use App\Scope\CategoryUlid;
use App\Services\Import\BeforePersistCategory;
use App\Services\Import\CategoriasAfterProcess;
use App\Services\Import\Person\CategoryImportService;
use Callcocam\LaravelRaptor\Http\Controllers\AbstractController;
use Callcocam\LaravelRaptor\Services\HierarchicalImportService;
use Callcocam\LaravelRaptor\Support\Form\Form;
use Callcocam\LaravelRaptor\Support\Info\InfoList;
use Callcocam\LaravelRaptor\Support\Pages\Edit;
use Callcocam\LaravelRaptor\Support\Pages\Index;
use Callcocam\LaravelRaptor\Support\Table\TableBuilder;
use Callcocam\LaravelRaptor\Traits\HandlesFileImports;
use Closure;
use Illuminate\Http\Request;

class CategoryController extends AbstractController
{
    use HandlesFileImports;
    use HasClientVisibilityRule;

    protected Closure|string|null $maxWidth = '7xl';

    // public function edit(Request $request, string $record)
    // {
    //     $model = $this->model()::findOrFail($record);
    //     dd($model->toArray());
    // }

    public function getPages(): array
    {
        return [
            'index' => Index::route('/categories')
                ->label('Categorias')
                ->name('categories.index')
                ->icon('FolderTree')
                ->group('Catálogo')
                ->groupCollapsible(true)
                ->visible(fn () => $this->hasCurrentClientContext())
                ->order(15)
                ->middlewares(['auth', 'verified'])
                ->resource(Category::class),
        ];
    }

    protected function infolist(InfoList $infoList): InfoList
    {
        $infoList->columns([
            \Callcocam\LaravelRaptor\Support\Info\Columns\Types\CardColumn::make('category_info')
                ->title('Informações da Categoria')
                ->description('Dados básicos da categoria')
                ->collapsible(true, true)
                ->columns([
                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('name')
                        ->label('Nome')
                        ->icon('FolderTree'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('slug')
                        ->label('Slug')
                        ->icon('Hash'),
                ]),

            \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('description')
                ->label('Descrição')
                ->icon('FileText'),

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
        $currentCategory = $form->getModel();
        $form->columns([
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('name')
                ->label('Nome da Categoria')
                ->required()
                ->rules(['required', 'string', 'max:255'])
                ->placeholder('Digite o nome da categoria')
                ->columnSpanFull(),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\CascadingField::make('mercadologico_cascading')
                ->label('Categoria')
                ->fieldsUsing('category_id')
                ->required()
                ->queryUsingCascading(Category::query()->when($currentCategory, function ($query) use ($currentCategory) {
                    return $query->where('id', '!=', $currentCategory->id);
                }))
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

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('nivel')
                ->label('Nível')
                ->disabled()
                ->placeholder('Nível hierárquico da categoria'),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('level_name')
                ->label('Nível Nome')
                ->disabled()
                ->placeholder('Nome do nível hierárquico da categoria'),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextareaField::make('description')
                ->label('Descrição')
                ->placeholder('Descrição da categoria')
                ->rows(4)
                ->columnSpanFull(),
        ]);

        return $form;
    }

    protected function table(TableBuilder $table): TableBuilder
    {
        $table->columns([
            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\StatusColumn::make('status')
                ->label('Status')
                ->editable()
                ->executeUrl(route('tenant.categories.execute'))->columnSpanOne(),
            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('name')
                ->label('Nome')
                ->searchable()
                ->sortable()->columnSpanThree(),

            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('hierarchy_path')
                ->label('Categoria Pai')
                ->sortable(false)->columnSpanEight(), // Remove searchable pois é um accessor, não coluna DB
            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('nivel')
                ->label('Nível')->columnSpanOne(), // Remove searchable pois é um accessor, não coluna DB
            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('level_name')
                ->label('Nível Nome')
                ->sortable(false)->columnSpanThree(), // Remove searchable pois é um accessor, não coluna DB
            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\DateColumn::make('created_at')
                ->label('Criado em')
                ->format('d/m/Y H:i')
                ->searchable()
                ->sortable()->columnSpanThree(),
        ]);

        $table->filters([
            \Callcocam\LaravelRaptor\Support\Table\Filters\TrashedFilter::make(),
        ]);

        $table->actions([
            // \Callcocam\LaravelRaptor\Support\Actions\Types\ViewAction::make('categories.show'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\EditAction::make('categories.edit'),

            \Callcocam\LaravelRaptor\Support\Actions\Types\RestoreAction::make('categories.restore'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\ForceDeleteAction::make('categories.forceDelete'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\DeleteAction::make('categories.destroy'),
        ]);

        $table->bulkActions([
            // Bulk actions
        ]);

        $table->headerActions([
            \Callcocam\LaravelRaptor\Support\Actions\Types\CreateAction::make('categories.create'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\ExportAction::make('categories.export')
                ->callback(function (Request $request) {
                    $user = $request->user();
                    $clientId = config('app.current_client_id');
                    $client = $clientId ? Client::find($clientId) : null;
                    $filters = array_merge(
                        ['tenant_id' => config('app.current_tenant_id')],
                        $request->all()
                    );
                    $fileName = 'categorias-'.now()->format('Y-m-d-H-i-s').'.xlsx';
                    $filePath = 'exports/'.$fileName;
                    ExportCategoryJob::dispatch($filters, $fileName, $filePath, 'Categorias', $user->id, $client?->id, $client?->database, $client?->tenant_id);

                    return [
                        'notification' => [
                            'title' => 'Exportação iniciada',
                            'text' => 'Sua exportação está sendo processada. Você receberá uma notificação quando estiver pronta para download.',
                            'type' => 'info',
                        ],
                    ];
                }),

            \Callcocam\LaravelRaptor\Support\Actions\Types\ImportAction::make('categories.import')
                ->useJob() // Recomendado para arquivos grandes (milhares de linhas)
                ->column(\Callcocam\LaravelRaptor\Support\Form\Columns\Types\CheckboxField::make('clean_data', 'Limpar dados existentes')->default(false))
                ->sheets([
                    \Callcocam\LaravelRaptor\Support\Import\Columns\Sheet::make('Tabela mercadológico')
                        ->table('categories', config('database.default'))
                        ->modelClass(Category::class)
                        ->hierarchicalColumns(['segmento_varejista', 'departamento', 'subdepartamento', 'categoria', 'subcategoria', 'segmento', 'subsegmento'])
                        ->parentColumnName('category_id')
                        // ->updateBy(['id'])
                        ->hierarchicalValueColumn('name')
                        // ->generateIdUsing(CategoryUlid::class)
                        // ->serviceClass(CategoryImportService::class)
                        ->serviceClass(HierarchicalImportService::class)
                        ->beforePersistClass(BeforePersistCategory::class)
                        ->afterProcessClass(CategoriasAfterProcess::class)
                        ->connection(config('database.default'))
                        ->columns([
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('ean')
                                ->label('ean')
                                ->exclude(),
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('segmento_varejista')
                                ->label('Segmento varejista'),
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('departamento')
                                ->label('Departamento'),
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('subdepartamento')
                                ->label('Subdepartamento'),
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('categoria')
                                ->label('Categoria')
                                ->required()
                                ->rules(['required', 'string', 'max:255']),
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('subcategoria')
                                ->label('Subcategoria'),
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('tenant_id')
                                ->hidden()
                                ->defaultValue(fn () => config('app.current_tenant_id')),
                        ]),
                ]),
        ]);

        return $table;
    }

    protected function resourcePath(): ?string
    {
        return 'tenant';
    }
}

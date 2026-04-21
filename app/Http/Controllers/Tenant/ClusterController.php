<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\HasClientVisibilityRule;
use App\Models\Cluster;
use Callcocam\LaravelRaptor\Http\Controllers\AbstractController;
use Callcocam\LaravelRaptor\Support\Form\Form;
use Callcocam\LaravelRaptor\Support\Info\InfoList;
use Callcocam\LaravelRaptor\Support\Pages\Index;
use Callcocam\LaravelRaptor\Support\Table\TableBuilder;

class ClusterController extends AbstractController
{
    use HasClientVisibilityRule;

    public function getPages(): array
    {

        return [
            'index' => Index::route('/clusters')
                ->label('Clusters')
                ->name('clusters.index')
                ->icon('ChartScatter')
                ->group('Operacional')
                ->groupCollapsible(true)
                ->order(20)->visible(fn () => $this->hasCurrentClientContext())
                ->middlewares(['auth', 'verified'])
                ->resource(Cluster::class),
        ];
    }

    protected function infolist(InfoList $infoList): InfoList
    {
        $infoList->columns([
            \Callcocam\LaravelRaptor\Support\Info\Columns\Types\CardColumn::make('info')
                ->title('Informações')
                ->description('Dados básicos')
                ->collapsible(true, true)
                ->columns([
                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('name')
                        ->label('Nome')
                        ->icon('FolderTree'),

                    \Callcocam\LaravelRaptor\Support\Info\Columns\Types\TextColumn::make('slug')
                        ->label('Slug')
                        ->icon('Hash'),
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
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\HiddenField::make('client_id')->default(fn () => config('app.current_client_id') ?? auth()->user()?->currentClient?->id),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('name')
                ->label('Nome')
                ->required()
                ->rules(['required', 'string', 'max:255'])
                ->placeholder('Digite o nome')
                ->columnSpanFull(),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('epcification_1')
                ->label('Incluir campo de especificação 1')
                ->placeholder('Digite o nome da especificação 1')
                ->columnSpanFour(),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('specification_2')
                ->label('Incluir campo de especificação 2')
                ->placeholder('Digite o nome da especificação 2')
                ->columnSpanFour(),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('specification_3')
                ->label('Incluir campo de especificação 3')
                ->placeholder('Digite o nome da especificação 3')
                ->columnSpanFour(),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextareaField::make('description')
                ->label('Descrição')
                ->placeholder('Descrição')
                ->rows(4)
                ->columnSpanFull(),
        ]);

        return $form;
    }

    protected function table(TableBuilder $table): TableBuilder
    {
        $table ->columns([
                \Callcocam\LaravelRaptor\Support\Table\Columns\Types\StatusColumn::make('status')
                    ->label('Status')
                    ->editable()
                    ->executeUrl(route('tenant.clusters.execute'))
                    ->primary()
                    ->columnSpanOne(),
                \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()->columnSpanSix()
                    ->columns([
                        \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('slug')
                            ->label('Slug')
                            ->searchable()
                            ->sortable()->columnSpanTwo(),
                    ]),
                \Callcocam\LaravelRaptor\Support\Table\Columns\Types\DateColumn::make('created_at')
                    ->label('Data de Criação')->columnSpanTwo(),
            ]);

        $table->filters([
            \Callcocam\LaravelRaptor\Support\Table\Filters\TrashedFilter::make(),
        ]);

        $table->actions([
            // \Callcocam\LaravelRaptor\Support\Actions\Types\ViewAction::make('clusters.show'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\EditAction::make('clusters.edit'),

            \Callcocam\LaravelRaptor\Support\Actions\Types\RestoreAction::make('clusters.restore'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\ForceDeleteAction::make('clusters.forceDelete'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\DeleteAction::make('clusters.destroy'),
        ]);

        $table->bulkActions([
            // Bulk actions
        ]);

        $table->headerActions([
            \Callcocam\LaravelRaptor\Support\Actions\Types\CreateAction::make('clusters.create'),
        ]);

        return $table;
    }

    protected function resourcePath(): ?string
    {
        return 'tenant';
    }
}

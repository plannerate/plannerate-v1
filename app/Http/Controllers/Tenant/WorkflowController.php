<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\HasClientVisibilityRule;
use Callcocam\LaravelRaptor\Http\Controllers\AbstractController;
use Callcocam\LaravelRaptor\Support\Form\Form;
use Callcocam\LaravelRaptor\Support\Info\InfoList;
use Callcocam\LaravelRaptor\Support\Pages\Index;
use Callcocam\LaravelRaptor\Support\Table\TableBuilder;
use Callcocam\LaravelRaptorFlow\Models\Flow;

class WorkflowController extends AbstractController
{
    use HasClientVisibilityRule;

    public function model(): ?string
    {
        return Flow::class;
    }

    public function getPages(): array
    {
        return [
            'index' => Index::route('/workflows')
                ->label('Fluxos')
                ->name('workflows.index')
                ->icon('FolderTree')
                ->group('Workflows')
                ->groupCollapsible(true)
                ->order(20)
                ->middlewares(['auth', 'verified'])
                ->visible(fn () => $this->hasNoCurrentClientContext())
                ->resource(Flow::class),
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
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('name')
                ->label('Nome')
                ->required()
                ->rules(['required', 'string', 'max:255'])
                ->placeholder('Digite o nome')
                ->columnSpanFull(),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('slug')
                ->label('Slug')
                ->placeholder('Digite o slug')
                ->rules(['required', 'string', 'max:255'])
                ->columnSpanFull(),

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
        $table->columns([
            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('name')
                ->label('Nome')
                ->searchable()
                ->sortable(),

            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('slug')
                ->label('Slug')
                ->searchable()
                ->sortable(),

            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('description')
                ->label('Descrição'),
        ]);

        $table->filters([
            \Callcocam\LaravelRaptor\Support\Table\Filters\TrashedFilter::make(),
        ]);

        $table->actions([
            \Callcocam\LaravelRaptor\Support\Actions\Types\ViewAction::make('workflows.show'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\EditAction::make('workflows.edit'),

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

            \Callcocam\LaravelRaptor\Support\Actions\Types\RestoreAction::make('workflows.restore'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\ForceDeleteAction::make('workflows.forceDelete'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\DeleteAction::make('workflows.destroy'),
        ]);

        $table->bulkActions([
            // Bulk actions
        ]);

        $table->headerActions([
            \Callcocam\LaravelRaptor\Support\Actions\Types\CreateAction::make('workflows.create'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\ExportAction::make('workflows.export'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\ImportAction::make('workflows.import'),
        ]);

        return $table;
    }

    protected function resourcePath(): ?string
    {
        return 'tenant';
    }
}

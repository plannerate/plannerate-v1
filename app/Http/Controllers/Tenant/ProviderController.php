<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\HasClientVisibilityRule;
use App\Models\Provider;
use Callcocam\LaravelRaptor\Http\Controllers\AbstractController;
use Callcocam\LaravelRaptor\Support\Form\Form;
use Callcocam\LaravelRaptor\Support\Info\InfoList;
use Callcocam\LaravelRaptor\Support\Pages\Index;
use Callcocam\LaravelRaptor\Support\Table\TableBuilder;

class ProviderController extends AbstractController
{
    use HasClientVisibilityRule;

    public function getPages(): array
    {
        return [
            'index' => Index::route('/providers')
                ->label('Fornecedores')
                ->name('providers.index')
                ->icon('Truck')
                ->group('Operacional')
                ->groupCollapsible(true)
                ->order(20)
                ->visible(fn() => $this->hasCurrentClientContext())
                ->middlewares(['auth', 'verified'])
                ->resource(Provider::class),
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
        // Razão Social
        // Nome fantasia
        // CNPJ
        // Email
        // Telefone
        // Cep
        // Rua
        // Numero
        // Complemento
        // Bairro
        // Cidade
        // Estado
        // Pais
        $form->columns([
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('name')
                ->label('Nome')
                ->required()
                ->rules(['required', 'string', 'max:255'])
                ->placeholder('Digite o nome')
                ->columnSpanFive(),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('description')
                ->label('Nome Fantasia')
                ->required()
                ->rules(['required', 'string', 'max:255'])
                ->placeholder('Digite o nome fantasia')
                ->columnSpanSeven(),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('cnpj')
                ->label('CNPJ')
                ->required()
                ->placeholder('Digite o CNPJ')
                ->columnSpanFour(),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('email')
                ->label('Email')
                ->required()
                ->placeholder('Digite o email')
                ->columnSpanFour(),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('phone')
                ->label('Telefone')
                ->required()
                ->phone()
                ->placeholder('Digite o telefone')
                ->columnSpanFour(),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\BuscaCepField::make('address')
                ->relationship('address')
                ->label('Endereço')
                ->placeholder('Digite o endereço')
                ->columnSpanFull(),
            // status
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('status')
                ->label('Status')
                ->options([
                    'published' => 'Publicado',
                    'draft' => 'Rascunho',
                ])
                ->required()
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
                ->executeUrl(route('tenant.providers.execute'))
                ->columnSpanOne(),
            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('name')
                ->label('Nome')
                ->searchable()
                ->sortable()->columnSpanFour(),

            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('code')
                ->label('Código')
                ->searchable()
                ->sortable()->columnSpanTwo(),
            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\DateColumn::make('created_at')
                ->label('Data de Criação')->columnSpanFour(),
        ]);

        $table->filters([
            \Callcocam\LaravelRaptor\Support\Table\Filters\TrashedFilter::make(),
        ]);

        $table->actions([
            // \Callcocam\LaravelRaptor\Support\Actions\Types\ViewAction::make('providers.show'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\EditAction::make('providers.edit'),

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

            \Callcocam\LaravelRaptor\Support\Actions\Types\RestoreAction::make('providers.restore'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\ForceDeleteAction::make('providers.forceDelete'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\DeleteAction::make('providers.destroy'),
        ]);

        $table->bulkActions([
            // Bulk actions
        ]);

        $table->headerActions([
            \Callcocam\LaravelRaptor\Support\Actions\Types\CreateAction::make('providers.create'),
            // \Callcocam\LaravelRaptor\Support\Actions\Types\ExportAction::make('providers.export'),
            // \Callcocam\LaravelRaptor\Support\Actions\Types\ImportAction::make('providers.import'),
        ]);

        return $table;
    }

    protected function resourcePath(): ?string
    {
        return 'tenant';
    }
}

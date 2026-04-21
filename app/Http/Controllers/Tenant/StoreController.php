<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Http\Controllers\Tenant;

use App\Models\Store;
use Callcocam\LaravelRaptor\Http\Controllers\AbstractController;
use Callcocam\LaravelRaptor\Support\Form\Form;
use Callcocam\LaravelRaptor\Support\Info\InfoList;
use Callcocam\LaravelRaptor\Support\Pages\Index;
use Callcocam\LaravelRaptor\Support\Table\TableBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class StoreController extends AbstractController
{
    public function getPages(): array
    {
        return [
            'index' => Index::route('/stores')
                ->label('Stores')
                ->name('stores.index')
                ->icon('Store')
                ->group('Operacional')
                ->groupCollapsible(true)
                ->order(10)
                ->visible(function () {
                    return config('app.context') === 'tenant';
                })
                ->middlewares(['auth', 'verified'])
                ->resource(Store::class),
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

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\HiddenField::make('client_id')
                ->default(config('app.current_client_id')),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('name')
                ->label('Nome')
                ->required()
                ->rules(['required', 'string', 'max:255'])
                ->placeholder('Digite o nome')
                ->columnSpanFull(),
            // document

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('code')
                ->label('Código Loja')
                ->required()
                ->rules(function ($record) {
                    $clientId = config('app.current_client_id');
                    $rules = ['required', 'string', 'max:100'];

                    $uniqueRule = \Illuminate\Validation\Rule::unique('landlord.stores', 'code')
                        ->where('client_id', $clientId)
                        ->withoutTrashed();

                    if ($record?->id) {
                        $uniqueRule->ignore($record->id);
                    }

                    $rules[] = $uniqueRule;

                    return $rules;
                })
                ->placeholder('Digite o código interno')
                ->columnSpanFour(),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('phone')
                ->label('Telefone')
                ->required()
                ->phone()
                ->placeholder('Digite o telefone')
                ->columnSpanFour(),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('email')
                ->label('Email')
                ->required()
                ->email()
                ->placeholder('Digite o email')
                ->columnSpanFour(),
        ]);

        $form->column(\Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('document')
            ->label('CNPJ')
            ->required()
            ->placeholder('Digite o CNPJ')
            ->columnSpanFour());

        $form->column(
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\BuscaCepField::make('address')
                ->relationship('address')
                ->label('Endereço')
                ->placeholder('Digite o endereço')
                ->columnSpanFull()
        );

        if ($form->getModel()->exists) {
            $form->column(\App\Form\Fields\MapsField::make('maps_integration')
                ->label('Integração com Google Maps')
                ->columnSpanFull());
        }
        $form->column(
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextareaField::make('description')
                ->label('Descrição')
                ->placeholder('Descrição')
                ->rows(4)
                ->columnSpanFull()
        );

        return $form;
    }

    protected function table(TableBuilder $table): TableBuilder
    {
        $table
            ->component('table-row')->columns([

                \Callcocam\LaravelRaptor\Support\Table\Columns\Types\StatusColumn::make('status')
                    ->label('Status')
                    ->editable()
                    ->executeUrl(route('tenant.stores.execute'))
                    ->columnSpanTwo(),
                \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()->columnSpanSix(),
                \Callcocam\LaravelRaptor\Support\Table\Columns\Types\DateColumn::make('created_at')
                    ->label('Data de Criação')->columnSpanFour(),
            ]);

        $table->filters([
            \Callcocam\LaravelRaptor\Support\Table\Filters\TrashedFilter::make(),
        ]);

        $table->actions([
            \Callcocam\LaravelRaptor\Support\Actions\Types\ViewAction::make('stores.show'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\EditAction::make('stores.edit'),

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

            \Callcocam\LaravelRaptor\Support\Actions\Types\RestoreAction::make('stores.restore'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\ForceDeleteAction::make('stores.forceDelete'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\DeleteAction::make('stores.destroy'),
        ]);

        $table->bulkActions([
            // Bulk actions
        ]);

        $table->headerActions([
            \Callcocam\LaravelRaptor\Support\Actions\Types\CreateAction::make('stores.create'),
            // \Callcocam\LaravelRaptor\Support\Actions\Types\ExportAction::make('stores.export'),
            // \Callcocam\LaravelRaptor\Support\Actions\Types\ImportAction::make('stores.import'),
        ]);

        return $table;
    }

    protected function beforeCreate(Request $request): void
    {
        $tenant = app('current.tenant');
        $count = Store::where('tenant_id', $tenant->id)->withoutTrashed()->count();
        app(\App\Services\TenantLimitService::class)->enforce('max_stores', $count, 'lojas');
    }

    protected function afterCreate(Request $request, $model)
    {
        $this->processMapData($request, $model);
    }

    protected function afterUpdate(Request $request, $model)
    {
        $this->processMapData($request, $model);
    }

    /**
     * Processa os dados do mapa da loja (imagem + regiões)
     */
    protected function processMapData(Request $request, $model): void
    {
        $mapData = $request->input('maps_integration');

        if (! $mapData) {
            return;
        }

        $updateData = [];

        // Processar imagem se for base64 (novo upload)
        if (! empty($mapData['image']) && str_starts_with($mapData['image'], 'data:image')) {
            $updateData['map_image_path'] = $this->processMapImage($mapData['image'], $model);
        }

        // Processar regiões
        if (isset($mapData['regions'])) {
            $updateData['map_regions'] = $mapData['regions'];
        }

        if (! empty($updateData)) {
            $model->update($updateData);
        }
    }

    /**
     * Converte imagem base64 para arquivo e salva no storage
     */
    protected function processMapImage(string $base64Image, $model): string
    {
        // Extrair dados do base64
        $imageData = explode(',', $base64Image);
        $imageContent = base64_decode($imageData[1] ?? $imageData[0]);

        $filename = "map-{$model->id}.webp";
        $path = "store-maps/{$model->id}/{$filename}";

        // Processar com Intervention Image e converter para WebP
        $image = Image::read($imageContent);

        // Redimensionar se muito grande (max 2000px de largura)
        if ($image->width() > 2000) {
            $image->scale(width: 2000);
        }

        // Salvar como WebP
        $encoded = $image->toWebp(quality: 85);
        Storage::disk('public')->put($path, $encoded);

        // Remover imagem antiga se existir
        if ($model->map_image_path && $model->map_image_path !== $path) {
            Storage::disk('public')->delete($model->map_image_path);
        }

        return $path;
    }

    protected function resourcePath(): ?string
    {
        return 'tenant';
    }
}

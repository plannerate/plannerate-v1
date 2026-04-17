<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\HasClientVisibilityRule;
use App\Models\Client;
use App\Services\Auth\LoginAsTokenBroker;
use App\Services\ClientMigrationService;
use Callcocam\LaravelRaptor\Http\Controllers\AbstractController;
use Callcocam\LaravelRaptor\Support\Form\Columns\Types\HiddenField;
use Callcocam\LaravelRaptor\Support\Form\Form;
use Callcocam\LaravelRaptor\Support\Pages\Edit;
use Callcocam\LaravelRaptor\Support\Pages\Index;
use Callcocam\LaravelRaptor\Support\Table\TableBuilder;
use Illuminate\Http\Request;

class ClientController extends AbstractController
{
    use HasClientVisibilityRule;

    protected function getResourceLabel(): ?string
    {
        return 'Cliente';
    }

    public function getPages(): array
    {

        return [
            'index' => Index::route('/clients')
                ->label('Clientes')
                ->name('clients.index')
                ->icon('UserCircle')
                ->group('Operacional')
                ->groupCollapsible(true)
                ->order(10)
                ->visible(fn () => $this->hasNoCurrentClientContext())
                ->middlewares(['auth', 'verified'])
                ->resource(Client::class),
        ];
    }

    public function table(TableBuilder $table): TableBuilder
    {
        $table->columns([
            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\StatusColumn::make('status')
                ->label('Status')
                ->editable()
                ->executeUrl(route('tenant.clients.execute'))
                ->columnSpanOne(),
            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('name')
                ->label('Nome')
                ->searchable()
                ->sortable()
                ->columnSpanTwo(),
            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('slug')
                ->label('Slug')
                ->searchable()
                ->sortable()->columnSpanTwo()
                ->icon('Tag'),
            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('email')
                ->label('Email')
                ->searchable()
                ->sortable()->columnSpanThree()
                ->icon('Envelope'),

            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('phone')
                ->label('Telefone')
                ->searchable()
                ->sortable()->columnSpanTwo()
                ->icon('Phone'),
            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\DateColumn::make('created_at')
                ->label('Data de Criação')->columnSpanTwo(),
        ]);

        $table->filters([
            \Callcocam\LaravelRaptor\Support\Table\Filters\TrashedFilter::make(),
        ]);
        $table->actions([
            // \Callcocam\LaravelRaptor\Support\Actions\Types\ViewAction::make('clients.show'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\EditAction::make('clients.edit'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\RestoreAction::make('clients.restore'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\ForceDeleteAction::make('clients.forceDelete'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\DeleteAction::make('clients.destroy'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\LinkAction::make('clients.loginAs')
                ->label('Login como Cliente')
                ->icon('UserCircle')
                ->color('blue')
                ->component('action-a-link')
                ->targetBlank()
                ->visibleWhen(function ($record) {
                    return $record?->domain !== null
                        && auth()->user()?->hasRole('super-admin');
                })
                ->url(function ($target, $request = null) {
                    if (
                        $target?->domain === null
                        || ! $request?->user()
                        || ! $request->user()->hasRole('super-admin')
                    ) {
                        return '#';
                    }

                    $token = app(LoginAsTokenBroker::class)->issue(
                        actor: $request->user(),
                        tenantId: (string) $target->tenant_id,
                        clientId: (string) $target->id
                    );

                    if (! $token) {
                        return '#';
                    }

                    return sprintf('%s://%s/login-as?token=%s', $request->getScheme(), $target->domain->domain, urlencode($token));
                }),
        ]);

        $table->headerActions([
            \Callcocam\LaravelRaptor\Support\Actions\Types\CreateAction::make('clients.create'),
        ]);

        return $table;
    }

    // public function edit(Request $request, string $record)
    // {
    //     $model = $this->model()::findOrFail($record);
    //     dd($model->toArray());
    // }

    protected function form(Form $form): Form
    {

        $form->columns([
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('name')
                ->label('Nome')
                ->required()
                ->rules(['required', 'string', 'max:255'])
                ->placeholder('Digite o nome')
                ->columnSpanFour(),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\DocumentField::make('cnpj')
                ->label('CNPJ')
                ->cnpj()
                ->required()
                ->rules(['required', 'string', 'max:18'])
                ->placeholder('Digite o CNPJ')
                ->columnSpanTwo()
                ->fieldMapping([
                    'razao_social' => 'name',
                ]),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('email')
                ->label('Email')
                ->required()
                ->rules(['required', 'email', 'max:255'])
                ->placeholder('Digite o email')
                ->columnSpanFour(),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('phone')
                ->label('Telefone')
                ->required()
                ->rules(['required', 'string', 'max:20'])
                ->placeholder('Digite o telefone')
                ->phone()
                ->columnSpanTwo(),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('status')
                ->label('Status')
                ->required()
                ->rules(['required', 'in:published,draft'])
                ->options([
                    'published' => 'Publicado',
                    'draft' => 'Rascunho',
                ])
                ->default('published')
                ->columnSpanFull(),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\BuscaCepField::make('address')
                ->label('Endereço')
                ->placeholder('Digite o endereço')
                ->columnSpanFull(),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextareaField::make('description')
                ->label('Descrição')
                ->placeholder('Descrição')
                ->rows(4)
                ->columnSpanFull(),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('domain')
                ->label('Domínio do Cliente')
                ->placeholder('Digite o domínio')
                ->helpText('Domínio principal do cliente, utilizado para identificação em multi-tenant.')
                ->relationship('domain')
                ->defaultUsing(function ($model) {
                    return $model && $model->domain ? $model->domain->domain : app('current.tenant')->domain;
                })
                ->columnSpanFive(),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('database')
                ->label('Banco de dados')
                ->placeholder('Digite o nome do banco de dados')
                ->helpText('Nome do banco de dados dedicado (se multi-database)')
                ->defaultUsing(function ($model) {
                    return $model && $model->database ? $model->database : app('current.tenant')->database;
                })
                ->columnSpanFour(),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('client_api_type')
                ->label('Tipo de API do Cliente')
                ->options([
                    'sysmo' => 'Sysmo',
                    'visao' => 'Visão',
                    'custom' => 'Customizada',
                ])
                ->placeholder('Selecione o tipo de API do cliente')
                ->columnSpanThree(),
        ]);

        $client_api_type = data_get($form->getModel(), 'client_api_type');
        $form->column(match ($client_api_type) {
            'sysmo' => \App\Form\Fields\SysmoApiField::make('client_integration')
                ->label('Configuração da API Sysmo')
                ->columnSpanFull(),
            'visao' => \App\Form\Fields\VisaoApiField::make('client_integration')
                ->label('Configuração da API Visão')
                ->columnSpanFull(),
            default => HiddenField::make('client_integration')->default(null),
        });

        return $form;
    }

    protected function resourcePath(): ?string
    {
        return 'tenant';
    }

    protected function beforeCreate(Request $request): void
    {
        $tenant = app('current.tenant');
        $count = Client::where('tenant_id', $tenant->id)->withoutTrashed()->count();
        app(\App\Services\TenantLimitService::class)->enforce('max_clients', $count, 'clientes');
    }

    protected function afterCreate(Request $request, $model): void
    {
        // Criar domínio automaticamente para o client
        if ($request->has('domain')) {
            $model->domain()->create([
                'tenant_id' => app('current.tenant')->id,
                'domain' => $request->input('domain'),
            ]);
        }

        app(ClientMigrationService::class)->runClientMigrations($model);
    }

    protected function afterUpdate(Request $request, $model): void
    {
        // Atualizar domínio se mudou
        if ($request->has('domain') && $model->domain) {
            $model->domain->update([
                'domain' => $request->input('domain'),
            ]);
        } elseif ($request->has('domain') && ! $model->domain) {
            // Criar domínio se não existia
            $model->domain()->create([
                'tenant_id' => app('current.tenant')->id,
                'domain' => $request->input('domain'),
            ]);
        }

        app(ClientMigrationService::class)->runClientMigrations($model);
    }
}

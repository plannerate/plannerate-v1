<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\HasClientVisibilityRule;
use App\Models\FlowStepTemplate;
use App\Models\User;
use App\Services\Flow\FlowStepTemplateAutoAssignmentResolver;
use App\Services\Import\Person\WorkflowStepTemplateImportService;
use Callcocam\LaravelRaptor\Http\Controllers\AbstractController;
use Callcocam\LaravelRaptor\Support\Form\Form;
use Callcocam\LaravelRaptor\Support\Info\InfoList;
use Callcocam\LaravelRaptor\Support\Pages\Index;
use Callcocam\LaravelRaptor\Support\Table\TableBuilder;
use Callcocam\LaravelRaptorFlow\Models\Flow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FlowStepTemplateController extends AbstractController
{
    use HasClientVisibilityRule;

    protected array $importSettings = [
        'disk' => 'public',
        'directory' => 'imports/flow_step_templates',
        'mapping' => [
            'name' => 'Nome do Workflowsteptemplate',
            'description' => 'Descrição',
            'instructions' => 'Instruções de Execução',
            'category' => 'Categoria',
            'defaultRole.name' => 'Papel Padrão',
            'templatePreviousStep.name' => 'Template Anterior',
            'templateNextStep.name' => 'Próximo Template',
            'suggested_order' => 'Ordem Sugerida',
            'estimated_duration_days' => 'Duração Estimada (dias)',
            'color' => 'Cor de Identificação',
            'is_required_by_default' => 'Obrigatório por Padrão',
            'is_active' => 'Template Ativo',
        ],
    ];

    public function getPages(): array
    {
        return [
            'index' => Index::route('/flow_step_templates')
                ->label('Templates de Etapas')
                ->name('flow_step_templates.index')
                ->group('Workflows')
                ->groupCollapsible(true)
                ->icon('FolderTree')
                ->visible(fn () => $this->hasNoCurrentClientContext())
                ->order(21)
                ->middlewares(['auth', 'verified'])
                ->resource(FlowStepTemplate::class),
        ];
    }

    protected function queryBuilder(): Builder
    {
        return parent::queryBuilder()->with(['templateNextStep', 'templatePreviousStep'])->orderBy('suggested_order', 'asc');
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
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('flow_id', 'Fluxo')
                ->options(Flow::query()->pluck('name', 'id')->toArray())
                ->columnSpanFour(),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('name')
                ->label('Nome')
                ->required()
                ->rules(['required', 'string', 'max:255'])
                ->placeholder('Digite o nome')
                ->columnSpanFive(),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('category', 'Categoria')
                ->options(FlowStepTemplate::getCategories())
                ->columnSpanThree(),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('default_role_id', 'Papel Padrão')
                ->options(\Callcocam\LaravelRaptor\Models\Role::query()->pluck('name', 'id')->toArray())
                ->placeholder('Selecione um papel responsável padrão')
                ->helperText('Define o papel responsável padrão ao criar workflows com este template')
                ->columnSpanFull(),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('template_previous_step_id', 'Etapa Anterior')
                ->options(function (?FlowStepTemplate $record = null) {
                    if (! $record) {
                        return [];
                    }
                    $data = FlowStepTemplate::query()
                        ->orderBy('suggested_order', 'asc')
                        ->where('id', '!=', data_get($record, 'id', ''));

                    if (data_get($record, 'suggested_order', null) !== null) {
                        $data->where('suggested_order', '<', data_get($record, 'suggested_order', 0));
                    }

                    return $data->pluck('name', 'id')->toArray();
                })
                    // ->relationship('template_previous_step', 'name', 'id')
                ->columnSpanSix(),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('template_next_step_id', 'Próxima Etapa')
                ->options(function (?FlowStepTemplate $record = null) {
                    if (! $record) {
                        return [];
                    }
                    $data = FlowStepTemplate::query()
                        ->orderBy('suggested_order', 'asc')
                        ->where('id', '!=', data_get($record, 'id', ''))
                        ->where('suggested_order', '>', data_get($record, 'suggested_order', 0))
                        ->pluck('name', 'id')->toArray();

                    return $data;
                })
                    // ->relationship('template_next_step', 'name', 'id')
                ->columnSpanSix(),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextareaField::make('description', 'Descrição')
                ->placeholder('Descrição detalhada da etapa')
                ->rows(3)
                ->columnSpanFull(),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextareaField::make('instructions', 'Instruções de Execução')
                ->placeholder('Instruções específicas para executar esta etapa')
                ->rows(4)
                ->columnSpanFull(),
            // Configurações
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('suggested_order', 'Ordem Sugerida')
                ->type('number')
                ->columnSpanThree(),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('estimated_duration_days', 'Duração Estimada (dias)')
                ->type('number')
                ->placeholder('Ex: 3')
                ->columnSpanThree(),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\SelectField::make('color', 'Cor de Identificação')
                ->options(FlowStepTemplate::getColors())
                ->columnSpanThree(),
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('icon', 'Ícone')
                ->placeholder('Ex: check-circle')
                ->columnSpanThree(),
            // Configurações avançadas
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\CheckboxField::make('is_required_by_default', 'Obrigatória por Padrão')
                ->default(false)
                ->columnSpanSix(),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\CheckboxField::make('is_active', 'Template Ativo')
                ->default(true)
                ->columnSpanSix(),

            // \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TagsField::make('tags', 'Tags')
            //     ->placeholder('Digite as tags separadas por vírgula')
            //     ->columnSpanFull(),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\MultiSelectField::make('users')
                ->label('Usuários sugeridos')
                ->options(User::query()->whereHas('roles', function ($query) {
                    $query->whereNotIn('slug', ['super-admin', 'admin', 'user']);
                })->pluck('name', 'id')->toArray())
                ->multiple()
                ->default(fn (?FlowStepTemplate $record) => $record?->users ?? [])
                ->columnSpanFull(),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextareaField::make('description')
                ->label('Descrição')
                ->placeholder('Descrição')
                ->rows(4)
                ->columnSpanFull(),
        ]);

        return $form;
    }

    protected function afterStore(Request $request, $model)
    {
        $this->syncSuggestedUsersMetadata($model, $request->input('users', []));
    }

    protected function afterUpdate(Request $request, $model)
    {
        $this->syncSuggestedUsersMetadata($model, $request->input('users', []));
    }

    protected function syncSuggestedUsersMetadata(FlowStepTemplate $model, mixed $usersPayload): void
    {
        $normalizedUsers = $this->normalizeSuggestedUsersPayload($usersPayload);

        $metadata = is_array($model->metadata) ? $model->metadata : [];

        if ($normalizedUsers === []) {
            unset($metadata['suggested_users']);
        } else {
            $metadata['suggested_users'] = $normalizedUsers;
        }
        $model->forceFill([
            'metadata' => $metadata,
        ])->save();
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeSuggestedUsersPayload(mixed $usersPayload): array
    {
        if (is_null($usersPayload) || $usersPayload === '') {
            return [];
        }

        if (is_string($usersPayload)) {
            $usersPayload = str_contains($usersPayload, ',')
                    ? explode(',', $usersPayload)
                    : [$usersPayload];
        }

        if (! is_array($usersPayload)) {
            $usersPayload = [$usersPayload];
        }

        return collect($usersPayload)
            ->map(function ($value) {
                if (is_array($value)) {
                    return $value['id'] ?? $value['value'] ?? null;
                }

                return $value;
            })
            ->filter(fn ($value) => ! is_null($value) && $value !== '')
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->values()
            ->all();
    }

    protected function table(TableBuilder $table): TableBuilder
    {
        $table->columns([
            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\BooleanColumn::make('is_active')
                ->label('Situação')
                ->trueLabel('Ativo')
                ->falseLabel('Inativo')
                ->trueColor('success')
                ->falseColor('danger')
                ->sortable()->columnSpanOne(),
            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('name')
                ->label('Nome')
                ->searchable()
                ->sortable()->columnSpanThree(),

            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('templatePreviousStep.name')
                ->label('Etapa Anterior')
                ->searchable()
                ->sortable()->columnSpanThree(),

            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\TextColumn::make('templateNextStep.name')
                ->label('Próxima Etapa')
                ->searchable()
                ->sortable()->columnSpanTwo(),
            \Callcocam\LaravelRaptor\Support\Table\Columns\Types\BooleanColumn::make('is_required_by_default', 'Obrigatório por Padrão')
                ->trueLabel('Sim')
                ->falseLabel('Não')
                ->trueColor('success')
                ->falseColor('warning')
                ->sortable()->columnSpanTwo(),
        ]);

        $table->filters([
            \Callcocam\LaravelRaptor\Support\Table\Filters\TrashedFilter::make(),
        ]);

        $table->actions([
            \Callcocam\LaravelRaptor\Support\Actions\Types\ViewAction::make('flow_step_templates.show'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\EditAction::make('flow_step_templates.edit'),

            \Callcocam\LaravelRaptor\Support\Actions\Types\RestoreAction::make('flow_step_templates.restore'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\ForceDeleteAction::make('flow_step_templates.forceDelete'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\DeleteAction::make('flow_step_templates.destroy'),
        ]);

        $table->bulkActions([
            // Bulk actions
        ]);

        $table->headerActions([
            \Callcocam\LaravelRaptor\Support\Actions\Types\CreateAction::make('flow_step_templates.create'),
            \Callcocam\LaravelRaptor\Support\Actions\Types\ConfirmAction::make('flow_step_templates.execute-seed-templates')
                ->label('Criar a partir de template padrão')
                ->url(route('tenant.flow_step_templates.execute'))
                ->actionType('header')
                ->callback(function () {
                    $defaultTemplates = FlowStepTemplate::getDefaultTemplates();
                    $tenantId = app('tenant')->id;
                    $flowId = Flow::query()
                        ->where('slug', 'planogramas')
                        ->value('id')
                            ?? Flow::query()->latest('created_at')->value('id');
                    $autoAssignmentResolver = app(FlowStepTemplateAutoAssignmentResolver::class);

                    FlowStepTemplate::query()->where('tenant_id', $tenantId)->forceDelete();

                    DB::transaction(function () use ($defaultTemplates, $tenantId, $flowId, $autoAssignmentResolver) {
                        $templatesByName = [];

                        // Primeira fase: cria/atualiza sem encadeamento (idempotente)
                        foreach ($defaultTemplates as $templateData) {
                            $templateData['tenant_id'] = $tenantId;
                            $templateData['flow_id'] = $flowId;
                            $templateData['slug'] = $templateData['slug'] ?? Str::slug((string) data_get($templateData, 'name'));
                            $templateData['template_previous_step_id'] = null;
                            $templateData['template_next_step_id'] = null;
                            $templateData['default_role_id'] = $templateData['default_role_id']
                                ?? $autoAssignmentResolver->resolveRoleIdByStep(
                                    (string) data_get($templateData, 'slug', ''),
                                    (string) data_get($templateData, 'name', ''),
                                    (string) $tenantId
                                );

                            $templatesByName[$templateData['name']] = FlowStepTemplate::updateOrCreate(
                                [
                                    'tenant_id' => $tenantId,
                                    'name' => $templateData['name'],
                                ],
                                $templateData
                            );
                        }

                        // Segunda fase: monta previous/next na ordem dos defaults
                        $previousStep = null;
                        foreach ($defaultTemplates as $templateData) {
                            $currentTemplate = $templatesByName[$templateData['name']] ?? null;
                            if (! $currentTemplate) {
                                continue;
                            }

                            $currentTemplate->template_previous_step_id = $previousStep ? $previousStep->id : null;
                            $currentTemplate->template_next_step_id = null;
                            $currentTemplate->save();

                            if ($previousStep) {
                                $previousStep->template_next_step_id = $currentTemplate->id;
                                $previousStep->save();
                            }

                            $previousStep = $currentTemplate;
                        }

                        $autoAssignmentResolver->applyToTemplates(collect($templatesByName)->values());
                    });

                    return redirect()->route('flow_step_templates.index')
                        ->with('success', 'Templates padrão criados com sucesso!');
                }),
            \Callcocam\LaravelRaptor\Support\Actions\Types\ExportAction::make('flow_step_templates.export')
                ->sheetName('Tabela de etapas')
                ->model(FlowStepTemplate::class)->exportColumns(data_get($this->importSettings, 'mapping', [])),
            \Callcocam\LaravelRaptor\Support\Actions\Types\ImportAction::make('flow_step_templates.import')
                    // ->useJob()
                ->column(\Callcocam\LaravelRaptor\Support\Form\Columns\Types\CheckboxField::make('clean_data', 'Limpar dados existentes')->default(false))
                ->callback(function ($request) {
                    $fileFieldName = str('flow_step_templates.import')->replace('import', 'file')->slug()->toString();
                    $flowId = Flow::query()
                        ->where('slug', 'planogramas')
                        ->value('id')
                            ?? Flow::query()->latest('created_at')->value('id');
                    $context = [
                        'tenant_id' => config('app.current_tenant_id'),
                        'user_id' => $request->user()?->getKey(),
                        'flow_id' => $flowId,
                    ];

                    return WorkflowStepTemplateImportService::make()
                        ->setContext($context)
                        ->import($request->file($fileFieldName));
                })
                ->sheets([
                    \Callcocam\LaravelRaptor\Support\Import\Columns\Sheet::make('Tabela de flowsteptemplates')
                        ->chunkSize(1000) // Reduz memória em planilhas grandes
                        ->updateBy(['tenant_id', 'name'])
                        ->modelClass(FlowStepTemplate::class)
                        ->serviceClass(WorkflowStepTemplateImportService::class)
                        ->columns([
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('name')
                                ->label('Nome')
                                ->required()
                                ->rules(['required', 'string', 'max:255']),
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('description')
                                ->label('Descrição')
                                ->required()
                                ->rules(['required', 'string', 'max:255']),
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('instructions')
                                ->label('Instruções de Execução')
                                ->required()
                                ->rules(['required', 'string', 'max:255']),
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('category')
                                ->label('Categoria')
                                ->required()
                                ->rules(['required', 'string', 'max:255']),
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('default_role_id')
                                ->label('Papel Padrão')
                                ->required()
                                ->rules(['required', 'string', 'max:255']),
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('template_previous_step_id')
                                ->label('Template Anterior')
                                ->required()
                                ->rules(['required', 'string', 'max:255']),
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('template_next_step_id')
                                ->label('Próximo Template')
                                ->required()
                                ->rules(['required', 'string', 'max:255']),
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('suggested_order')
                                ->label('Ordem Sugerida')
                                ->required()
                                ->rules(['required', 'integer', 'max:255']),
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('estimated_duration_days')
                                ->label('Duração Estimada (dias)')
                                ->required()
                                ->rules(['required', 'integer', 'max:255']),
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('color')
                                ->label('Cor de Identificação')
                                ->required()
                                ->rules(['required', 'string', 'max:255']),
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('is_required_by_default')
                                ->label('Obrigatório por Padrão')
                                ->required()
                                ->rules(['required', 'boolean']),
                            \Callcocam\LaravelRaptor\Support\Import\Columns\Types\ImportText::make('is_active')
                                ->label('Template Ativo')
                                ->required()
                                ->rules(['required', 'boolean']),
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

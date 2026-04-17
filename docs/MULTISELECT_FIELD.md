/**
 * MultiSelectField - Exemplos de Uso
 *
 * Componente multiselect para formulários no Plannerate
 * Funciona com opções estáticas ou carregamento dinâmico via API
 */

// ============================================================================
// BACKEND - app/Form/Fields/MultiSelectField.php
// ============================================================================

/*
 * 1. OPÇÕES ESTÁTICAS (Array)
 */
use App\Form\Fields\MultiSelectField;

// Com array de opções
MultiSelectField::make('userIds')
    ->label('Selecionar Usuários')
    ->options([
        'user1' => 'João Silva',
        'user2' => 'Maria Santos',
        'user3' => 'Pedro Costa',
    ])
    ->required()
    ->searchable()
    ->columnSpan(6);

// ============================================================================

/*
 * 2. CARREGAMENTO VIA API
 */

// Usando endpoint de API
MultiSelectField::make('workflowStepTemplateIds')
    ->label('Templates de Workflow')
    ->apiEndpoint('/api/workflow-step-templates')
    ->labelColumn('name')
    ->valueColumn('id')
    ->required()
    ->searchable()
    ->columnSpan(6);

// ============================================================================

/*
 * 3. CARREGAMENTO VIA TABELA
 */

// Carregando de uma tabela Eloquent
MultiSelectField::make('categoryIds')
    ->label('Categorias')
    ->table('categories')
    ->labelColumn('name')
    ->valueColumn('id')
    ->searchable()
    ->columnSpan(6);

// ============================================================================

/*
 * 4. COM AUTOCOMPLETE (Preenchimento Automático)
 */

// Quando seleciona uma opção, preenche outros campos automaticamente
MultiSelectField::make('productIds')
    ->label('Produtos')
    ->options(function () {
        return Product::query()
            ->get()
            ->mapWithKeys(fn($p) => [$p->id => $p->name])
            ->toArray();
    })
    ->withFullObject()  // Retorna objeto completo para autoComplete
    ->complete('ean', 'product_ean')           // source => target
    ->complete('category_id', 'product_category')
    ->complete('price', 'unit_price')
    ->searchable()
    ->columnSpan(6);

// ============================================================================

/*
 * 5. OPÇÕES DINÂMICAS COM QUERY
 */

// Carregando opções via query builder (para opções estáticas na página)
MultiSelectField::make('roleIds')
    ->label('Papéis (Roles)')
    ->options(function () {
        return Role::query()
            ->where('is_active', true)
            ->pluck('name', 'id')
            ->toArray();
    })
    ->required()
    ->columnSpan(6);

// ============================================================================

// EXEMPLO COMPLETO DE CONTROLLER

class WorkflowController
{
    protected function form(Form $form): Form
    {
        return $form->columns([
            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextField::make('workflow_name')
                ->label('Nome do Workflow')
                ->required()
                ->columnSpan(12),

            MultiSelectField::make('step_template_ids')
                ->label('Etapas do Workflow')
                ->apiEndpoint('/api/workflow-step-templates')
                ->labelColumn('name')
                ->valueColumn('id')
                ->required()
                ->searchable()
                ->columnSpan(6),

            MultiSelectField::make('responsible_user_ids')
                ->label('Usuários Responsáveis')
                ->apiEndpoint('/api/users')
                ->labelColumn('name')
                ->valueColumn('id')
                ->searchable()
                ->columnSpan(6),

            \Callcocam\LaravelRaptor\Support\Form\Columns\Types\TextareaField::make('description')
                ->label('Descrição')
                ->columnSpan(12),
        ]);
    }
}

// ============================================================================
// FRONTEND - resources/js/components/form/fields/FormFieldMultiSelect.vue
// ============================================================================

/*
 * PROPS DISPONÍVEIS:
 *
 * - column: {
 *     name: string                      // Nome do campo
 *     label?: string                    // Label a exibir
 *     placeholder?: string              // Placeholder do input
 *     required?: boolean                // Campo obrigatório
 *     searchable?: boolean              // Habilita busca
 *     options?: {                       // Opções estáticas
 *       [key: string]: string           // { 'id': 'label' } ou array
 *     }
 *     apiEndpoint?: string              // URL da API para buscar
 *     table?: string                    // Nome da tabela
 *     labelColumn?: string              // Coluna para label (default: 'name')
 *     valueColumn?: string              // Coluna para value (default: 'id')
 *     helperText?: string               // Texto de ajuda
 *     autoComplete?: {                  // Config de auto-complete
 *       enabled: boolean
 *       fields: Array<{
 *         source: string                // Campo na opção
 *         target: string                // Campo do form a preencher
 *         isFixedValue: boolean
 *       }>
 *     }
 *   }
 * - modelValue: string[]               // Valores selecionados
 * - error?: string | string[]           // Erros de validação
 * - optionsData?: Record<string, any>  // Dados completos das opções
 *
 * EVENTOS:
 * - update:modelValue (values: string[])  // Atualiza valores selecionados
 * - autoComplete (data: {                 // Quando dispara auto-complete
 *     source: string
 *     target: string
 *     value: any
 *   })
 */

// ============================================================================
// COMPONENTES UI USADOS (Shadcn/Vue)
// ============================================================================

/*
 * - Input              // Campo de entrada de busca
 * - Badge              // Badges para valores selecionados
 * - Checkbox           // Checkboxes nas opções
 * - Field/Label        // Wrapper de campo do formulário
 * - FieldDescription   // Texto de ajuda
 * - FieldError         // Exibição de erros
 */

// ============================================================================
// INTEGRAÇÃO COM INERTIA (Form Helper)
// ============================================================================

/*
 * Uso em um componente Inertia:
 */

import MultiSelectField from '@/components/form/fields/FormFieldMultiSelect.vue'

export default {
  components: { MultiSelectField },

  setup() {
    return {
      form: useForm({
        user_ids: [],
        product_ids: [],
        role_ids: [],
      }),

      field: {
        name: 'user_ids',
        label: 'Usuários',
        placeholder: 'Buscar usuários...',
        searchable: true,
        apiEndpoint: '/api/users',
        labelColumn: 'name',
        valueColumn: 'id',
      }
    }
  }
}

/*
 * Template:
 *
 * <MultiSelectField
 *   v-bind="field"
 *   v-model="form.user_ids"
 *   :error="form.errors.user_ids"
 * />
 */

// ============================================================================
// API ESPERADA PARA ENDPOINTS
// ============================================================================

/*
 * Para apiEndpoint='/api/users?search=john' esperado retorno:
 *
 * Array ou objeto com data:
 * [
 *   {
 *     id: 'uuid-1',
 *     name: 'John Doe',
 *     email: 'john@example.com'
 *   },
 *   {
 *     id: 'uuid-2',
 *     name: 'Jane Smith',
 *     email: 'jane@example.com'
 *   }
 * ]
 *
 * ou
 *
 * {
 *   data: [
 *     { id: 'uuid-1', name: 'John Doe', ... },
 *     { id: 'uuid-2', name: 'Jane Smith', ... }
 *   ]
 * }
 */

// ============================================================================
// RECURSOS
// ============================================================================

/*
 * - Backend: app/Form/Fields/MultiSelectField.php
 * - Frontend: resources/js/components/form/fields/FormFieldMultiSelect.vue
 * - Traits: HasAutoComplete (para suporte a auto-complete)
 * - Base: Extends Column (do Raptor)
 * - UI: Shadcn/Vue (Input, Badge, Checkbox, Field, etc)
 */

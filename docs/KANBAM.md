# Kanban Atual

Esta documentação resume a implementação atual do Kanban de workflow no Plannerate. O material antigo que descrevia um board Vue totalmente customizado ficou obsoleto após a migração para o pacote `laravel-raptor-flow`.

## Visão Geral

Hoje o Kanban é `flow-first`:

- O frontend renderiza o board com `FlowKanbanView` do pacote `@flow`
- A aplicação fornece páginas, filtros auxiliares e configuração declarativa de cards e modal
- O backend monta os dados via `App\Services\Workflow\KanbanService`
- O board trabalha sobre `FlowExecution`, `FlowConfigStep` e os modelos de domínio `PlanogramWorkflow` e `GondolaWorkflow`

Em termos práticos, o Plannerate não mantém mais componentes locais como `KanbanBoard.vue`, `KanbanColumn.vue`, `KanbanCard.vue` ou `PlanogramEditDrawer.vue`.

## Arquivos Relevantes

### Frontend

- `resources/js/pages/admin/tenant/plannerates/kanbans/index.vue`
	- Página do Kanban na área admin/tenant
	- Renderiza `FlowKanbanView` dentro de `ResourceLayout`
	- Injeta `board`, `groupConfigs`, `filters`, `userRoles`, `cardConfig`, `detailModalConfig` e `currentUserId`

- `resources/js/pages/tenant/plannerates/kanban.vue`
	- Página do Kanban no contexto do planograma
	- Também usa `FlowKanbanView`
	- Adiciona ação de criar gôndola com `GondolaCreateStepper`

- `resources/js/components/kanban/KanbanHeader.vue`
	- Cabeçalho leve para título, descrição e ações extras
	- Encapsula `KanbanFilters.vue`

- `resources/js/components/kanban/KanbanFilters.vue`
	- Painel de filtros baseado nos query params da URL
	- Usa `router.get()` para aplicar filtros sem quebrar histórico de navegação
	- Suporta `only_overdue` e `show_completed`
	- Consome configs vindas do backend no formato de `SelectFilter::toArray()`

- `resources/js/types/workflow.ts`
	- Tipos centralizados do Kanban atual
	- `KanbanBoard` é um alias do payload bruto do pacote Flow
	- Define `KanbanFilters`, `KanbanFilterConfig`, `KanbanFiltersState`, `FlowGroupConfig` e `KanbanIndexProps`

### Backend

- `app/Http/Controllers/Plannerate/KanbanController.php`
	- Monta o Kanban geral em `/kanbans/{flow}`
	- Usa `KanbanService` para gerar board, card config e modal config

- `app/Http/Controllers/Tenant/Plannerate/Editor/PlannerateController.php`
	- Reusa o mesmo serviço para o Kanban filtrado por planograma

- `app/Services/Workflow/KanbanService.php`
	- Serviço principal de composição do board
	- Estende `Callcocam\LaravelRaptorFlow\Services\KanbanService`
	- Resolve o fluxo, carrega planogramas e gôndolas válidos e monta colunas, filtros, cards e modal

## Fluxo de Dados

1. O controller resolve o flow e instancia o `KanbanService`
2. O serviço carrega `stepTemplates` ativos do flow atual
3. O serviço busca `FlowConfigStep` cujo `configurable_type` é `PlanogramWorkflow`
4. A partir desses configs, identifica os planogramas e as gôndolas visíveis
5. O serviço delega a construção do board ao service do pacote, adicionando:
	 - colunas do domínio
	 - filtros
	 - roles do usuário
	 - configuração declarativa do card
	 - configuração declarativa do modal
6. O frontend recebe esse payload e renderiza tudo com `FlowKanbanView`

## Permissões

As permissões do board não ficam mais dispersas em componentes Vue específicos.

- O backend expõe abilities por execução via `FlowExecutionPolicy`
- O `KanbanService` incorpora essas permissões ao payload usado pelo pacote
- O frontend apenas consome a configuração final para habilitar ou esconder ações

## Filtros

Os filtros atuais são controlados pela URL e podem incluir:

- `planogram_id`
- `assigned_to`
- `user_id`
- `status`
- `loja_id`
- `only_overdue`
- `show_completed`

O componente `KanbanFilters.vue` é responsável por:

- inicializar o estado a partir dos query params
- reaplicar mudanças com `router.get()`
- limpar filtros sem perder o estado navegável

## Quando Atualizar Esta Doc

Atualize este arquivo quando houver mudanças em pelo menos um destes pontos:

- troca do pacote/base visual do Kanban
- alteração das páginas que consomem `FlowKanbanView`
- mudança na forma de montar `cardConfig` ou `detailModalConfig`
- inclusão ou remoção de filtros públicos
- mudança relevante no fluxo de permissões por execução

## Observação

Se precisar documentar uma feature nova de atribuição de usuários no Kanban, faça isso neste arquivo com foco no fluxo atual baseado em `FlowExecution` e `FlowKanbanView`, não na arquitetura Vue antiga.
## Documentação

A documentação completa do projeto está organizada na pasta [`docs/`](./docs/). Abaixo estão os principais documentos:

### 📚 Documentação Geral
- **[Estrutura do Projeto](./docs/structure.md)** - Estrutura e organização do código
- **[Plano Completo](./docs/PLAN.md)** - Documentação completa do sistema multi-tenant

### 🚀 Deploy e Infraestrutura
- **[Deploy do Sistema](./docs/DEPLOY_NEW_SYSTEM.md)** - Guia de deploy do novo sistema
- **[Logs VPS](./docs/LOG-VPS.md)** - Documentação sobre logs do VPS

### ⚙️ Configuração e Operações
- **[Laravel Horizon](./docs/HORIZON.md)** - Monitoramento de filas com Horizon
- **[Comandos de Setup](./docs/COMANDOS_SETUP.md)** - Operações recorrentes e comandos úteis
- **[Importação Legacy](./docs/IMPORT_LEGACY_COMMAND.md)** - Fluxo atual de importação de dados legacy
- **[Sincronização Sequencial](./docs/SEQUENTIAL_SYNC.md)** - Sincronização sequencial de vendas

### 🔌 Integrações e APIs
- **[Dashboard de Integrações](./docs/DASHBOARD_INTEGRACOES.md)** - Sistema de integrações
- **[API de Vendas Melhorada](./docs/PLANO-API-SALES-MELHORADA.md)** - Sistema de sincronização de vendas com análise de gaps
- **[IA para Planogramas](./docs/IA-PLANOGRAM-USAGE.md)** - Fluxo atual de uso e geração de planogramas com IA

### 📊 Análises e Cálculos
- **[Análise ABC](./docs/abc.md)** - Documentação sobre análise ABC
- **[Cálculo ABC Frontend](./docs/abc-calculo-frontend.md)** - Cálculo ABC no frontend
- **[Estoque Alvo](./docs/stoke-alvo.md)** - Sistema de estoque alvo
- **[Laravel Echo Vue](./docs/doc-echo-vue.md)** - Uso do Laravel Echo com Vue

### 📖 PostgreSQL
Para detalhes sobre arquitetura de banco e contexto tenant, consulte:
- **[Arquitetura do Banco](./docs/database-architecture.md)** - Visão geral da arquitetura de dados
- **[Conexões dos Models](./docs/models-database-connections.md)** - Referência rápida de conexões e contexto
- **[Custom Tenant Resolver](./docs/custom-tenant-resolver.md)** - Resolução de tenant por domínio e contexto

---

## Progresso Plannerate (Refactor Otimista)

- ✅ Análise inicial do frontend (Editor, composables) e backend (controllers Plannerate) para entender fluxo de deltas.
- ✅ Plano de melhorias definido (estado centralizado, commits otimistas, autosave, soft delete, normalização).
- ✅ Normalização e helpers de lookup para seções/prateleiras/segmentos, garantindo reatividade.
- ✅ Auto-save real (debounce) com contexto configurável de rota e gondola_id; fila de mudanças mantida.
- ✅ Soft delete no front (marca `deleted_at` em seção/shelf/layer) + sanitização na inicialização (filtra `deleted_at`).
- ✅ Extensão de contexto de seleção para remoção otimista em cascata (seções/shelves/layers).
- ✅ Organização: composables do planograma movidos para `resources/js/composables/plannerate/` e imports atualizados.
- ✅ Movimentação de prateleira por arraste entre seções, com snap e persistência (transfer + posição).
- ✅ Refatoração de campos comuns: criação de composables `useGondolaFields`, `useSectionFields` e `useShelfFields` para centralizar campos, valores padrão, validações e funções helper.
- ✅ Ordem visual das seções respeita o fluxo da gôndola (`left_to_right` ou `right_to_left`), apenas no front, sem alterar o banco.
- ✅ Refatoração de componentes de formulário para usar os novos composables, mantendo toda funcionalidade existente.
- ✅ Refatoração do `SaveChangesController`: separação em Services e Repositories para melhor organização e manutenibilidade.

Itens pendentes / próximos passos sugeridos
- ⏳ Reconciliar resposta do backend se ele começar a retornar ids/timestamps para merge fino (hoje mantemos estado otimista e limpamos fila no sucesso).
- ⏳ Garantir que quaisquer pontos adicionais de criação/edição de segmentos/camadas fora dos renderers também propaguem contexto para remoção otimista, se existirem.

## Otimização e Limpeza de Código (2024)

### Composables Utilitários Criados
- ✅ **`usePlanogramUtils.ts`**: Composable utilitário compartilhado para funções comuns
  - `shouldShowDeleteConfirm()`: Verifica se deve mostrar modal de confirmação baseado no localStorage

### Refatorações Realizadas
- ✅ **Remoção de código duplicado**:
  - Função `checkShouldShowDeleteConfirmHelper` removida de 3 arquivos e centralizada em `usePlanogramUtils.ts`
  - Função `getDisplaySectionsLocal()` removida de 2 arquivos
  - Composables agora usam diretamente `editor.sectionsOrdered.value` (computed reativo) ao invés de funções locais
  - Removida função `getDisplaySections()` do utilitário (não mais necessária)

- ✅ **Consolidação de lógica de ordenação**:
  - Lógica de ordenação de seções consolidada no computed `sectionsOrdered` do `usePlanogramEditor`
  - Composables `useSectionActions` e `useShelfActions` agora usam diretamente o computed reativo
  - Melhor performance e consistência (uma única fonte de verdade)

- ✅ **Limpeza de código não utilizado**:
  - Removido `ShelfRendererOld.vue` (arquivo não utilizado)
  - Removida função `flip()` não implementada e não utilizada
  - Melhorada documentação de funções deprecated (`alignVertical`, `distribute`, `alignHorizontal`)

### Arquivos Modificados
- Criado: `usePlanogramUtils.ts`
- Atualizado: `useSectionActions.ts` - Usa utilitário compartilhado
- Atualizado: `useShelfActions.ts` - Usa utilitário compartilhado
- Atualizado: `useSegmentActions.ts` - Usa utilitário compartilhado
- Atualizado: `usePlanogramKeyboard.ts` - Importa utilitário diretamente
- Atualizado: `usePlanogramEditor.ts` - Removida função `flip()` e melhorada documentação de deprecated
- Removido: `ShelfRendererOld.vue`

### Benefícios
- **Redução de duplicação**: Código duplicado removido e centralizado
- **Manutenibilidade**: Mudanças em funções comuns agora são feitas em um único lugar
- **Organização**: Código mais limpo e organizado
- **Documentação**: Funções deprecated melhor documentadas

Notas
- Atalhos e payload de delta permanecem inalterados.
- Uso de Lucide Vue preservado.
- Sem comandos sensíveis executados (build/dev/server).

## Refatoração de Composables de Campos (2024)

### Composables Criados
- **`useGondolaFields.ts`**: Agrupa campos, valores padrão e funções helper para gôndolas
  - Tipos: `GondolaFieldsCamel`, `GondolaFieldsSnake`
  - Funções: `generateGondolaCode()`, `toSnakeCase()`, `toCamelCase()`, `getInitialGondolaFields()`, `validateGondolaFields()`
  - Valores padrão: `DEFAULT_GONDOLA_FIELDS`

- **`useSectionFields.ts`**: Agrupa campos, valores padrão e funções helper para seções/módulos
  - Tipos: `SectionFieldsCamel`, `SectionFieldsSnake`
  - Funções: `toSnakeCase()`, `toCamelCase()`, `getInitialSectionFields()`, `validateSectionFields()`, `calculateUsableHeight()`
  - Valores padrão: `DEFAULT_SECTION_FIELDS`

- **`useShelfFields.ts`**: Agrupa campos, valores padrão e funções helper para prateleiras
  - Tipos: `ShelfFieldsCamel`, `ShelfFieldsSnake`
  - Funções: `toSnakeCase()`, `toCamelCase()`, `getInitialShelfFields()`, `validateShelfFields()`, `calculateShelfSpacing()`, `calculateTotalDisplayArea()`
  - Valores padrão: `DEFAULT_SHELF_FIELDS`

### Componentes Refatorados
- ✅ `GondolaCreateStepper.vue` - Usa composables para valores padrão e inicialização
- ✅ `GondolaEditForm.vue` - Usa composables para valores padrão e conversão de dados
- ✅ `AddModuleSheet.vue` - Usa composables para inicialização de seção e prateleira
- ✅ `SectionShelfBulkUpdate.vue` - Usa composables para valores padrão, conversão e cálculos
- ✅ `Step1BasicInfo.vue` - Usa `validateGondolaFields` do composable
- ✅ `Step2Modules.vue` - Usa `validateSectionFields` do composable
- ✅ `Step3Base.vue` - Usa `validateSectionFields` do composable
- ✅ `Step4Rack.vue` - Usa `validateSectionFields` do composable
- ✅ `Step5Shelves.vue` - Usa `validateShelfFields` e funções de cálculo dos composables

### Benefícios
- **Centralização**: Campos comuns agora estão em um único lugar, facilitando manutenção
- **Reutilização**: Funções helper podem ser usadas em qualquer componente
- **Consistência**: Valores padrão e validações são consistentes em todo o sistema
- **Type Safety**: Tipos TypeScript garantem segurança de tipos
- **Manutenibilidade**: Mudanças em campos podem ser feitas em um único lugar

## Refatoração do SaveChangesController (2024)

### Estrutura Criada

#### FormRequest
- **`SaveChangesRequest.php`**: Validação centralizada do payload de mudanças

#### Repositories (Acesso a Dados)
- **`ShelfRepository.php`**: Operações de acesso a dados de prateleiras
- **`SectionRepository.php`**: Operações de acesso a dados de seções/módulos
- **`SegmentRepository.php`**: Operações de acesso a dados de segmentos
- **`LayerRepository.php`**: Operações de acesso a dados de camadas de produtos
- **`ProductRepository.php`**: Operações de acesso a dados de produtos
- **`GondolaRepository.php`**: Operações de acesso a dados de gôndolas
- ~~`DimensionRepository.php`~~ (removido – deprecated; use ProductRepository para dimensões)

#### Services (Lógica de Negócio)
- **`ShelfService.php`**: Lógica de negócio para prateleiras (create, update, move, transfer)
- **`SectionService.php`**: Lógica de negócio para seções/módulos
- **`SegmentService.php`**: Lógica de negócio para segmentos
- **`LayerService.php`**: Lógica de negócio para camadas (create, update, remove)
- **`ProductService.php`**: Lógica de negócio para produtos (dimensões, posicionamento)
- **`GondolaService.php`**: Lógica de negócio para gôndolas (update, scale, alignment, flow)
- **`PlanogramChangeService.php`**: Service orquestrador que roteia mudanças para os services específicos

#### Controller Refatorado
- **`SaveChangesController.php`**: Reduzido de 873 linhas para ~70 linhas
  - Usa FormRequest para validação
  - Delega toda lógica de negócio para Services
  - Mantém apenas orquestração e tratamento de erros

### Benefícios da Refatoração
- **Separação de Responsabilidades**: Cada camada tem uma responsabilidade clara
- **Testabilidade**: Services e Repositories podem ser testados isoladamente
- **Reutilização**: Services podem ser usados em outros controllers ou jobs
- **Manutenibilidade**: Código mais organizado e fácil de entender
- **Escalabilidade**: Fácil adicionar novas funcionalidades sem modificar código existente
- **Controller Enxuto**: Controller focado apenas em orquestração HTTP

## Refatoração da Página Welcome (2025)

### Modificações Realizadas
- ✅ **Substituição de SVGs por ícones Lucide Vue**: Todos os SVGs inline foram substituídos por ícones do Lucide Vue Next
- ✅ **Uso de cores do sistema**: Removidas cores hardcoded (orange-500, red-600, slate-*) e substituídas pelas variáveis CSS do sistema (--primary, --background, --foreground, etc.)
- ✅ **Componentes shadcn-vue**: Uso consistente dos componentes Button, Card, Badge do shadcn-vue
- ✅ **Remoção de botões de login/registro**: Botões "Criar Conta" e "Entrar" foram removidos conforme solicitado
- ✅ **Botão de WhatsApp/Chat**: Adicionado botão "Falar Conosco" que abre WhatsApp em nova aba
- ✅ **Design moderno e clean**: Interface refatorada para ser mais moderna e limpa
- ✅ **Dark mode**: Suporte completo ao dark mode usando as cores do sistema

### Ícones Lucide Vue Utilizados
- `LayoutGrid`: Logo e ícone de gôndolas
- `Zap`: Dashboard e colaboração em tempo real
- `MessageCircle`: Botão de WhatsApp/Chat
- `Clock`: Autosave inteligente
- `BarChart3`: Analytics avançado
- `Shield`: Multi-tenant seguro
- `Image`: Exportação visual
- `Users`: Clientes ativos
- `CheckCircle2`: Uptime garantido
- `Globe`: Domínio de clientes
- `ArrowDown`: Navegação e CTAs
- `Sparkles`: Efeitos visuais

### Arquivos Modificados
- ✅ `resources/js/pages/Welcome.vue` - Refatoração completa

### Benefícios
- **Consistência**: Uso das cores padrão do sistema garante consistência visual
- **Manutenibilidade**: Cores centralizadas facilitam mudanças de tema
- **Acessibilidade**: Dark mode funcional com as cores do sistema
- **Modernidade**: Design mais limpo e moderno
- **Integração**: Botão de WhatsApp facilita contato com clientes

## Limpeza de Código Antigo - Manutenção Apenas V3 (2025)

### Objetivo
Manter apenas a versão v3 do editor de planogramas, removendo código legado e duplicado para simplificar a base de código e facilitar manutenção.

### Arquivos Removidos

#### Componentes Antigos
- ✅ `resources/js/components/plannerate/Editor.vue` - Componente principal da versão antiga
- ✅ `resources/js/components/plannerate/editor/` - Pasta completa com todos os componentes da versão antiga
  - `EditorHeader.vue`, `EditorToolbar.vue`, `PlanogramCanvas.vue`
  - `ProductsPanel.vue`, `PropertiesPanel.vue`
  - `sections/` (SectionRenderer, ShelfRenderer, SegmentRenderer, LayerRenderer, etc.)
  - `products/` (ProductCard, ProductSearch, ProductStats, ProductFilters)
  - `properties/` (SectionProperties, ShelfProperties, LayerProperties)
  - `form/` (AddModuleDrawer, GondolaDrawer)
- ✅ `resources/js/components/plannerate/example/` - Pasta de exemplos não utilizada

#### Páginas Antigas
- ✅ `resources/js/pages/tenant/plannerates/gondolas/edit.vue` - Página antiga não utilizada pelo controller

#### Composables Antigos
- ✅ `resources/js/composables/plannerate/usePlanogramEditor.ts` - Versão antiga
- ✅ `resources/js/composables/plannerate/usePlanogramKeyboard.ts` - Versão antiga
- ✅ `resources/js/composables/plannerate/usePlanogramSelection.ts` - Versão antiga
- ✅ `resources/js/composables/plannerate/usePlanogramChanges.ts` - Versão antiga
- ✅ `resources/js/composables/plannerate/usePlanogramHistory.ts` - Versão antiga
- ✅ `resources/js/composables/plannerate/useShelfRenderer.ts` - Versão antiga

### Arquivos Mantidos (V3)
- ✅ `resources/js/components/plannerate/v3/` - Toda a estrutura v3 mantida
- ✅ `resources/js/composables/plannerate/v3/` - Todos os composables v3 mantidos
- ✅ `resources/js/components/plannerate/GondolaCards.vue` - Componente usado em `index.vue`
- ✅ `resources/js/pages/tenant/plannerates/gondolas/edit-v3.vue` - Página ativa usada pelo controller

### Correções Realizadas
- ✅ **Imports corrigidos**: Ajustados imports quebrados em `useProductsPanel.ts` e `Listar.vue` para usar `@/types/planogram` ao invés do componente removido
- ✅ **Verificação de dependências**: Confirmado que não há mais referências aos arquivos antigos

### Benefícios
- **Código mais limpo**: Remoção de ~50+ arquivos não utilizados
- **Manutenibilidade**: Base de código simplificada, focada apenas na versão v3
- **Redução de confusão**: Desenvolvedores não precisam mais escolher entre versões antigas e novas
- **Performance**: Menos arquivos para processar e menor bundle size
- **Clareza**: Estrutura de pastas mais clara e organizada

## Melhorias em Análise de Performance (2025)

### Comando de Recálculo de Vendas Mensais
- ✅ **Criado comando `monthly-sales:recalculate`**: Recalcula e verifica integridade da tabela `monthly_sales_summaries`
  - Agrega dados da tabela `sales` por mês, cliente, loja, produto e promoção
  - Opções: `--verify` (apenas verifica), `--client-id` (filtra por cliente), `--month` (filtra por mês)
  - Otimizado para PostgreSQL: usa `DATE_TRUNC` para formatação de datas
  - Verificação otimizada: calcula totais diretamente no banco sem carregar todos os registros na memória
  - Validado: 5.633.083 registros processados com sucesso e dados conferem perfeitamente

### Padronização de Modais de Parâmetros
- ✅ **`TargetStockParamsModal.vue`**: Refatorado para seguir padrão do ABC
  - Removido campo "Período (Mensal/Diário)" - agora determinado automaticamente pelo tipo de tabela
  - Adicionados campos `start_month` e `end_month` para períodos mensais
  - Quando `table_type === 'sales'`: mostra campos de data (`date_from`, `date_to`) com `type="date"`
  - Quando `table_type === 'monthly_summaries'`: mostra campos de mês (`start_month`, `end_month`) com `type="month"`
  - Adicionado watch para sincronizar form com initialData (similar ao AbcParamsModal)
  - Estilização padronizada: mesmas classes e tamanhos do AbcParamsModal

- ✅ **`PerformanceTargetStockTab.vue`**: Atualizado para suportar períodos mensais
  - Adicionados campos `start_month` e `end_month` no form state
  - Adicionado watch no planogram para popular campos automaticamente
  - Display do CardContent ajustado: mostra data ou mês conforme o tipo de tabela
  - Função `formatMonth()` criada para formatar mês no padrão MM/YYYY

### Arquivos Modificados
- ✅ `app/Console/Commands/RecalculateMonthlySalesSummaries.php` - Comando de recálculo otimizado
- ✅ `resources/js/components/plannerate/analysis/TargetStockParamsModal.vue` - Modal padronizado
- ✅ `resources/js/components/plannerate/v3/header/PerformanceTargetStockTab.vue` - Suporte a períodos mensais

### Refatoração e Organização de Componentes de Análise
- ✅ **Reorganização de arquivos**: Componentes movidos para pasta `analysis`
  - Movidos de `v3/header` para `analysis`: `AbcParamsModal.vue`, `AbcResultsList.vue`, `TargetStockResultsList.vue`
  - Todos os componentes de análise agora centralizados em uma única pasta

- ✅ **Criado composable `useAnalysisFilters.ts`**: Lógica compartilhada de filtros
  - Gerencia busca, filtros por classe e ordenação
  - Suporta tipagem genérica para diferentes tipos de resultado
  - Funções reutilizáveis: `handleSort`, `getClassBadgeVariant`, `getClassRowClass`
  - Elimina ~150 linhas de código duplicado entre ResultsList

- ✅ **Criado componente `AnalysisPeriodSelector.vue`**: Seletor de período compartilhado
  - Componente reutilizável para seleção de tipo de tabela e período
  - Suporta tanto sales (date) quanto monthly_summaries (month)
  - Elimina ~80 linhas de código duplicado entre modais de parâmetros

- ✅ **Refatorados `AbcResultsList.vue` e `TargetStockResultsList.vue`**:
  - Agora usam `useAnalysisFilters` para lógica comum
  - Código reduzido de ~200 para ~90 linhas cada
  - Mantém funcionalidades específicas (stats customizados)

- ✅ **Refatorados `AbcParamsModal.vue` e `TargetStockParamsModal.vue`**:
  - Agora usam `AnalysisPeriodSelector` para seleção de período
  - Código reduzido em ~80 linhas cada
  - Eliminada duplicação total da seção de tipo/período

### Arquivos Criados
- ✅ `resources/js/composables/plannerate/analysis/useAnalysisFilters.ts` - Composable compartilhado
- ✅ `resources/js/components/plannerate/analysis/AnalysisPeriodSelector.vue` - Componente compartilhado

### Arquivos Atualizados
- ✅ `resources/js/components/plannerate/v3/header/PerformanceAbcTab.vue` - Imports atualizados
- ✅ `resources/js/components/plannerate/v3/header/PerformanceTargetStockTab.vue` - Imports atualizados

### Benefícios
- **Integridade de Dados**: Comando de recálculo garante que dados mensais estão corretos
- **Consistência**: Modais de ABC e Target Stock agora seguem o mesmo padrão visual e funcional
- **Usabilidade**: Input type="month" oferece melhor UX para seleção de períodos mensais
- **Manutenibilidade**: Código padronizado facilita futuras modificações
- **Performance**: Verificação otimizada processa grandes volumes sem esgotamento de memória
- **DRY Principle**: ~310 linhas de código duplicado eliminadas
- **Organização**: Componentes de análise centralizados em uma pasta
- **Reutilização**: Lógica comum extraída em composables e componentes compartilhados
- **Type Safety**: Composable usa generics para garantir type safety

## Indicadores Visuais de Performance ABC (2025)

### Implementação
- ✅ **Criado composable `useAbcClassification.ts`**: Estado global reativo para classificações ABC
  - Gerencia mapa de EAN → Classificação (A, B, C)
  - Funções: `setClassifications()`, `getClassification()`, `clearClassifications()`
  - Estatísticas em tempo real: total, classA, classB, classC
  - Timestamp da última análise

- ✅ **Criado componente `AbcBadge.vue`**: Badge visual de classificação
  - Cores distintas: A (verde), B (amarelo), C (cinza)
  - Posicionamento absoluto no canto superior direito
  - Tooltip informativo
  - Design discreto e responsivo

- ✅ **Integrado em `Segment.vue`**: Exibição automática no planograma
  - Busca classificação por EAN do produto
  - Atualização reativa em tempo real
  - Não interfere com drag & drop ou seleção

- ✅ **Atualizado `PerformanceAbcTab.vue`**: Salva classificações após cálculo
  - Popula estado global automaticamente
  - Sincronização em tempo real com o planograma
  - Log de confirmação no console

### Arquivos Criados
- ✅ `resources/js/composables/plannerate/v3/useAbcClassification.ts` - Gerenciamento de estado
- ✅ `resources/js/components/plannerate/v3/editor/AbcBadge.vue` - Componente visual

### Arquivos Modificados
- ✅ `resources/js/components/plannerate/v3/editor/Segment.vue` - Integração do badge
- ✅ `resources/js/components/plannerate/v3/header/PerformanceAbcTab.vue` - Salvamento de classificações

### Fluxo de Funcionamento
1. **Análise ABC**: Usuário configura parâmetros e executa análise em `PerformanceAbcTab`
2. **Salvamento**: Resultados são salvos automaticamente no estado global por EAN
3. **Exibição**: Cada produto no planograma busca sua classificação pelo EAN
4. **Atualização**: Badge é exibido em tempo real sobre o produto no `Segment`
5. **Persistência**: Estado mantido até nova análise ou limpeza manual

### Benefícios
- **Visualização Imediata**: Classificações ABC visíveis diretamente no planograma
- **Tempo Real**: Atualização automática após cada análise
- **Performance**: Estado global reativo otimizado com Map
- **UX**: Cores intuitivas facilitam identificação rápida
- **Não Invasivo**: Badge discreto não interfere nas operações do editor
- **Reutilizável**: Composable pode ser usado em outros componentes

## Indicadores Visuais de Estoque Alvo (2025)

### Implementação
- ✅ **Criado composable `useTargetStockAnalysis.ts`**: Estado global reativo para estoque alvo
  - Gerencia mapa de EAN → Dados de Estoque (target, mínimo, atual, segurança)
  - Funções: `setTargetStockDataBatch()`, `getTargetStockData()`, `calculateSegmentCapacity()`, `getStockStatus()`
  - Cálculo automático de status baseado em capacidade vs estoque alvo
  - Margem de tolerância configurável (padrão 10%)

- ✅ **Criado componente `StockIndicator.vue`**: Indicador visual de estoque
  - Overlay colorido sobre o produto: vermelho (aumentar), amarelo (diminuir), verde (ok)
  - Ícones do Lucide: TrendingUp, TrendingDown, CheckCircle
  - Tooltip rico com informações detalhadas:
    - Capacidade do segment (frentes × altura × profundidade)
    - Estoque alvo, mínimo, atual e segurança
    - Demanda média e faixa de tolerância
    - Recomendações visuais de ajuste
  - Cálculo em tempo real da capacidade

- ✅ **Integrado em `Segment.vue`**: Exibição automática no planograma
  - Busca dados de target stock por EAN do produto
  - Calcula capacidade do segment automaticamente
  - Determina status (increase/decrease/ok) em tempo real
  - Reage a mudanças nas frentes, altura e profundidade

- ✅ **Atualizado `PerformanceTargetStockTab.vue`**: Salva dados após cálculo
  - Popula estado global automaticamente
  - Sincronização em tempo real com o planograma
  - Log de confirmação no console

- ✅ **Atualizado `Shelf.vue`**: Passa profundidade da prateleira
  - Adiciona prop `shelf-depth` para cálculo de capacidade
  - Propaga dados necessários para o Segment

### Arquivos Criados
- ✅ `resources/js/composables/plannerate/v3/useTargetStockAnalysis.ts` - Gerenciamento de estado
- ✅ `resources/js/components/plannerate/v3/editor/StockIndicator.vue` - Componente visual

### Arquivos Modificados
- ✅ `resources/js/components/plannerate/v3/editor/Segment.vue` - Integração do indicador
- ✅ `resources/js/components/plannerate/v3/editor/Shelf.vue` - Propagação de shelf_depth
- ✅ `resources/js/components/plannerate/v3/header/PerformanceTargetStockTab.vue` - Salvamento de dados

### Fluxo de Funcionamento
1. **Análise Target Stock**: Usuário configura parâmetros e executa cálculo em `PerformanceTargetStockTab`
2. **Salvamento**: Resultados são salvos automaticamente no estado global por EAN
3. **Cálculo de Capacidade**: Para cada segment, calcula: frentes × altura × profundidade
4. **Determinação de Status**: Compara capacidade calculada com estoque alvo (± tolerância)
5. **Exibição**: Indicador visual é exibido sobre o produto com cor e ícone adequados
6. **Reatividade**: Ao ajustar frentes/altura, o indicador atualiza instantaneamente

### Lógica de Status
- **🔴 Increase (Vermelho)**: Capacidade < Estoque Alvo - Tolerância → Precisa aumentar espaço
- **🟡 Decrease (Amarelo)**: Capacidade > Estoque Alvo + Tolerância → Precisa diminuir espaço
- **🟢 OK (Verde)**: Capacidade dentro da faixa de tolerância → Espaço adequado

### Cálculo de Capacidade
```
Capacidade = Frentes × Altura × (Profundidade Shelf ÷ Profundidade Produto)

Exemplo:
- Frentes (segment.quantity): 3
- Altura (layer.quantity): 2
- Profundidade Shelf: 60cm
- Profundidade Produto: 15cm
- Itens na Profundidade: 60 ÷ 15 = 4
- Capacidade Total: 3 × 2 × 4 = 24 unidades
```

### Benefícios
- **Visualização Intuitiva**: Status visual imediato com cores e ícones
- **Tempo Real**: Atualização automática ao ajustar frentes
- **Decisões Informadas**: Tooltip detalhado auxilia no planejamento
- **Cálculo Preciso**: Considera todas as dimensões (largura, altura, profundidade)
- **Tolerância Inteligente**: Margem de 10% evita ajustes desnecessários
- **Performance**: Estado global otimizado com Map
- **Integração Completa**: Funciona perfeitamente com ABC e outras funcionalidades

## Infraestrutura PostgreSQL com Replicação (2025)

### Arquitetura
O projeto agora utiliza um **servidor PostgreSQL externo** com replicação ao invés de containers locais, proporcionando:
- ✅ Alta disponibilidade
- ✅ Melhor performance
- ✅ Backups automáticos
- ✅ Escalabilidade

### Configuração do Cluster
- **Servidor Primário**: 192.168.2.106 (Leitura + Escrita)
- **Réplica 1**: VM local (Somente Leitura)
- **Réplica 2**: VM local (Somente Leitura)
- **Replicação**: Streaming assíncrona

### Bases de Dados por Ambiente

| Ambiente | Database | Arquivo .env | pgAdmin URL |
|----------|----------|-------------|-------------|
| 🟡 Staging | `plannerate_staging` | `.env.staging` | `https://pgadmin.plannerate.dev.br` |
| 🔴 Production | `plannerate_production` | `.env.production` | `https://pgadmin.plannerate.com.br` |

### Configuração do .env

Para cada ambiente, configure as seguintes variáveis:

```bash
# PostgreSQL Externo
DB_CONNECTION=pgsql
DB_HOST=192.168.2.106
DB_PORT=5432
DB_DATABASE=laravel  # ou plannerate_staging / plannerate_production
DB_USERNAME=replicator
DB_PASSWORD=replicator_password
```

### Documentação Completa
Para detalhes sobre instalação, configuração e manutenção do banco e contexto multi-tenant:
- 📖 **Arquitetura**: `docs/database-architecture.md`
- ⚡ **Referência rápida**: `docs/models-database-connections.md`
- 🔧 **Resolver de tenant**: `docs/custom-tenant-resolver.md`

### Modificações nos Docker Compose

#### Arquivos Atualizados
- ✅ `docker-compose.staging.yml` - Staging
- ✅ `docker-compose.production.yml` - Produção

#### O que foi Removido
- ❌ Serviço `postgres` (agora externo)
- ❌ Volume `postgres_data` (dados no servidor externo)
- ❌ Dependências de healthcheck do postgres nos serviços

#### O que foi Mantido/Adicionado
- ✅ **pgAdmin** mantido em todos os ambientes para gerenciar o servidor externo
- ✅ **Comentários** explicativos sobre a configuração externa
- ✅ Todos os demais serviços (app, redis, minio, reverb, queue, scheduler)

### Benefícios da Mudança

#### Performance
- 🚀 Menor overhead de containers
- 🚀 Recursos dedicados ao PostgreSQL
- 🚀 Otimizações específicas no servidor

#### Confiabilidade
- 🛡️ Alta disponibilidade com failover automático
- 🛡️ Replicação em tempo real
- 🛡️ Backups independentes da aplicação

#### Escalabilidade
- 📈 Fácil adicionar mais réplicas
- 📈 Balanceamento de leitura entre réplicas
- 📈 Crescimento independente do banco

#### Manutenção
- 🔧 Atualizações sem rebuild de containers
- 🔧 Monitoramento dedicado
- 🔧 Gestão centralizada via pgAdmin

### Monitoramento e Saúde

```bash
# Verificar réplicas conectadas (no servidor primário)
sudo -u postgres psql -d testdb -c "SELECT application_name, client_addr, state FROM pg_stat_replication;"

# Verificar lag de replicação (nas réplicas)
sudo -u postgres psql -c "SELECT NOW() - pg_last_xact_replay_timestamp() AS lag;"

# Ver status dos slots de replicação
sudo -u postgres psql -c "SELECT * FROM pg_replication_slots;"
```

### Próximos Passos

1. **Criar as bases de dados** no servidor primário:
```sql
CREATE DATABASE laravel;
CREATE DATABASE plannerate_staging;
CREATE DATABASE plannerate_production;
```

2. **Configurar permissões**:
```sql
GRANT ALL PRIVILEGES ON DATABASE laravel TO replicator;
GRANT ALL PRIVILEGES ON DATABASE plannerate_staging TO replicator;
GRANT ALL PRIVILEGES ON DATABASE plannerate_production TO replicator;
```

3. **Atualizar arquivos .env** de cada ambiente com as configurações acima

4. **Rodar migrations** em cada ambiente:
```bash
# Staging
docker compose -f docker-compose.staging.yml exec app php artisan migrate

# Production
docker compose -f docker-compose.production.yml exec app php artisan migrate
```

## Páginas de Configurações para Tenant, Client e Store (2025)

### Implementação
- ✅ **Atualizado HandleInertiaRequests**: Compartilha `tenant_id`, `client_id` e `store_id` no frontend
- ✅ **Criados 3 controllers separados**:
  - `TenantSettingsController.php`: Gerencia configurações do tenant
  - `ClientSettingsController.php`: Gerencia configurações do cliente
  - `StoreSettingsController.php`: Gerencia configurações da loja
- ✅ **Criados 3 FormRequests**: Validação específica para cada entidade
- ✅ **Adicionadas rotas em `routes/settings.php`**: Rotas separadas para cada página
- ✅ **Criadas 3 páginas Vue**:
  - `TenantInfo.vue`: Formulário completo para edição do tenant (nome, slug, subdomain, domain, email, phone, document, logo, description)
  - `ClientInfo.vue`: Formulário para edição do cliente (nome, slug, CNPJ, email, phone, description, status, endereço completo)
  - `StoreInfo.vue`: Formulário para edição da loja (nome, slug, código, CNPJ, email, phone, description, status, endereço completo)
- ✅ **Atualizado SettingsLayout**: Menu lateral mostra itens condicionais:
  - **Tenant**: Aparece apenas quando está no tenant principal (sem client_id e sem store_id)
  - **Cliente**: Aparece quando há `current_client_id`
  - **Loja**: Aparece quando há `current_store_id`

### Arquivos Criados
- ✅ `app/Http/Controllers/Settings/TenantSettingsController.php`
- ✅ `app/Http/Controllers/Settings/ClientSettingsController.php`
- ✅ `app/Http/Controllers/Settings/StoreSettingsController.php`
- ✅ `app/Http/Requests/Settings/TenantUpdateRequest.php`
- ✅ `app/Http/Requests/Settings/ClientUpdateRequest.php`
- ✅ `app/Http/Requests/Settings/StoreUpdateRequest.php`
- ✅ `resources/js/pages/settings/TenantInfo.vue`
- ✅ `resources/js/pages/settings/ClientInfo.vue`
- ✅ `resources/js/pages/settings/StoreInfo.vue`

### Arquivos Modificados
- ✅ `app/Http/Middleware/HandleInertiaRequests.php` - Adicionado compartilhamento de tenant_id, client_id e store_id
- ✅ `routes/settings.php` - Adicionadas rotas para tenant, client e store settings
- ✅ `resources/js/layouts/settings/Layout.vue` - Adicionados itens condicionais no menu

### Funcionalidades
- **Visualização**: Cada página exibe os dados atuais da entidade
- **Edição**: Formulários completos para atualização de informações
- **Endereço**: Client e Store incluem formulário de endereço completo
- **Validação**: FormRequests garantem validação adequada dos dados
- **Feedback**: Mensagens de sucesso após salvamento
- **Menu Condicional**: Apenas as opções relevantes aparecem no menu lateral

### Benefícios
- **Organização**: Cada entidade tem sua própria página e controller
- **Manutenibilidade**: Código separado facilita manutenção e expansão
- **UX**: Menu mostra apenas opções relevantes ao contexto atual
- **Completude**: Formulário do tenant mais completo conforme solicitado
- **Consistência**: Segue o padrão das outras páginas de settings

## Migração de Notificações para Laravel Echo Vue (2025)

### Objetivo
Migrar todo o sistema de notificações para usar `@laravel/echo-vue` seguindo a documentação oficial, removendo a necessidade de expor o Echo no objeto `window`.

### Modificações Realizadas
- ✅ **Removida exposição do Echo no window**: Removido `window.Echo` e declaração de tipo `Window` do `echo.ts`
- ✅ **Limpeza do app.ts**: Removidos imports e chamadas duplicadas de `configureEcho` (já configurado em `echo.ts`)
- ✅ **Migração do useSyncNotifications**: Refatorado para usar `useEcho` ao invés de `window.Echo`
  - Conecta aos canais privados `sync.user.{userId}` e `sync.client.{clientId}`
  - Usa o composable `useEcho` conforme documentação oficial
  - Mantém toda funcionalidade de notificações de sincronização
- ~~TestNotifications.vue~~ (removido – rotas e controller de teste de notificações obsoletos)

### Arquivos Modificados
- ✅ `resources/js/echo.ts` - Removido `window.Echo` e declaração de tipo
- ✅ `resources/js/app.ts` - Removidos imports duplicados de `configureEcho`
- ✅ `resources/js/composables/useSyncNotifications.ts` - Migrado para `useEcho`

### Benefícios
- **Padrão Oficial**: Segue a documentação oficial do `@laravel/echo-vue`
- **Type Safety**: Melhor suporte TypeScript sem declarações globais
- **Manutenibilidade**: Código mais limpo e organizado
- **Reatividade**: Melhor integração com Vue 3 Composition API
- **Sem Dependências Globais**: Não expõe objetos no window, melhor isolamento

## Configuração do Comando TenantMigrate (2025)

### Objetivo
Configurar o comando `php artisan tenant:migrate` para usar um mapeamento fixo de clientes para seus respectivos bancos de dados, ao invés de buscar dinamicamente nas tabelas.

### Mapeamento de Bancos de Dados
- ✅ **Banco Principal**: `plannerate_staging` (configurado no .env via `DB_DATABASE`)
- ✅ **Mapeamento de Tenants**:
  | Client ID | Banco de Dados |
  |-----------|----------------|
  | `01jym02qk8n1cwdq2hd5drpgsz` | `plannerate_albert` |
  | `01k1xx8evcx6pygzkwax3wrpfm` | `plannerate_franciosi` |
  | `01k7ez88kzrywyejk3xgvskyhj` | `plannerate_bruda` |
  | `01k8vf7dsq3mxm15jxecf3r99s` | `plannerate_michelon` |
  | `01kbr0jmjzk0n5vdjyxd3q3nd0` | `plannerate_cooasgo` |

### Modificações Realizadas
- ✅ **Adicionada constante `TENANT_DATABASES`**: Mapeamento fixo de client_id => database_name
- ✅ **Refatorado método `collectClientDatabases()`**: Usa o mapeamento estático ao invés de buscar dinamicamente
- ✅ **Removido import `Store`**: Não é mais necessário no comando

### Arquivos Modificados
- ✅ `app/Console/Commands/TenantMigrateCommand.php`

### Uso do Comando
```bash
# Executa migrations em todos os bancos (principal + tenants)
php artisan tenant:migrate

# Com força (sem confirmação)
php artisan tenant:migrate --force

# Com seeders
php artisan tenant:migrate --seed

# Fresh (dropa tabelas - USAR COM CUIDADO)
php artisan tenant:migrate --fresh
```

### Benefícios
- **Controle**: Mapeamento fixo garante previsibilidade
- **Performance**: Não precisa consultar o banco para descobrir os tenants
- **Segurança**: Lista de tenants é explícita e versionada no código

## Configuração do Comando ImportLegacyClient (2025)

### Objetivo
Configurar o comando `php artisan import:legacy-client` para usar o mesmo mapeamento fixo de bancos de dados quando o cliente não tiver um banco configurado.

### Lógica de Resolução do Banco
1. **Primeiro**: Verifica se o cliente já tem o campo `database` preenchido → usa esse banco
2. **Segundo**: Se vazio, busca no mapeamento `TENANT_DATABASES` pelo ID do cliente
3. **Terceiro**: Se não estiver no mapeamento e usar `--create-db`, cria com slug do nome

### Modificações Realizadas
- ✅ **Adicionada constante `TENANT_DATABASES`**: Mesmo mapeamento do `TenantMigrateCommand`
- ✅ **Refatorado método `setupClientDatabase()`**: Usa o mapeamento quando o campo `database` está vazio

### Arquivos Modificados
- ✅ `app/Console/Commands/ImportLegacyClientCommand.php`

### Benefícios
- **Consistência**: Mesmo mapeamento usado em todos os comandos de tenant
- **Fallback Inteligente**: Se não estiver no mapeamento, ainda pode criar com `--create-db`
- **Automatização**: Atualiza automaticamente o campo `database` do cliente

## Validação de Dimensões no ImportProductDimensions (2025)

### Objetivo
Atualizar o comando `php artisan import:product-dimensions` para validar dimensões e atualizar o status do produto automaticamente.

### Lógica de Validação
- **Dimensões válidas**: `width > 0 AND height > 0 AND depth > 0` → `status = 'published'`
- **Dimensões inválidas**: Qualquer dimensão = 0 ou nula → `status = 'draft'`

### Modificações Realizadas
- ✅ **Validação de dimensões**: Verifica se width, height e depth são > 0
- ✅ **Atualização automática de status**: Define 'published' ou 'draft' conforme validação
- ✅ **Processa todos os produtos**: Não apenas os 'published', mas todos
- ✅ **Contadores atualizados**: Mostra quantos ficaram published vs draft

### Arquivos Modificados
- ✅ `app/Console/Commands/ImportProductDimensionsCommand.php`

### Uso do Comando
```bash
# Importa dimensões e valida status
php artisan import:product-dimensions

# Para cliente específico
php artisan import:product-dimensions --client=albert

# Modo dry-run (apenas mostra, não altera)
php artisan import:product-dimensions --dry-run
```

### Benefícios
- **Qualidade de Dados**: Produtos sem dimensões válidas ficam como draft
- **Visibilidade**: Contadores mostram claramente o estado dos produtos
- **Automatização**: Validação e status atualizados em um único comando

## Unificação de Rotas de Visualização de Gôndolas (2025)

### Objetivo
Unificar todas as funcionalidades de visualização/exportação de gôndolas em uma única rota.

### Rota Unificada
```
/export/gondola/{gondola}/view
```

### Funcionalidades que apontam para essa rota:
- ✅ **QR Code**: `QRCodeService` gera QR Code apontando para essa rota
- ✅ **Compartilhar**: `ShareQRCodeModal` mostra essa URL para compartilhamento
- ✅ **Visualizar PDF**: `DropdownActions` abre essa rota em nova aba
- ✅ **Baixar PDF**: Mesma página de visualização contém botão de download

### Arquivos Modificados
- ✅ `routes/export.php` - Rota renomeada de `pdf/vue-preview` para `view`
- ✅ `app/Services/QRCode/QRCodeService.php` - URL atualizada
- ✅ `resources/js/components/plannerate/v3/header/ShareQRCodeModal.vue` - URL atualizada
- ✅ `resources/js/components/plannerate/v3/DropdownActions.vue` - Simplificado para usar URL direta

### Benefícios
- **Simplicidade**: Uma única rota para todas as funcionalidades de visualização
- **Consistência**: QR Code, compartilhamento e preview apontam para o mesmo lugar
- **URL mais limpa**: `/export/gondola/{id}/view` ao invés de `/export/gondola/{id}/pdf/vue-preview`
- **Manutenibilidade**: Código mais simples e fácil de manter

Para convenções ao trabalhar com o pacote **callcocam/laravel-raptor** (Sail, componentes Vue, Wayfinder, controllers), use a skill do projeto: `.cursor/skills/laravel-raptor/SKILL.md`.
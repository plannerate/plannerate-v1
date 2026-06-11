# Fase 4 — Relatório de Implementação (2026-06-11)

Branch: `refactor/raptor-plannerate-v2` | Base: `main` @ 29827b2
**Status: implementação concluída nas etapas 0–11. Validação manual (checklist fase-3 §5) pendente — requer browser/usuário.**

## Commits por etapa

| Etapa | Commit | Conteúdo |
|---|---|---|
| 0 | `83d400a` | Destrava suíte Pest (23 `uses()` redundantes) + fix `scoreOrNeutral` no VerticalBlockTest + baseline documentado |
| 1 | `6577311` | 15 enums do domínio → `src/Enums`; código morto removido (`src/Form`, método vazio); manifesto de rotas congelado |
| 2 | `643685e` | Models Editor sobem p/ `src/Models`; 9 models do app migram p/ o pacote; `DeletesGondolaGraph` extraído; fix transação tenant no `cloneWithSlots` |
| 3 | `0d84080` | **21 testes de contrato do save-changes (escritos antes)**; services → `Services/Editor`; camada Repositories absorvida e removida |
| 4 | `073a033` | Análises → `Services/Analysis`; print/QR → `Services/Export` |
| 5 | `aa477db` | **AutoPlanograma inteiro → `src/AutoPlanogram`** (59 arquivos); provider DI fundido; 6 transações movidas p/ conexão tenant (eram ineficazes na default) |
| 6 | `4f8c6cd` | 5 controllers + rotas → pacote (`routes/generation.php`, `routes/templates.php`); listener movido; **manifesto de rotas 100% idêntico** |
| 7 | `fb64c3b` | Actions wayfinder movidas p/ novos FQCNs (manuais preservadas); 9 componentes atualizados |
| 9 | `e6a6c82` | DnD centralizado em `dnd/transfer.ts` (MIME types + payloads tipados) |
| 10 | `ce44853` | `PlanogramEditor.vue` unificado (manual/generated); `planogram-templates/` → pacote com alias |

## Resultado da validação automática (Etapa 11)

| Verificação | Resultado |
|---|---|
| Manifesto de rotas vs baseline | **358 = 358, zero diferença** (URI/nome/método/middleware) |
| Referências órfãs a namespaces antigos | 0 (1 menção em docblock histórico, intencional) |
| `tests/Unit/Services/AutoPlanogram` (s/ CompositeScorer) | **156 passed** ✅ |
| `PlanogramChangeServiceTest` (novo, 21 contratos do save-changes) | **21 passed** ✅ |
| `AutoPlanogramRegressionTest` | **15 passed** ✅ |
| `AutoPlanogramPlacementTest` / `GondolaSlotOverrideTest` / `PlanogramSettingsTest` / `QRCodeServiceTest` / `GondolaProductImagesUpdatedTest` | **2/5/11/2/1 passed** ✅ |
| `PlannerateEditorModelConnectionTest` (arquitetura) | **17 passed** ✅ (2 violações reais corrigidas: transações na conexão errada) |
| Build Vite + types:check | verde; **zero erros de tipo novos** (77 pré-existentes; 8 duplicados por symlink) |
| Pré-existentes vermelhos (inalterados vs baseline) | Suggestions 2, Packages 2, AutoPlanogram dir 11, VerticalBlock 4, UploadImageRoute 2 (stale: espera domínio `{subdomain}` removido antes da branch), crashers CompositeScorer/Scoring 2 |

**Zero regressão: todo teste verde no baseline continua verde; todo vermelho é pré-existente e idêntico.**

## O que mudou de lugar (resumo)

- **Pacote agora contém:** 15 enums, 24 models (15 Editor + 9 de templates/geração), todos os services (Editor/Analysis/Export), o AutoPlanograma completo, 7 controllers novos (Generation + Templates), o listener de rejeitados, 5 arquivos de rotas, o wizard de templates Vue e o editor unificado.
- **App ficou com:** tenancy/RBAC/traits, `Category`/`Product`/`Gondola`(espelho), Settings controllers (importando models do pacote), comandos de console, navegação, demais domínios.
- **`app/Services/AutoPlanogram` não existe mais** — vive só no pacote (objetivo central cumprido).

## Correções reais embutidas (além de mover código)

1. Suíte Pest inteira estava **incapaz de carregar** (uses() duplicado) — destravada.
2. **6 transações** de escrita tenant abriam na conexão default (onde nada era escrito) — `AutoPlanogramService`, `VisualReorder`, `ExposureRedistribute`, `TemplateSlotService`, `TemplateImportService`, `AutoTemplateSynthesizer` + `PlanogramSubtemplate::cloneWithSlots` + `swapProduct` do controller.
3. Cascade da gôndola extraído para concern testável.
4. `ShelfLevelPreferenceFactory` sem `$model` (quebraria com namespace não-App).

## Desvios do plano fase-3 (com justificativa)

| Item | Status | Razão |
|---|---|---|
| D4 — estado normalizado do editor (Etapa 8) | **adiado** | sem runner JS no projeto (vitest = mudança de dependência, exige aprovação); cache parcial de lookups criaria referências stale com a reatividade por re-spread — pior que o O(n) atual. Pré-requisito: aprovar vitest + testes de browser |
| Divisão interna do `AbcAnalysisService` | **adiado** | sem fixtures de paridade input→output, o risco de regressão silenciosa supera o ganho |
| Divisão dos componentes gigantes (TransferSectionDialog 1019 l etc.) | **adiado** | mesma lógica: sem verificação comportamental automatizada, mover por mover é risco |
| Teclado dividido por contexto (Etapa 9 parcial) | **adiado** | usePlanogramKeyboard funciona e está coberto pelo fluxo manual; divisão é cosmética com risco de listener duplo |
| Browser tests Pest 4 (fase-3 §6.3) | **pendente** | exigem app rodando + tenant autenticado; executar na validação manual |

## Validação manual necessária (antes do merge)

Rodar o checklist completo da fase-3 §5 no browser (tenant `albert`):
1. Editor manual: drag & drop produto/segmento/prateleira, Ctrl+copy, duplicar seção/prateleira, teclado (setas/números/Ctrl+D/I/Z/Y/S), auto-save, undo/redo
2. Geração: template + automático, regerar/redistribuir/reordenar, overrides, rejeitados
3. Templates: wizard completo (CRUD, slots, critérios visuais drag-and-drop, import/export XLSX)
4. Análises ABC/estoque/papel + exports; PDF/PNG/QR; **link público de share**
5. `composer dump-autoload` + `docker compose exec php php artisan optimize:clear` no ambiente de teste

## Fase 5 (após validação e merge — NÃO executada)

- Limpeza do app: re-export thin? (não foi necessário — referências foram atualizadas na origem; sobram apenas: conferir `app/Models/{Gondola,Planogram,Sale,Category,Product}` espelhos × models do pacote para definição de dono único)
- Plano de remoção destrutiva exige aprovação explícita (regra de ouro 9)

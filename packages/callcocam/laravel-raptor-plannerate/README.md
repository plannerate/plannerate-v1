# Laravel Raptor Plannerate (Private)

Pacote privado e dono completo do **domínio planograma** do Plannerate: editor visual
(frontend Vue + API), templates, geração automática (AutoPlanograma), análises de
performance, exportação e regras de produto.

Instalado via path repository (`composer.json` da raiz) — o código vive em
`packages/callcocam/laravel-raptor-plannerate` e é symlinkado em `vendor/`.

> Reconstruído em 2026-06 (branch `refactor/raptor-plannerate-v2`). Histórico completo
> da refatoração em `docs/refatoracao-raptor-plannerate/` na raiz do projeto.

## Arquitetura (src/)

```
src/
├── LaravelRaptorPlannerateServiceProvider.php  rotas, policies, listeners, bindings DI
├── AutoPlanogram/        motor de geração automática (pipeline completo)
│   ├── DTO/  Scoring/  Placement/  Synthesis/  Template/  Validation/
├── Enums/                15 enums do domínio (CategoryRole, ZonePriority, FlowDirection...)
├── Models/               todos os models do domínio (Gondola, Section, Shelf, Segment,
│                         Layer, PlanogramTemplate, Subtemplate, TemplateSlot,
│                         RejectedProduct, ProductRule, GondolaSlotOverride,
│                         ScoringWeights, ShelfLevelPreference, AdjacencyRule...)
├── Http/Controllers/
│   ├── Editor/           CRUD físico + save-changes (delta) + payload do editor
│   ├── Generation/       AutoPlanogram, overrides por gôndola, regras de produto
│   ├── Templates/        CRUD de templates/slots/subtemplates, import/export, review
│   ├── Analysis/         ABC, estoque alvo, papel + export CSV
│   ├── Export/           PDF, QR Code, link público de share
│   └── Api/              detalhes/imagens de produto
├── Services/
│   ├── Editor/           GondolaService, Section/Shelf/Segment/LayerService,
│   │                     PlanogramChangeService (aplica deltas), GondolaPayloadService
│   ├── Analysis/         AbcAnalysisService, TargetStockService, PaperAnalysisService
│   └── Export/           GondolaPrintService, QRCodeService
├── Listeners/  Events/  Jobs/  Policies/  Concerns/
```

## Frontend (resources/js/)

Fonte no pacote, build no app host (aliases do Vite/tsconfig apontam para `vendor/`):

- `components/plannerate/` — editor completo: `PlanogramEditor.vue` (unificado,
  prop `mode: manual|generated`), canvas (Section/Shelf/Segment/Layer), header/toolbar,
  sidebars (produtos, propriedades, geração), análises, impressão PDF, modais
- `components/planogram-templates/` — wizard de templates (slots, critérios visuais
  drag-and-drop, review)
- `composables/plannerate/` — estado do editor (singleton refs), undo/redo por snapshots,
  auto-save delta, **`dnd/transfer.ts`** (contrato central de drag & drop), teclado,
  geometria, análises
- Aliases: `@/components/plannerate`, `@/components/planogram-templates`,
  `@/composables/plannerate`, `@/types/planogram`, `@plannerate`

## Rotas (routes/)

| Arquivo | Conteúdo | Middleware |
|---|---|---|
| `editor.php` | API do editor (CRUD, save-changes, análises, imagens) | web, auth, NeedsTenant (+tenant.client.redirect na parte de edição) |
| `generation.php` | geração automática, rejeitados, reorder/redistribute, overrides | web, auth, NeedsTenant (sem client.redirect) |
| `templates.php` | planogram-templates/* + regras de produto | web, auth, NeedsTenant, tenant.client.redirect |
| `export.php` | PDF, QR, **share público (sem auth)** | auth (exceto share) |
| `plannerate.php` | visualização tenant de gôndola/seção | auth, tenant |

## Contratos importantes

- **Save-changes (delta):** o frontend envia mudanças com ULIDs gerados no cliente;
  `PlanogramChangeService` roteia por entityType. Coberto por
  `tests/Feature/Plannerate/PlanogramChangeServiceTest.php` (21 contratos).
- **shelf_position é coordenada em cm** (0 = topo), não índice — o engine calcula vãos
  verticais reais e rejeita por altura.
- Vínculo slot → produto é `category_id` (FK); produto `status=draft` nunca entra.
- Models tenant usam `BelongsToTenant` (do app) — nunca passar `tenant_id` manualmente.

## Migrations (safe mode)

Migrations versionadas em `database/migrations/clients/*`. Para sincronizar no app host:

```bash
docker compose exec php php artisan plannerate:migrations:sync   # --dry-run | --force | --target=...
docker compose exec php php artisan tenants:artisan "migrate --database=tenant"
```

## Testes

A suíte vive no projeto raiz (`tests/`): `tests/Unit/Services/AutoPlanogram/` (165),
`tests/Feature/AutoPlanogram*` (E2E do modo automático), `tests/Feature/Plannerate/`
(save-changes). Rodar: `docker compose exec php php artisan test --compact <path>`.

## Documentação de domínio

Em [`docs/`](docs/): fluxo do planograma automático, comparativo fluxo×implementação,
análises ABC/BCG/estoque-alvo e min-facings/overflow/pruning.

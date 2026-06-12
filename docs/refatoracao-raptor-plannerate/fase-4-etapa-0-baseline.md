# Fase 4 — Etapa 0: Baseline de testes (2026-06-11)

Branch: `refactor/raptor-plannerate-v2` (criada de `main`/`dev` @ 29827b2).

## Estado da suíte ANTES desta branch

A suíte completa (`php artisan test`) **não rodava na main**: crashava no carregamento do Pest
("Test case [Tests\TestCase] can not be used...") por `uses(TestCase::class)` redundante em
23 arquivos — o `tests/Pest.php` já vincula `TestCase` + `RefreshDatabase` a `Unit` e `Feature`.

## Correções de infraestrutura de teste feitas nesta etapa (sem tocar em lógica)

1. Removidos os `uses(TestCase::class)` redundantes (23 arquivos) + imports órfãos.
2. `AutoPlanogramVerticalBlockTest`: classe anônima ganhou `scoreOrNeutral()` (método adicionado
   à interface `ProductScorerInterface` depois que o teste foi escrito — FatalException no load).

## Crashers pré-existentes (exit 2, sem output) — EXCLUÍDOS de qualquer run

- `tests/Unit/Services/AutoPlanogram/Scoring/CompositeScorerTest.php`
- `tests/Feature/AutoPlanogramScoringTest.php`
- Ambos carregam o `CompositeScorer` (já documentado na memória do projeto).
- Exclusão: `--filter '^(?!.*(CompositeScorer|AutoPlanogramScoring)).*$'`

## Baseline do DOMÍNIO (planograma) — referência de regressão

| Suíte | Resultado |
|---|---|
| `tests/Unit/Services/AutoPlanogram` (sem CompositeScorer) | **156 passed** ✅ |
| `tests/Feature/AutoPlanogramRegressionTest` | **15 passed** ✅ |
| `tests/Feature/AutoPlanogramPlacementTest` | **2 passed** ✅ |
| `tests/Feature/GondolaSlotOverrideTest` | **5 passed** ✅ |
| `tests/Feature/Settings/PlanogramSettingsTest` | **11 passed** ✅ |
| `tests/Feature/AutoPlanogram/` (dir) | 18 passed, **11 failed (pré-existentes)** |
| `tests/Feature/AutoPlanogramSuggestionsTest` | 5 passed, **2 failed (pré-existentes)** |
| `tests/Feature/AutoPlanogramVerticalBlockTest` | **4 failed (pré-existentes)** — teste antecede a exigência de categoria real na síntese (`Categoria selecionada não encontrada` / `no such table: categories`) |
| `tests/Unit/Packages/PlannerateProductImageControllerTest` | 2 passed, **2 failed (pré-existentes)** — teste espera assinatura antiga de `uploadImage` (3 params com `subdomain`; código atual tem 2) |

Falhas pré-existentes em `tests/Feature/AutoPlanogram/`: `AutoTemplateSynthesizerTest` (4× QueryException),
`AutomaticEndToEndTest` (casos 5, 8, 10...), mesmas famílias de causa (fixtures sem categorias/tabelas tenant).

## Suíte completa (fora do domínio — contexto)

`php artisan test --filter '^(?!.*(CompositeScorer|AutoPlanogramScoring)).*$'`:
**339 failed, 330 passed, 2 skipped**. A grande maioria das falhas é alheia ao planograma e
pré-existente (ex.: `tests/Unit/Services/Integrations/*` testa classes que não existem mais, como
`App\Services\Integrations\Http\IntegrationHttpClient` — 21 falhas "Class not found").
Esses testes **nunca rodaram** (a suíte não carregava). Não fazem parte do escopo da refatoração;
o critério de aceite das etapas é o **baseline do domínio** acima.

## Critério de regressão para as próximas etapas

A cada etapa: os testes verdes do domínio continuam verdes; os pré-existentes vermelhos podem
continuar vermelhos (ou serem consertados quando a etapa tocar no módulo correspondente — ex.:
VerticalBlock na Etapa 5, PlannerateProductImageController na Etapa 6).

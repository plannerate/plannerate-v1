# Plano de Implementação — Gôndola Precisa e Verdadeira

> **Status:** Fase 0 ✅ COMMITADA (887621cf) e validada no browser.
> Fase 1 ✅ COMMITADA (d7985ef8). Fase 2 ✅ COMMITADA (ba7e5f11).
> Fases 3-6 pendentes — **mas o plano delas precisa ser revisto:** a medição da
> Fase 2 derrubou a premissa da auditoria sobre onde a ocupação se perde.
> Ver o quadro *"O que a medição mostrou"* na Fase 2.
> A execução de cada fase precisa de aprovação explícita antes de começar.

## Princípio orientador (confirmado com o usuário)

**Precisão > velocidade.** A geração automática pode demorar mais — e pode
até ser refeita internamente (loop de convergência) — desde que:

1. Rode **em fila** (não bloqueie o request HTTP nem trave a UI);
2. **Avise o usuário quando terminar** (notificação, como já existe para
   relatórios);
3. **Fique salva para consulta futura** (comparar execuções, reabrir o
   relatório, auditar o que mudou entre uma geração e outra).

Isso muda a arquitetura atual (síncrona, resultado só na resposta do
request) para o mesmo padrão **já implementado e em produção** para
relatórios de gôndola — ver [`docs/relatorios-em-fila.md`](../relatorios-em-fila.md).
Reaproveitamos ao máximo, trocando "arquivo para baixar" por "relatório de
execução para consultar".

## Infraestrutura já pronta que vamos reusar (zero esforço extra)

Da mesma forma que os relatórios reaproveitaram infra existente, a geração
automática reaproveita:

- **Filas/Horizon**: `config/queue.php` (driver redis), `config/horizon.php`
  (supervisor `default`, timeout 660s — provavelmente suficiente; criar
  supervisor dedicado `autoplanogram` só se o volume/tempo justificar).
- **Multi-tenancy em jobs**: `Spatie\Multitenancy\Jobs\TenantAware` restaura
  o tenant automaticamente antes do `handle()`.
- **Notificação (database + broadcast)**: `App\Notifications\AppNotification`
  — já `ShouldQueue`, canais `['database', 'broadcast']`, exibida em
  `NotificationsDropdown.vue` via Reverb/Echo em tempo real.
- **Padrão de Job**: o mesmo esqueleto de `GenerateGondolaReportJob`
  (`ShouldQueue`, `TenantAware`, `tries`, `timeout`, `handle()`, `failed()`).

## Arquitetura proposta

```
AutoGeneratePlanogramForm (router.post)
   │
   ▼
AutoPlanogramController@generate            (contexto tenant, auth)
   │  1. cria PlanogramGenerationRun (status=queued, config snapshot)
   │  2. dispatch job
   │  3. back() com flash "na fila" (imediato, sem esperar)
   ▼
GenerateAutoPlanogramJob (ShouldQueue, TenantAware)   fila: default
   │  1. AutoGenerationRunner->run(...)  (mesma lógica de negócio, evoluindo nas Fases 1-3)
   │  2. atualiza PlanogramGenerationRun: status, métricas de ocupação,
   │     iterações, convergiu?, validation_report, capacity_report, duração
   │  3. $user->notify(new AppNotification(actionUrl: relatório da execução))
   ▼
AppNotification → [database]  grava em notifications (tenant)
              → [broadcast]  Reverb → Echo → NotificationsDropdown (sino)
   │
   ▼
Usuário clica "Ver relatório" → abre o PlanogramGenerationRun persistido
   (também consultável depois, em qualquer momento, via histórico da gôndola)
```

## Por que isso resolve o pedido de "salvar para futuras consultas"

Hoje o resultado de uma geração (`capacity_report`, `validation_report`,
sugestões, slot analysis) só existe como **flash Inertia** — desaparece
depois do primeiro render, não é possível voltar e ver "o que aconteceu na
geração de ontem". A nova tabela `planogram_generation_runs` persiste isso
permanentemente por gôndola, o que também é pré-requisito técnico para medir
se as fases seguintes (2 e 3) realmente melhoram a ocupação ao longo do
tempo.

---

## Fase 0 — Fundação assíncrona (fila + notificação + histórico) ✅ IMPLEMENTADA

**Objetivo:** mover a geração do request síncrono para fila, sem tocar em
nenhuma lógica de posicionamento ainda. Risco baixíssimo — é só transporte.

### O que foi entregue (2026-07-12)

| Arquivo | Status |
|---|---|
| `database/migrations/2026_07_12_180000_create_planogram_generation_runs_table.php` | novo — aplicado nos 6 tenants |
| `src/Enums/GenerationRunStatus.php` | novo (queued/running/completed/failed) |
| `src/Models/PlanogramGenerationRun.php` | novo (tenant-scoped, ULID, SoftDeletes) |
| `src/Services/Generation/GenerationReportBuilder.php` | novo — `capacity_report` extraído do controller (era inline) + métricas de ocupação |
| `src/Jobs/GenerateAutoPlanogramJob.php` | novo (`ShouldQueue`, `TenantAware`, fila `default`, `tries=1`, `timeout=600`) |
| `src/Http/Controllers/Generation/PlanogramGenerationRunController.php` | novo (`index`/`show`/`latest`/`pending`) |
| `src/Http/Controllers/Generation/AutoPlanogramController.php` | `generate()`/`regenerateAuto()` agora criam o run + despacham o job (via `queueGeneration()` privado); ~110 linhas de montagem de relatório removidas |
| `routes/generation.php` | 4 rotas novas de `generation-runs` |
| `resources/js/composables/plannerate/generation/useGenerationRun.ts` | novo — busca a última execução, faz polling enquanto pendente, recarrega ao concluir |
| `resources/js/pages/tenant/editor/Plannerate.vue` | banner/validação hidratados da execução persistida (flash mantido como fallback) + estado "gerando..." |
| `lang/pt_BR/plannerate/generation.php` | novo namespace de traduções |
| `tests/Feature/Tenant/AutoPlanogramQueueTest.php` | novo — **5 testes verdes** |

**Achado importante durante a implementação:** existiam **DOIS** caminhos de
geração, não um. Além do `AutoPlanogramController` (botão "gerar" no editor), o
`GondolaController::store` gerava **sincronamente** ao criar a gôndola já em modo
automático. Por isso foi criado o `GenerationQueueDispatcher` como fonte única —
hoje o **único** chamador do `AutoGenerationRunner` é o job (verificado por grep
em todo o pacote + app). Sem isso, metade das gerações continuaria síncrona.

**Verificações feitas:** Pint ✅; 6 testes novos ✅ (um deles cobrindo justamente
o caminho da criação, para não regredir); suíte `AutoPlanogram` 274 passed /
2 failed — as 2 falhas (`AutoPlanogramDimensionsReportCommandTest`) são
**pré-existentes**, confirmado reproduzindo com as mudanças em `git stash`; build
frontend ✅; `vue-tsc` limpo nos arquivos tocados; Horizon + Reverb reiniciados.

**Gotcha de teste registrado:** em teste tenant `:memory:`, `makeCurrent()`
reconecta a conexão `tenant` — construir o schema **depois** de `makeCurrent()`,
nunca antes, ou as tabelas somem.

**Validação no browser:** ✅ confirmada pelo usuário.
**Commit:** ✅ `887621cf`.

### Passos originais planejados

| Passo | Arquivo | Ação |
|---|---|---|
| 0.1 | `database/migrations/tenant/..._create_planogram_generation_runs_table.php` | **nova migration tenant** — campos: `id` (ulid), `tenant_id`, `gondola_id`, `planogram_id`, `user_id`, `status` (enum queued/running/completed/failed), `config_snapshot` (json), `started_at`, `finished_at`, `duration_ms`, `occupancy_avg`, `occupancy_min`, `occupancy_max`, `iterations_run`, `converged` (bool nullable), `validation_report` (json), `capacity_report` (json), `synth_template_id` (nullable), `error_message` (nullable) |
| 0.2 | `src/Models/PlanogramGenerationRun.php` | **novo model** — `BelongsToTenant`, `UsesTenantConnection`, `HasUlids`, `SoftDeletes` (padrão de model tenant do projeto) |
| 0.3 | `src/Jobs/GenerateAutoPlanogramJob.php` | **novo job** — `ShouldQueue`, `TenantAware`; `handle()` chama `AutoGenerationRunner->run()`, atualiza o run record, notifica; `failed()` marca `status=failed` + notifica erro |
| 0.4 | `src/Http/Controllers/Generation/AutoPlanogramController.php` | `generate()`/`regenerateAuto()` passam a criar o run (`status=queued`) e despachar o job — devolvem `back()` com flash "na fila" **imediatamente**, sem esperar o resultado |
| 0.5 | novo controller/rotas `.../generation-runs` | `GET .../gondolas/{gondola}/generation-runs` (histórico, lista) e `GET .../generation-runs/{run}` (detalhe — o que hoje é `capacity_report`/`validation_report`) |
| 0.6 | Frontend: modal de resultado da geração | passa a buscar o relatório persistido (via novo endpoint) em vez de ler do flash síncrono; toast imediato "Gôndola sendo gerada, você será avisado quando terminar" |
| 0.7 | Traduções | `app.messages.autoplanogram.queued` / `.failed` |

**Testes:**
```php
it('enfileira a geração em vez de rodar sincronamente', function () {
    Queue::fake();
    $this->post(route('tenant.autoplanogram.generate', $gondola), [...])
        ->assertRedirect();
    Queue::assertPushed(GenerateAutoPlanogramJob::class);
});

it('job roda a geração, persiste o run e notifica o usuário', function () {
    Notification::fake();
    (new GenerateAutoPlanogramJob($gondolaId, $planogramId, $configArray, $userId, $tenantId, $runId))
        ->handle(app(AutoGenerationRunner::class));
    expect(PlanogramGenerationRun::find($runId)->status)->toBe('completed');
    Notification::assertSentTo($user, AppNotification::class);
});
```
Rodar isolado: `docker compose exec php php artisan test --compact --filter=AutoPlanogramQueue`.

**Critério de pronto:** geração automática/template roda 100% via fila,
resultado consultável no novo endpoint, notificação chega no sino com link
para o relatório. Zero mudança na lógica de posicionamento — os 165+ testes
existentes de `AutoPlanogram*` continuam verdes sem alteração.

**Migration:** lembrar de rodar
`docker compose exec php php artisan tenants:artisan "migrate --database=tenant"`
(nunca `migrate:fresh`).

---

## Fase 1 — Correções rápidas de precisão ✅ IMPLEMENTADA

### O que foi entregue (2026-07-12)

**Decisão de escopo:** o plano falava em "precisão em mm". As colunas
`segments.position` / `segments.width` são **inteiras (cm)** e o editor,
drag & drop e PDF dependem disso — migrar para decimal seria invasivo e
arriscado. A solução equivalente, sem tocar no schema: **toda a aritmética de
encaixe roda em float exato** e o arredondamento acontece **só na persistência**,
por **soma de prefixos** (arredondando os PONTOS de início/fim, não cada largura
isolada). Resultado: segmentos contíguos por construção, sem erro acumulado.

| Passo | Entregue | Onde |
|---|---|---|
| 1.1 | `PlacementMath` (novo): fonte única da aritmética de encaixe — `fits()` com tolerância de float e `segmentBounds()` com soma de prefixos. Removidos os arredondamentos por item nos **4** pontos de empacotamento | `Placement/PlacementMath.php` (novo), `TemplatePlacementEngine.php` (principal, overflow e grid), `GreedyShelfPlacer.php`, `DTO/ShelfLayoutDTO.php` |
| 1.2 | Folga configurável entre produtos, cobrada **só entre** produtos (nunca antes do 1º, nunca sobrando no fim). Default `0.0` = comportamento anterior | `config/plannerate.php` (`auto_planogram.placement.product_spacing_cm`), `PlacementMath::gapBefore()`, `distributeInShelf()` |
| 1.3 | `targetOccupancyRate` deixa de ser código morto: vira config e o relatório passa a informar `ocupacao_alvo`, `ocupacao_media` e `prateleiras_abaixo_do_alvo` | `config/plannerate.php` (`target_occupancy_rate`), `GenerationReportBuilder` |
| 1.4 | `ProductWidthResolver` rastreia os produtos que entraram com largura **chutada** (inclusive o caso `null`, que antes passava totalmente mudo) e eles vão para o relatório da geração (`produtos_sem_dimensao_confiavel`) | `ProductWidthResolver.php`, `AutoGenerationRunner` (chama `reset()`), `GenerationReportBuilder` |

### Bugs reais encontrados e corrigidos no caminho

1. **`placeOverflow` arredondava a largura UNITÁRIA antes de multiplicar pelas
   frentes** — um produto de 3,4cm × 5 frentes "ocupava" 15cm em vez de 17cm, e
   o motor o encaixava em prateleiras onde ele **não cabia de verdade**.
2. **O grid somava larguras arredondadas ao calcular o ocupado**, então a
   expansão de frentes decidia em cima de um espaço livre errado.
3. **Produto sem largura cadastrada (`null`) caía no fallback de 10cm em
   silêncio total** — sem log, sem relatório. É a causa silenciosa nº1 de
   gôndola que "não fecha", e agora aparece nomeada no relatório.

### Verificações

Pint ✅; **8 testes novos** (`PlacementMathTest` + rastreamento no
`ProductWidthResolverTest`); suíte `AutoPlanogram` **287 passed / 2 failed** —
as 2 falhas (`AutoPlanogramDimensionsReportCommandTest`) são **pré-existentes**;
suíte de placement **209 passed / 3 failed** — as 3 também **pré-existentes**,
ambas confirmadas com `git stash` (baseline: 201 passed / **as mesmas 3
failed**). Build ✅.

**Nota:** com o espaçamento em 0 (default), a Fase 1 não muda o *resultado* das
gôndolas existentes — ela corrige a **base de cálculo** (encaixe exato, sem erro
acumulado) sobre a qual a Fase 2 vai construir o empacotador exato, e torna
visível o dado ruim de cadastro que sabotava a precisão em silêncio.

---

## Fase 2 — Motor de empacotamento exato por prateleira ✅ IMPLEMENTADA (ba7e5f11)

> ⚠️ **Esta fase entregou o que prometia, mas a medição derrubou a premissa do
> plano.** Ela estava descrita aqui como "o conserto central". Não é. Leia o
> quadro *"O que a medição mostrou"* abaixo antes de planejar as fases seguintes.

### O que foi entregue

| Passo | O que mudou | Onde |
|---|---|---|
| 2.1 | `Placement/ShelfKnapsackPacker.php` — bounded knapsack por DP sobre a prateleira inteira. Frentes = variável livre em `[min, max]`; rejeitados por espaço voltam a concorrer. Trabalha em mm inteiros (arredondando o custo **para cima**, nunca prometendo encaixe que a prateleira não comporta) e devolve `null` para o motor guloso quando não consegue resolver | novo |
| 2.2 | `distributeInShelf()` chama o packer entre o first-fit e o `expandFacings`. O ranking de candidatos a montante fica **intocado** — o packer recebe a ordem pronta | `TemplatePlacementEngine.php` |
| 2.3 | Guardas de custo: acima de 60 candidatos ou 500cm de prateleira o DP aborta e o guloso segue valendo | `ShelfKnapsackPacker.php` |
| 2.4 | Interruptor `plannerate.auto_planogram.placement.packer` = `knapsack` \| `greedy` (env `PLANNERATE_SHELF_PACKER`) — restaura o motor antigo sem deploy | `config/plannerate.php` |

### Garantia de não-regressão (por construção, não por teste)

Tudo que o first-fit já colocaria entra no modelo como **obrigatório**. Logo a
solução do motor antigo é sempre viável no espaço de busca do DP — e como o DP
devolve a de maior valor, o resultado **empata ou melhora, nunca perde um SKU**.
Um teste de propriedade sobre 60 prateleiras sortidas confirma
(`empacotador nunca ocupa menos que o guloso`).

### Modelo de valor: três prioridades, nesta ordem

1. **Variedade** — estar na prateleira domina tudo (5000 contra um teto de ~690).
2. **Ocupação** — fechar a gôndola é objetivo de 1ª classe, não efeito colateral.
3. **Profundidade** — série harmônica: frentes têm retorno decrescente.

A prioridade ② não estava no plano e **foi um teste que a exigiu**: sem ela o DP
maximiza só valor comercial e chega a *preferir deixar 1cm vazio* se isso
concentrar mais frentes no produto melhor ranqueado. Estava certo pelo modelo —
o modelo é que não refletia o objetivo do projeto.

### Bug real corrigido de quebra

O fallback `reduce_facings` montava seus segmentos **fora** da esteira do cursor e
os posicionava a partir de `x=0` — **sobrepondo os produtos já colocados** — e
ainda escapava do espelhamento direita→esquerda. Agora devolve itens (não
segmentos) e entra na mesma esteira dos demais.

### O que a medição mostrou (e por que o plano precisa mudar)

Benchmark em prateleiras sortidas (larguras de 4 a 28cm, 4-12 SKUs, `max_facings`
de 3 a 12), guloso × empacotador, medindo ocupação **do slot**:

| `max_facings` | guloso | empacotador | ganho |
|---|---|---|---|
| 3 | 97,3% | 98,2% | +0,9 pp |
| 4 | 97,0% | 98,0% | +1,0 pp |
| 6 | 97,1% | 98,1% | +1,0 pp |
| 12 | 97,1% | 98,2% | +1,0 pp |

**O guloso já ocupava ~97% do espaço que lhe é dado.** O empacotador entrega o
encaixe exato (+1pp) e a garantia de não-regressão — ganho real, mas pequeno.

Isso **contradiz a auditoria** (`01-auditoria-codigo-atual.md`), que apontava o
first-fit como causa-raiz da ocupação de 70-80%. A razão do erro, agora clara:
o first-fit coloca todo mundo com a frente **mínima antes** de expandir qualquer
frente. Ou seja, ele já é "variedade primeiro", e o momento em que ele rejeita um
produto é justamente quando a prateleira está mais vazia. Um produto que ele
rejeita **não caberia de jeito nenhum** — nem o DP consegue recuperá-lo (só com
`reduce_facings`, que baixa o piso para 1 frente).

**Conclusão: a perda grande NÃO está dentro da prateleira. Está a montante** —
em quanto espaço cada slot recebe e em quanta demanda a categoria tem para
preencher:

- **Tetos de frentes** (`max_facings`, teto por estoque alvo): se a categoria tem
  4 SKUs de 8cm e teto de 3 frentes, a demanda máxima é 96cm. Numa prateleira de
  130cm, **34cm ficam vazios e nenhum empacotador do mundo os preenche.**
- **Plano de slots** (`SlotPlanBuilder`): categoria espalhada por mais prateleiras
  do que precisa → cada uma fica rala.
- **`SHELF_FILL_RATE_ESTIMATE = 0.75`** (`AutoTemplateSynthesisOrchestrator:50`):
  usado em `computeNumModules`, ele infla o número de módulos em 33%. Mais gôndola
  para o mesmo mix = ocupação menor. **A profecia se autorrealiza:** o hack existe
  porque a ocupação é baixa, e a ocupação é baixa porque o hack dá gôndola demais.
  (Só afeta gôndolas **novas**; regeneração usa a estrutura existente.)
- **Reserva de micro-categoria** (`MICRO_CATEGORY_WIDTH_THRESHOLD = 0.35`): 35% da
  prateleira compartilhada é segurado para a micro-categoria. Se ela não usar,
  fica vazio.

**Antes de implementar a Fase 3, medir uma gôndola real.** A Fase 0 já persiste
`capacity_report` com `percentual_uso` por slot em `planogram_generation_runs` —
é só gerar uma gôndola de verdade e ler onde o vão realmente está, em vez de
continuar deduzindo. Sem esse dado, a Fase 3 corre o risco de ser mais uma fase
consertando o lugar errado.

---

## Fase 3 — Loop de convergência do overflow

| Passo | O que muda | Onde |
|---|---|---|
| 3.1 | Transformar `placeOverflow()` de passe único em **loop**: tenta encaixar rejeitados (agora de **qualquer** categoria com espaço, não só a mesma) → recheca se houve melhoria → repete até não melhorar mais OU bater limite de iterações/tempo | `TemplatePlacementEngine.php:473-736` |
| 3.2 | Expor limite como config: `maxOverflowIterations` (default razoável, ex. 5) e/ou `overflowTimeBudgetMs` | `PlacementSettings.php` |
| 3.3 | Persistir `iterations_run` e `converged` no `PlanogramGenerationRun` (campos já criados na Fase 0) — informação de auditoria explicitamente pedida pelo usuário | `GenerateAutoPlanogramJob.php` |

**Critério de pronto:** produtos antes presos atrás de outra categoria cheia
agora são reconsiderados quando espaço libera em qualquer prateleira; testes
cobrindo cenário de convergência multi-iteração e o caso de "bateu o limite
sem convergir" (deve terminar graciosamente, não travar).

---

## Fase 4 — Observabilidade / histórico de execuções

| Passo | O que muda |
|---|---|
| 4.1 | Tela/endpoint de histórico por gôndola: lista de execuções com ocupação atingida, duração, convergiu?, link pro relatório completo — a peça que fecha "salvar pra futuras consultas" |
| 4.2 | (Opcional) Enquanto o usuário está na tela do editor aguardando, escutar broadcast (`useEcho`) do evento de conclusão para auto-abrir o relatório sem precisar ir ao sino |
| 4.3 | Remover/reduzir o hack `SHELF_FILL_RATE_ESTIMATE = 0.75` em `AutoTemplateSynthesisOrchestrator.php:39-50` agora que o packer real atinge ocupação alta — recalibrar a estimativa de número de módulos para refletir a precisão real |

---

## Fase 5 (opcional) — Reotimização profunda

Só entra em jogo se a Fase 2/3 não bastar para casos extremos, ou como
diferencial de produto ("otimizar com precisão máxima").

| Passo | O que muda |
|---|---|
| 5.1 | Modo opcional, disparado explicitamente (botão dedicado) ou como batch noturno: usa GA ou o ciclo alternado RS↔MILP (padrão da patente Oracle) com orçamento de tempo maior (minutos) — não roda no caminho padrão de geração |
| 5.2 | Curva de retornos decrescentes por produto (power-law, achado da patente Walmart) para tornar a expansão de frentes mais inteligente sobre qual produto merece frente extra, em vez de round-robin por score |

---

## Fase 6 — Validação e rollout

1. Validação manual no browser (Horizon rodando) comparando gôndolas
   antes/depois lado a lado.
2. Rollout: pode ser direto se os testes automatizados (ocupação medida nos
   fixtures) derem confiança suficiente; considerar feature flag por tenant
   só se quiser cautela extra num primeiro tenant piloto.
3. Atualizar esta pasta de docs com os números reais medidos (ocupação
   antes/depois, tempo de geração antes/depois) — fecha o loop de "salvar
   pra futuras consultas" também no nível de documentação técnica.

---

## Ordem recomendada e dependências

```
Fase 0 (fila+notificação+histórico) ──┐
                                        ├──► Fase 2 (packer exato) ──► Fase 3 (convergência overflow) ──► Fase 4 (observabilidade) ──► Fase 6 (validação/rollout)
Fase 1 (correções rápidas) ───────────┘                                                                        │
                                                                                                                 └──► Fase 5 (opcional, reotimização profunda)
```

Fase 0 e Fase 1 são independentes entre si e de baixo risco — podem ser
feitas em qualquer ordem ou em paralelo. Fase 2 é o conserto central e
depende conceitualmente de Fase 1 (larguras em mm, espaçamento) já
existirem, mas tecnicamente pode ser feita antes se preferir — a ordem
sugerida só evita retrabalho. Fase 0 precisa existir **antes** de Fase 2
entrar em produção, porque é ela que garante que um packer mais lento (DP
exato, loop de convergência) não trava o request HTTP nem a UI.

## Próximo passo

Aprovar o início pela **Fase 0**. Ao final dela, o comportamento observável
para o usuário já muda (geração assíncrona + notificação + histórico
consultável), mesmo que a precisão do empacotamento em si só melhore de
verdade a partir da Fase 2.

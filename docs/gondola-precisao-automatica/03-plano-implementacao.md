# Plano de Implementação — Gôndola Precisa e Verdadeira

> **Status: RESOLVIDO na prática — 39,7% → 87% de ocupação, medido.** Mas NÃO pelo
> caminho que este plano previa. A medição da Fase 2 derrubou a premissa da auditoria
> (o packer exato não era o gargalo; rendeu ~1pp). O conserto real foram três bugs de
> **alocação de espaço entre categorias** e de **métrica**, não de empacotamento.
>
> Commitado em `dev`: Fase 0 (887621cf), Fase 1 (d7985ef8), Fase 2 (ba7e5f11) +
> `a53b2be4` (reparte sortimento), `875a6e50` (overflow p/ irmãs), `a94564fa`
> (product_analyses ausente), `4e465fed` (métrica física).
>
> **Leia primeiro o quadro "As fases 3-6 abaixo eram do plano ORIGINAL" mais abaixo** —
> ele explica o que a medição mudou e o que ainda sobra. As fases 3-6 escritas aqui
> refletem o diagnóstico ANTIGO e errado; ficam como registro histórico.
>
> Validação no browser pendente (tudo foi medido via banco).

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

## ⚠️ As fases 3-6 abaixo eram do plano ORIGINAL — a medição as tornou obsoletas

O plano previa: packer exato (Fase 2) → loop de convergência do overflow (3) →
observabilidade (4) → reotimização profunda GA/MILP (5) → rollout (6).

Aí a Fase 2 foi medida e **o gargalo não era nenhum desses**. O que de fato
levou a gôndola de **39,7% para 87% de ocupação** foram três bugs a MONTANTE
e a jusante da prateleira, não um empacotador mais esperto:

1. **Categoria com N prateleiras usava 1** (commit `a53b2be4`) — o plano de slots
   dava N prateleiras a uma categoria, mas o motor empilhava o sortimento inteiro
   na primeira e deixava as N−1 irmãs VAZIAS. `takeCategoryShare()` reparte.
   **Esse foi o conserto grande: 39,7% → 83,3%.**
2. **Overflow preso na mesma categoria** (commit `875a6e50`) — 257cm de prateleira
   vazia convivendo com produtos rejeitados, só por falta de PERMISSÃO. Escopo
   `siblings` deixa transbordar para categorias irmãs. **83,3% → 87%.**
3. **A métrica mentia** (commits `a53b2be4` + `4e465fed`) — a ocupação relatada
   ignorava tudo que o overflow colocava e pulava slots vazios, então anunciava
   78% quando a realidade era 39,7%. `shelfAnalysis` mede a prateleira física no
   fim de tudo. Sem isso, nenhuma das melhorias acima seria visível.

E o `product_analyses` ausente (commit `a94564fa`), que derrubava a geração sempre
que "usar análise existente" estava marcado, com o erro mascarado de "cancelado".

**Lição para quem retomar:** o custo real da imprecisão estava na **alocação de
espaço entre categorias** (plano de slots + permissão de overflow), não no
empacotamento dentro da prateleira. O packer exato (Fase 2) rendeu ~1pp. Antes de
otimizar qualquer coisa aqui, **gerar uma gôndola real e ler o `shelf_analysis` do
run** — foi a medição, não a leitura de código, que apontou cada bug.

### O que sobra de verdade

- **Últimos ~13%** (5 prateleiras abaixo do alvo de 90%): é o teto de frentes por
  **estoque alvo** funcionando — não empilha produto sem giro. **Decisão de produto
  tomada: DEIXAR COMO ESTÁ.** 87% reflete a demanda real do mix; fechar além disso
  exporia produto além do que o giro pede. `target_occupancy_rate` segue só medindo,
  de propósito.
- **`SHELF_FILL_RATE_ESTIMATE = 0.75`** (`AutoTemplateSynthesisOrchestrator:50`):
  ainda infla o número de módulos em 33% em gôndola nova. Sob compressão não faz mal;
  sem compressão dá gôndola a mais para o mesmo mix. Candidato a revisão FUTURA, mas
  não é urgente e mexer aqui muda a estrutura da gôndola — validar bem antes.
- **`Pusher error: Payload too large`** no broadcast da notificação: o
  `capacity_report` cresceu demais para o push em tempo real. Não quebra nada (a
  notificação persiste e aparece no sino; só o push falha). Enxugar o payload do
  broadcast se quiser que o relatório abra sozinho na tela sem F5.

### Ideias do plano original que continuam válidas SE precisar de mais

- **Loop de convergência do overflow** (era a Fase 3): hoje `placeOverflow()` roda
  uma vez. Viraria loop até não melhorar. Ganho esperado agora é pequeno (o overflow
  já recupera a maior parte), mas é o caminho se aparecer um caso que não fecha.
- **Reotimização profunda GA/MILP** (era a Fase 5): só como diferencial de produto
  ("otimização máxima" em batch), nunca no caminho padrão. Fora de escopo hoje.

## Estado final (medido, não estimado)

| Marco | Ocupação | Prateleiras vazias | Commit |
|---|---|---|---|
| Início | 39,7% | 8 de 16 | — |
| Reparte sortimento entre slots | 83,3% | 0 | `a53b2be4` |
| Transbordo p/ irmãs (`siblings`) | 87,0% | 0 | `875a6e50` |
| Métrica passa a bater com a realidade | 87,0% (relatado = real) | 0 | `4e465fed` |

Tudo no branch `dev`. **Validação no browser pendente** — todos os números acima
foram medidos via banco. O transbordo entre irmãs em especial precisa de olho
humano (produto de uma subcategoria na prateleira da irmã pode ficar estranho).

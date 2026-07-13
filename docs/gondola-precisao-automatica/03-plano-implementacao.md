# Plano de Implementação — Gôndola Precisa e Verdadeira

> Nada implementado ainda. Este documento é o plano; a execução de cada fase
> precisa de aprovação explícita antes de começar (padrão do projeto).

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

## Fase 0 — Fundação assíncrona (fila + notificação + histórico)

**Objetivo:** mover a geração do request síncrono para fila, sem tocar em
nenhuma lógica de posicionamento ainda. Risco baixíssimo — é só transporte.

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

## Fase 1 — Correções rápidas de precisão (Tier 1, baixo risco)

Pode rodar em paralelo à Fase 0 (não depende da fila) ou logo depois.

| Passo | O que muda | Onde |
|---|---|---|
| 1.1 | Precisão em **mm** em vez de arredondar cada largura/posição pra cm inteiro; arredondar só na hora de persistir o valor final do segmento | `ShelfLayoutDTO.php`, `TemplatePlacementEngine.php:1033,1098,1104`, `GreedyShelfPlacer.php:602` |
| 1.2 | Novo parâmetro de **espaçamento entre produtos** (default 0, configurável), efetivamente subtraído da largura disponível a cada item posicionado | `PlacementSettings.php`, `distributeInShelf()` |
| 1.3 | `targetOccupancyRate` passa a ser **lido de verdade**: após a Fase 1 + `expandFacings`, comparar ocupação real contra o alvo e registrar no relatório (a ação de fato de tentar fechar mais fica pra Fase 2/3) | `PlacementSettings.php`, `TemplatePlacementEngine.php` |
| 1.4 | `ProductWidthResolver`: logar warning estruturado (não silenciar) quando cai no fallback de 10cm, e listar produtos com dimensão suspeita no `PlanogramGenerationRun` | `ProductWidthResolver.php:16,29-61` |

**Testes:** ajustar fixtures de `TemplatePlacementEngineTest.php` e
`AutoPlanogramPlacementTest.php` para a nova precisão em mm; nenhuma
regressão funcional esperada (mesmos produtos elegíveis, só menos erro de
arredondamento).

**Critério de pronto:** ocupação medida sobe (parcialmente — ainda não é o
conserto estrutural), erro de arredondamento cumulativo eliminado, dado ruim
de largura fica visível no relatório em vez de silencioso.

---

## Fase 2 — Motor de empacotamento exato por prateleira (o conserto central)

Esta é a fase que **de fato** resolve "fechar a gôndola com precisão" —
tudo antes é preparação/higiene.

| Passo | O que muda | Onde |
|---|---|---|
| 2.1 | Novo serviço `Placement/ShelfKnapsackPacker.php` — resolve, por prateleira, um **bounded knapsack via DP**: única variável livre por candidato = contagem de frentes em `[min_facings, max_facings]`, maximizando largura ocupada (ou score ponderado), sujeito à capacidade da prateleira (mm, Fase 1) e ao espaçamento (Fase 1) | novo arquivo em `Placement/` |
| 2.2 | Substituir a Fase 1 de `distributeInShelf()` (e o equivalente em `GreedyShelfPlacer`) para chamar o novo packer em vez do loop first-fit — **mantém a ordenação de candidatos existente como input**, não redesenha o ranking a montante | `TemplatePlacementEngine.php:1004-1077`, `GreedyShelfPlacer.php:406-437,491-520` |
| 2.3 | Dispatch por tamanho de instância (padrão confirmado pela pesquisa): DP exato até um limiar (ex. 40 itens/prateleira), heurística construtiva mais rápida acima disso como fallback raro | `ShelfKnapsackPacker.php` |
| 2.4 | Medir e persistir performance por prateleira/gôndola (`duration_ms` do `PlanogramGenerationRun`, já criado na Fase 0) — decide se cabe no timeout padrão do job (600s) ou se precisa de supervisor Horizon dedicado com timeout maior | `GenerateAutoPlanogramJob.php` |

**Testes:**
- Unitários do packer isolado: 0 candidatos, 1 candidato que não cabe nem no
  mínimo, exact-fit (soma exata = largura disponível), muitos candidatos
  pequenos, candidato único que sozinho excede a prateleira.
- Regressão: comparar ocupação média antes/depois nos fixtures existentes
  de `TemplatePlacementEngineTest.php` / `AutoPlanogramPlacementTest.php` —
  meta: sair de ~70-80% para >95% de ocupação nos casos de teste.
- Performance: benchmark de tempo com prateleiras de 40 itens e gôndolas de
  "centenas de prateleiras" (simular volume real), documentado no relatório
  da execução.

**Critério de pronto:** ocupação média medida nos testes sobe
significativamente, sem estourar o timeout do job, zero regressão funcional
(os mesmos produtos elegíveis continuam sendo posicionados — só a
distribuição de frentes/gaps melhora).

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

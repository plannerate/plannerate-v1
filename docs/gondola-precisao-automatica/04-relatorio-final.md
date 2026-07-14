# Relatório — Precisão da Geração Automática de Gôndola

> **Status: EM PRODUÇÃO** (merge `26ce193e`, deploy 2026-07-13).
> Resultado medido numa gôndola real: **39,7% → 87% de ocupação**, zero prateleiras vazias.

## 1. O pedido

*"Não estamos conseguindo fechar a gôndola com precisão."* A geração automática deixava
prateleiras vazias e produtos de fora.

Decisão de produto confirmada no início: **precisão > velocidade**. A geração pode demorar,
desde que rode em fila, avise ao terminar e fique salva para consulta.

## 2. A auditoria errou o alvo — e a medição provou

A auditoria inicial (`01-auditoria-codigo-atual.md`) culpou o empacotador da prateleira: um
first-fit sequencial sem backtracking. A pesquisa externa (`02-pesquisa-externa.md`) confirmou
que o problema tem nome na literatura (Shelf Space Allocation Problem) e que a solução canônica
é resolver frentes + capacidade conjuntamente por prateleira.

Construímos esse empacotador exato (bounded knapsack por programação dinâmica). Aí **medimos**:

| teto de frentes | guloso | empacotador exato | ganho |
|---|---|---|---|
| 3 | 97,3% | 98,2% | +0,9 pp |
| 6 | 97,1% | 98,1% | +1,0 pp |
| 12 | 97,1% | 98,2% | +1,0 pp |

**O guloso já ocupava ~97% do espaço que recebia.** O empacotador rendeu ~1 ponto percentual.

Por que a auditoria errou: o first-fit coloca **todos** os produtos com a frente **mínima antes**
de expandir qualquer frente. Ele já é "variedade primeiro", e o momento em que rejeita um produto
é justamente quando a prateleira está **mais vazia**. Um produto que ele rejeita não caberia de
jeito nenhum.

**A perda não estava dentro da prateleira. Estava na alocação de espaço entre categorias.**

## 3. Os consertos que de fato moveram o número

### 3.1 Categoria recebia N prateleiras e usava 1 — `a53b2be4` — **39,7% → 83,3%**

O `SlotPlanBuilder` dá N prateleiras a uma categoria dimensionando pela largura **total** dos
produtos dela. Mas o motor posiciona cada produto com a frente **mínima** — e com 1 frente o
sortimento inteiro cabe numa prateleira só.

Resultado: o **1º slot levava todos os produtos**, e do 2º em diante o `findCandidates` não achava
mais nada. Os slots irmãos ficavam **vazios** (`slots_sem_matching`). Numa gôndola real: **7 de 16
prateleiras zeradas**.

A expansão de frentes não salvava, porque só trabalha **dentro** da prateleira onde o produto já
está: enchia a primeira até 96-100% e não tinha como transbordar para as irmãs vazias ao lado.

**Fix:** `takeCategoryShare()` — cada slot leva a fatia dele (os melhores ranqueados ainda não
usados, até cobrir 1/N da largura restante). O último slot leva o que sobrou.

### 3.2 Overflow preso na mesma categoria — `875a6e50` — **83,3% → 87,0%**

257cm de prateleira **vazia** convivendo com **11 produtos rejeitados por falta de espaço**. Não
faltava espaço — faltava **permissão**: o overflow só realocava um produto rejeitado em prateleiras
da **mesma** categoria. A categoria sem produto para encher a prateleira dela segurava o vão; a
categoria que transbordava não podia usá-lo.

**Fix:** config `plannerate.auto_planogram.placement.overflow_scope`:

| valor | comportamento |
|---|---|
| `strict` | só a própria categoria (o comportamento anterior) |
| `siblings` | **padrão** — também as categorias irmãs (mesmo pai no mercadológico) |
| `any` | qualquer categoria do planograma |

`siblings` fecha a maior parte do vão sem virar bagunça: irmãs já ficam lado a lado na loja
(LÍQUIDO junto de GEL, ambas filhas de CUIDADO COM O BANHEIRO).

Rollback sem deploy: `PLANNERATE_OVERFLOW_SCOPE=strict`.

### 3.3 A métrica mentia — `a53b2be4` + `4e465fed`

O `occupancy_avg` era derivado do `slot_analysis`, que é montado **dentro** do laço de slots —
**antes** do overflow pass. Tudo que o overflow colocava ficava fora da conta. E slots vazios eram
pulados antes de entrar na análise.

Sintoma: a gôndola foi de 83,3% para 87,0% e **o relatório ficou cravado em 76,81% nas duas**. O
usuário via a gôndola mudar e o número não mexer — e concluiu, com razão, que "não mudou nada".
Antes disso, o relatório chegou a anunciar **78% com a gôndola em 39,7%**.

**Fix:** `shelfAnalysis` — mede a prateleira **física**, no fim de tudo, sobre os segmentos finais.
Prateleira vazia entra com 0% (ela é o defeito, não pode ser omitida da média).
Verificado: relatório 87,13% contra realidade medida no banco de 87,0%.

### 3.4 `product_analyses` derrubava a geração — `a94564fa`

Tabela de cache que **nunca existiu**: sem migration em lugar nenhum, e nada no código escreve
nela — só uma leitura. Marcar **"usar análise existente"** explodia a geração inteira, **sempre**.

Junto, um bug meu introduzido na Fase 0: o job capturava `\RuntimeException` para tratar
cancelamento de negócio. Mas `QueryException` **também é** uma `RuntimeException` — então a falha
de banco era **engolida e mostrada como "geração cancelada"**. O erro real só aparecia no log.

**Fix:** guarda com `plannerateTenantHasTable()` (degrada para o cálculo ABC on-the-fly, que já
existia) + `GenerationCancelledException` como tipo próprio, para erro técnico voltar a estourar.

## 4. A fundação (entregue antes dos consertos)

| Fase | O que entregou | Commit |
|---|---|---|
| 0 | **Geração assíncrona**: job em fila + notificação + `PlanogramGenerationRun` (histórico consultável por execução, com snapshot da config e relatório completo) | `887621cf` |
| 1 | **Aritmética de encaixe exata**: `PlacementMath` — encaixe em float exato, arredondamento só na persistência por soma de prefixos (segmentos contíguos, sem erro acumulado) | `d7985ef8` |
| 2 | **Empacotador exato** (`ShelfKnapsackPacker`): bounded knapsack por DP, com não-regressão **por construção** (o que o guloso colocaria entra como obrigatório) | `ba7e5f11` |
| — | **Aviso de blocagem vertical não aplicada**: o modo vertical só forma coluna com 2+ prateleiras exclusivas consecutivas; antes o fallback para horizontal era silencioso | `ea70a50c` |

Bugs reais achados de quebra na Fase 1: `placeOverflow` arredondava a largura **unitária** antes de
multiplicar pelas frentes (3,4cm × 5 = "15cm" em vez de 17cm → encaixava produto onde não cabia);
e produto com `width = null` caía num fallback de 10cm em **silêncio total**.

Na Fase 2: o fallback `reduce_facings` montava segmentos fora da esteira do cursor e os posicionava
a partir de **x=0, sobrepondo** os produtos já colocados.

## 5. Interruptores de rollback (sem deploy)

| Variável | Efeito |
|---|---|
| `PLANNERATE_OVERFLOW_SCOPE=strict` | volta à blocagem rígida por categoria |
| `PLANNERATE_SHELF_PACKER=greedy` | volta ao empacotador guloso antigo |
| `PLANNERATE_PRODUCT_SPACING_CM` | folga entre produtos (default 0.0) |
| `PLANNERATE_TARGET_OCCUPANCY_RATE` | alvo de ocupação (só mede, não age) |

## 6. O que sobra — e a decisão tomada

Restam ~13% de vão (prateleiras abaixo do alvo de 90%). **Não é bug:** é o teto de frentes por
**estoque alvo** funcionando — não empilha produto que não tem giro para justificar.

**Decisão de produto: deixar como está.** 87% reflete a demanda real do mix. Fechar além disso
exporia produto além do que o giro pede. O `target_occupancy_rate` segue apenas medindo, de propósito.

Pendências menores, registradas mas não urgentes:

- **`SHELF_FILL_RATE_ESTIMATE = 0.75`** (`AutoTemplateSynthesisOrchestrator:50`) ainda infla o número
  de módulos em 33% em gôndola nova. Sob compressão não faz mal; sem compressão, dá gôndola a mais
  para o mesmo mix. Mexer aqui muda a estrutura da gôndola — validar bem antes.
- **`Pusher error: Payload too large`** no broadcast da notificação de conclusão. Não quebra nada (a
  notificação persiste e aparece no sino); só o push em tempo real falha. Ver o plano do overlay.

## 7. Como está a suíte de testes (achado importante)

**A suíte completa tem ~390 falhas no `main` — e isso é anterior a este trabalho.** O CI não a
executa: ele roda uma **lista curada de caminhos** (ver `.github/workflows/tests.yml`), e por isso
fica verde.

Na bateria que o CI de fato roda: **374 passando neste trabalho contra 334 no `main`**, com a mesma
única falha pré-existente. Zero regressão.

Mas essa suíte quebrada é **dívida real**: qualquer regressão fora dos caminhos curados passa
despercebida. Vale um trabalho dedicado.

## 8. Caveats operacionais

- **O Horizon roda em container próprio e mantém o PHP em memória.** Depois de qualquer mudança no
  motor: `docker compose restart horizon`. Sem isso o worker segue rodando o código antigo — isto
  custou uma medição inteira durante este trabalho (o fix "não funcionou" porque o worker era velho).
- **Medir uma gôndola real** é o único jeito confiável de avaliar precisão. O `capacity_report` do
  `PlanogramGenerationRun` traz `shelf_analysis` com a ocupação física de cada prateleira. Foi a
  medição — não a leitura de código — que apontou cada um dos bugs reais.
- A geração é **assíncrona**: exige Horizon saudável consumindo a fila `default`. Se ele não estiver
  rodando, a execução fica pendurada em `queued` e o usuário não recebe nada.

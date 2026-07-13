# Auditoria do Código Atual — `AutoPlanogram`

Raiz: `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/`

Investigação read-only feita sobre o motor de geração automática/template de
planogramas, focada especificamente em por que o preenchimento das
prateleiras é impreciso.

## 1. Fluxo end-to-end — modo "automático"

Entrada: `AutoGenerationRunner::run()` (`AutoGenerationRunner.php:38-156`) →
`ProductSelectionService::selectAndRankProducts()` seleciona/filtra o pool de
produtos e calcula ABC/estoque-alvo/papel (`ProductSelectionService.php:59-149`)
→ `AutoPlanogramService::generate()` (`AutoPlanogramService.php:56-137`).

Dentro de `generate()`, quando `settings->usesTemplate()` é falso
(`AutoPlanogramService.php:77-136`):

1. `scorer->scoreOrNeutral()` → `CompositeScorer::score()`
   (`CompositeScorer.php:25-70`) computa um score composto por produto: `giro`
   (quantidade log-normalizada), `margem` (min-max normalizada), `estrategico`
   (flag), `doh` (placeholder neutro 0.5), `crescimento` (papel, peso 0 por
   padrão) — `CompositeScorer.php:134-172`. Esse score decide **ordem de
   prioridade**, não frentes nem prateleira diretamente.
2. `ensureShelvesExist()`/`countShelvesPerModule()` travam a estrutura física
   de prateleiras (`AutoPlanogramService.php:182-224`).
3. `AutoTemplateSynthesisOrchestrator::orchestrate()`
   (`AutoTemplateSynthesisOrchestrator.php:67-148`) transforma o pool
   pontuado num **template sintetizado**: agrupa produtos por subcategoria,
   infere `CategoryRole` (`CategoryRoleInferrer.php:37-78`), e chama
   `SlotPlanBuilder::build()` para particionar slots (módulo, prateleira)
   proporcionalmente à demanda (ver §3).
4. O template sintetizado roda pelo **mesmo** caminho `generateWithTemplate()`
   do modo template (`AutoPlanogramService.php:128`, chamando
   `TemplatePlacementEngine::place()`).
5. Depois, `pruneEmptySlots()` apaga slots de template que ficaram sem
   candidatos (`AutoPlanogramService.php:151-166`) — é limpeza estrutural, não
   reparo de espaço de prateleira.

**Frentes**: decididas em dois lugares — `SlotPlanBuilder::deriveMinFacings()`
define `min_facings` por slot a partir da classe ABC
(`SlotPlanBuilder.php:640-658`, todas as classes têm piso 1 —
`ABC_MIN_FACINGS = ['A'=>1,'B'=>1,'C'=>1,''=>1]`, `SlotPlanBuilder.php:52`),
depois `TemplatePlacementEngine::expandFacings()` cresce frentes no espaço
sobrando (ver §4).

**Atribuição de prateleira**: totalmente guiada por template —
`SlotPlanBuilder::buildOrderedSlots()`/`partitionIntoBlocks()` atribuem cada
subcategoria a slots específicos de `(module_number, shelf_order)` antes do
posicionamento (`SlotPlanBuilder.php:176-395`); o placer só preenche a
prateleira já atribuída.

## 2. Fluxo end-to-end — modo "template"

`AutoPlanogramService::generateWithTemplate()`
(`AutoPlanogramService.php:226-317`) monta `PlacementSettings` (mapas de
ABC/estoque-alvo/BCG, regras de produto, overrides de slot da gôndola) e chama
`TemplatePlacementEngine::place()` (`TemplatePlacementEngine.php:96-446`).

`place()`:

1. Resolve o `PlanogramSubtemplate` correspondente por
   `num_modules <= seções da gôndola` (`TemplatePlacementEngine.php:776-784`);
   se não achar, cai para `GreedyShelfPlacer::place()` (ver §3) —
   `TemplatePlacementEngine.php:113-122`.
2. Carrega slots ordenados, detecta slots de prateleira compartilhada
   (múltiplas categorias numa mesma prateleira física) e grupos de bloco
   vertical (`TemplatePlacementEngine.php:153-218`).
3. Para cada slot: resolve `Section`/`Shelf` via `resolveShelf()`
   (`shelf_order 1` = chão; `index = numShelves - shelf_order`,
   `TemplatePlacementEngine.php:796-803`), encontra candidatos de categoria
   (`findCandidates()`, `:862-881`), ordena por ABC/preço/tamanho/zona
   (`orderCandidates()`, `:890-905`), e chama `distributeInShelf()` — o
   empacotador central (ver §3).
4. Depois de todos os slots: um **passe de overflow**
   (`placeOverflow()`, `:473-736`) tenta reencaixar produtos rejeitados por
   `NoHorizontalSpace` em prateleiras da **mesma categoria** que ainda têm
   espaço, ou em prateleiras totalmente vazias sobrando.
5. Mismatch de subtemplate, relatório de explicação e `slotAnalysis` (por
   slot, `largura_livre`/`percentual_uso`) são anexados ao `PlacementResult`
   para a camada de UI/sugestões.

`SlotPlanBuilder` traduz demanda de categoria num plano de slots
(`list<SlotPlanEntry>`), e `AutoTemplateSynthesizer::createSlots()` persiste
como linhas `PlanogramTemplateSlot`
(`AutoTemplateSynthesizer.php:190-212`) — essa é a ponte entre "plano de slots
abstrato" e as linhas concretas de DB que o motor lê de volta.

## 3. O algoritmo central de preenchimento de prateleira

**Nenhuma otimização de bin-packing em lugar nenhum** (sem best-fit/FFD/
knapsack/DP/ILP). Todo empacotador nesta árvore é **first-fit sequencial com
parada antecipada**, não best-fit:

- `GreedyShelfPlacer::tryPlaceProductsInSection()`
  (`GreedyShelfPlacer.php:406-437`): para cada produto, varre prateleiras
  `for ($shelfIndex = $currentShelfIndex; ...)` e pega a **primeira**
  prateleira onde `tryAllocate()` funciona (`:418-427`); se nenhuma
  prateleira couber, **a tentativa inteira da seção falha** (`return null`,
  `:430`) — sem crédito parcial, sem retry com menos frentes.
- `GreedyShelfPlacer::fitContiguousRun()` (`:491-520`) é o caminho de
  fallback/split: mesma lógica first-fit-por-prateleira, mas **quebra todo o
  loop** assim que um produto falha em posicionar (`:513`) — os produtos
  restantes daquele bloco ordenado nunca são tentados na mesma ou em outra
  prateleira; vão direto pra rejeição `NoHorizontalSpace` (`:476-481`).
- `TemplatePlacementEngine::distributeInShelf()` Fase 1 (`:1004-1077`): itera
  candidatos uma vez, em ordem ranqueada, cada um recebendo `min_facings`; o
  check é `if ($occupied + $width <= $available)` (`:1035`), senão
  **rejeição imediata** (`:1071-1075`) — o loop **não** tenta o próximo
  candidato (possivelmente mais estreito) contra o **mesmo** espaço restante
  antes de seguir adiante; ele só continua para o próximo produto da lista
  ranqueada, então um candidato mais estreito e de rank mais baixo mais
  adiante na lista ainda pode caber e ser posicionado, mas não há
  reordenação/backtracking para preferir o melhor empacotamento.
- `ShelfLayoutDTO::addProduct()` (`ShelfLayoutDTO.php:59-86`) é o check
  folha "cabe ou não": simples
  `occupiedWidth + productWidth <= availableWidth`.

Resposta a "o que acontece quando não cabe exatamente": o produto atual é
rejeitado de vez (nunca redimensionado para menos), o algoritmo avança para o
próximo candidato — **não** tenta um número menor de frentes para o *mesmo*
produto (Fase 1 sempre usa `min_facings`, nunca algo menor), e o espaço
sobrando no fim de uma prateleira simplesmente fica lá a menos que a expansão
de Fase 2 (§4) ou o passe de overflow (§7) consumam depois.

## 4. Tratamento de gap/sobra — `ExposureRedistributeService` e `expandFacings`

`ExposureRedistributeService::redistribute()`
(`ExposureRedistributeService.php:31-103`) e seu irmão
`VisualReorderService::reorder()` (`VisualReorderService.php:34-111`) são
**explicitamente documentados como preservadores de invariante**: "mesmo
conjunto {produto: frentes} antes e depois — não recomputa scoring nem
rejeitados" (`ExposureRedistributeService.php:15`). Só reordenam segmentos já
posicionados (recomputam `ordering`/`position`) quando o usuário muda
exposição de marca/sabor ou critério visual no editor. **Nunca adicionam
frentes nem trazem produtos novos para consumir a largura sobrando.**

O mecanismo real que consome sobra é
`TemplatePlacementEngine::expandFacings()`
(`:1730-1791`, "Fase 2: distribui espaço sobrando da prateleira como frentes
extras"). Vai dando `+1 frente` em round-robin para itens já posicionados
(em `expansionOrder()` — ordem por score/estoque-atual/déficit de
estoque-alvo, `:1909-1942`) enquanto `remainingWidth > 0` e cada item está
abaixo de `max_facings`/`targetStockFacingCap`/limites de participação
(`violatesParticipationLimit()`, `:1843-1901`). Isso é consumo real de sobra,
**mas só entre produtos já selecionados para aquele slot** — não pode trazer
um produto diferente (mais estreito) rejeitado para tapar um buraco que
nenhuma largura de item existente divide igualmente. Fica atrás de
`slot->facing_expansion !== FacingExpansion::None` (`:1080`) — se o
`facing_expansion` de um slot for `None`, o gap `available - occupied` da
Fase 1 fica completamente sem uso.

## 5. De onde vem a imprecisão — caminhos concretos de código

- **Parada em first-fit, sem reempacotamento**:
  `GreedyShelfPlacer::fitContiguousRun()` quebra inteiramente no primeiro
  produto que não cabe (`GreedyShelfPlacer.php:512-514`), e
  `distributeInShelf()` Fase 1 nunca tenta de novo um produto rejeitado com
  menos frentes.
- **Fallback do `ProductWidthResolver`**: largura `<=0`, `null`, ou `>60cm` é
  silenciosamente substituída por um fixo `DEFAULT_WIDTH_CM = 10.0`
  (`ProductWidthResolver.php:16,29-61`). Como o check de encaixe
  (`occupied + width <= available`) usa essa largura resolvida, dado ruim de
  master data desloca silenciosamente a largura restante real por uma
  quantidade desconhecida, produzindo gaps ou overflows falsos.
- **Sem espaçamento/margem entre produtos em lugar nenhum**:
  `distributeInShelf` computa `$width = singleWidth * facings` e empacota
  itens encostados (`x += $width`, `:1116`); não há um gap entre produtos
  configurado subtraído (confirmado ausente em `config/plannerate.php` — só
  existe `holeSpacing`, para furos de pegboard, sem relação com empacotamento
  de produto).
- **Arredondamento para cm inteiro**: toda largura/posição posicionada é
  `(int) round(...)` (`TemplatePlacementEngine.php:1033, 1098, 1104`;
  `GreedyShelfPlacer.php:602`), então o arredondamento cumulativo entre
  muitos segmentos numa prateleira pode deixar até `0.5cm × N` sem ser
  contabilizado, e também pode causar rejeição de um produto por 1cm
  arredondado que caberia em precisão sub-cm.
- **A matemática de demanda do `SlotPlanBuilder` é uma estimativa, não a
  aritmética própria do placer**: `computePerSubcatSlots()` usa
  `ceil(totalWidth / shelfWidth)` (`SlotPlanBuilder.php:483-488`) e
  `AutoTemplateSynthesisOrchestrator::computeNumModules()` deliberadamente
  encolhe a largura útil de prateleira com um
  `SHELF_FILL_RATE_ESTIMATE = 0.75` hard-coded
  (`AutoTemplateSynthesisOrchestrator.php:39-50,198-221`) com o comentário
  admitindo explicitamente: *"O placement engine não consegue usar 100% da
  largura da prateleira ... ocupação típica de 70–80%."* Esse é o
  reconhecimento do próprio código de que ~20-30% da largura da prateleira é
  rotineiramente desperdiçada por design, não um limiar de bug.
- **Piso de `min_facings` antes do check de encaixe**
  (`TemplatePlacementEngine.php:1031`, `max($slot->min_facings, 1)`): se o
  `min_facings` vindo do `SlotPlanBuilder` ou de um override de gôndola for
  alto demais para a largura disponível real, produtos inteiros são
  rejeitados em vez de posicionados com 1 frente.
- **Heurística de reserva de micro-categoria**
  (`SlotPlanBuilder::MICRO_CATEGORY_WIDTH_THRESHOLD = 0.35`,
  `SlotPlanBuilder.php:55-68`, espelhada em
  `TemplatePlacementEngine.php:281-296`) reserva uma fatia fixa de 35% da
  largura da prateleira para uma categoria "futura" *antes* de saber a
  demanda real dela — super ou subestima o espaço sobrando dependendo de
  quão bem a heurística casa com a largura real da micro-categoria.

## 6. Regras de validação — só relatório, sem loop de reparo

`PlanogramValidator::validate()` (`PlanogramValidator.php:43-53`) apenas
roda cada regra e concatena `ValidationResult`s num `ValidationReport` — **sem
retry, sem reinvocação do motor de posicionamento, sem mutação de
segmentos**. Confirmado em `AutoPlanogramService.php:264`
(`$report = $this->validator->validate(...)`) — o relatório é anexado ao
`PlanogramOutput` e logado (`:307-314`), nunca realimentado numa nova
tentativa de posicionamento.

- `EmptyShelfRule` (`EmptyShelfRule.php:18-68`): severidade `Info` pura,
  lista prateleiras vazias em seções usadas — só relatório cosmético.
- `SectionCapacityRule` (`SectionCapacityRule.php:21-107`): `Warning` se
  utilização `<70%` ou `>95%` (`MIN_CAPACITY`/`MAX_CAPACITY`, `:23-25`) — só
  sugestão de texto ("Considere consolidar produtos").
- `UnplacedProductsRule` (`UnplacedProductsRule.php:13-54`): converte
  `PlacementFailureReason` em `Error`/`Warning` conforme
  `reason->isHardRule()` — ainda assim só um item de relatório, confirmado
  pelo teste `AutoPlanogramPlacementTest.php:37-53`, que mostra rejeições de
  `NoHorizontalSpace` produzindo `report->passed === true` com um warning,
  i.e. **o planograma ainda é aceito/persistido mesmo com produtos não
  posicionados** — nenhum gate bloqueia a gravação.

Nenhuma dessas regras dispara uma segunda tentativa de posicionamento ou
qualquer auto-reparo; são informativas para o revisor humano.

## 7. `RejectedProductsWriter` — rejeitado permanentemente (com uma exceção parcial)

`TemplatePlacementEngine::placeOverflow()` (`:473-736`) é o **único**
mecanismo de retentativa: produtos rejeitados com `NoHorizontalSpace` são
retentados contra *outras prateleiras da mesma categoria* (ou prateleiras
vazias "reivindicadas") num esquema de duas passagens
variedade-depois-profundidade (`:556-681`). Roda uma vez, depois do loop
principal de slots, não iterativamente/até convergência.

O que ainda sobra rejeitado depois desse passe de overflow chega em
`RejectedProductsWriter::write()` (`RejectedProductsWriter.php:15-88`), que
**apaga linhas de rejeição anteriores da gôndola/planograma e insere a nova
lista final** (`:17-19,87`) — isso é persistência terminal para exibição na
UI (`PlanogramRejectedProduct`), não uma fila para reconsideração posterior.
Não existe caminho de código em lugar nenhum desta árvore que revisite
`planogram_rejected_products` para tentar preencher um gap descoberto depois;
a única "reconsideração" é o único passe de overflow dentro da mesma execução
de geração.

## 8. Configurações — nenhum conceito de tolerância de gap existe

`PlacementSettings`/`AutoGenerateConfigDTO`
(`PlacementSettings.php:13-170`, `AutoGenerateConfigDTO.php:20-113`) expõem:
`minFacings`/`maxFacings` (padrões globais, default 1/10), `facingExpansion`
(`None`/`Score`/`CurrentStock`/`TargetStock`), `useTargetStock`,
`spaceFallback` (`ReduceC`/`RemoveDog`/`reduce_facings`/`skip`),
`maxSharePerSku`/`maxSharePerBrand`/`maxSharePerSubcategory`,
`targetOccupancyRate` (declarado, default 0.90, mas **só lido em
`toArray()`/`toConfigDto()` — nunca consumido por lógica de
posicionamento/empacotamento**, ou seja, config morta), e overrides por slot
via `planogram_gondola_slot_overrides`.

**Nenhuma dessas configurações representa uma tolerância de largura de gap,
um espaçamento/margem explícito entre produtos, ou um alvo de "fechar a
gôndola".** O mais perto disso é: (a) a tolerância implícita do
`expandFacings` é só "cabe mais uma frente de um item existente" (§4); (b)
`SlotPlanBuilder::MICRO_CATEGORY_WIDTH_THRESHOLD` (0.35) e
`AutoTemplateSynthesisOrchestrator::SHELF_FILL_RATE_ESTIMATE` (0.75) são
heurísticas hard-coded, não parâmetros configuráveis por usuário/tenant. Não
existe configuração tipo "preencher prateleiras até X% antes de aceitar um
gap" ou "trocar por produto rejeitado mais estreito para fechar um gap".

## 9. Reconhecimento existente do problema de precisão

- `AutoTemplateSynthesisOrchestrator.php:39-50`: comentário explícito de que
  o motor de posicionamento só atinge "ocupação típica de 70–80%" da largura
  da prateleira, e o código compensa *encolhendo a largura estimada da
  prateleira* em vez de consertar o empacotador.
- `SlotSuggestionGenerator.php:34,57-77`: `ESPACO_MINIMO_CM = 10` — qualquer
  slot com `>10cm` livre gera uma sugestão de UI ("Prat - X tem Ycm livres
  (Z% usado)... Considere adicionar mais produtos"). Essa é a evidência mais
  clara de que o sistema já detecta espaço sobrando por prateleira, mas só
  **expõe para um humano** em vez de fechar automaticamente.
- `TemplatePlacementEngine.php:1049-1068`: log de debug explicitamente
  rotulado "possível adensamento mal planejado" quando um slot tem zero
  espaço disponível antes mesmo de tentar um produto — um sintoma
  autodiagnosticado das heurísticas de reserva do §5 dimensionando mal o
  espaço disponível.
- `explanationReport`'s alerta `target_stock_not_met`
  (`TemplatePlacementEngine.php:2148-2160`) sinaliza produtos cuja expansão
  de frentes por estoque-alvo foi bloqueada por falta de espaço — outro
  sinal já instrumentado de prateleiras subpreenchidas/mal calculadas que
  para no relatório.

Não há um comentário TODO/FIXME explícito nomeando "precisão de
preenchimento de prateleira" diretamente, mas os logs de debug de
adensamento e a constante de taxa de preenchimento de 0.75 são a própria
admissão do código de que o uso de espaço de prateleira é aproximado por
design, não uma invariante rígida.

## Resumo das causas-raiz prioritárias

1. Posicionamento é first-fit sequencial estrito sem reempacotamento/
   backtracking (§3) — arquiteturalmente a maior fonte evitável de gaps.
2. `expandFacings` só cresce frentes de itens já escolhidos; não pode trocar
   por um produto de tamanho diferente para fechar um gap, e é totalmente
   pulado quando `facing_expansion = None` (§4).
3. O passe de overflow roda uma vez, é escopado por categoria, e nunca
   revisita a saída do `RejectedProductsWriter` para mais tentativas de
   preenchimento de gap (§7).
4. Nenhum conceito configurável/considerado de espaçamento entre produtos ou
   tolerância de gap existe (§8) — reivindicações de precisão assumem
   implicitamente espaçamento 0 e arredondamento em cm inteiro, o que a
   própria estimativa de taxa de preenchimento de 70-80% do código admite
   ser irrealista (§5, §9).

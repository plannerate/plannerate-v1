# Pesquisa Externa — Algoritmos de Empacotamento de Prateleira

## Metodologia

Pesquisa feita via harness de deep-research (fan-out de buscas, fetch de
fontes, verificação adversarial de alegações, síntese citada), alimentada com
o contexto específico da auditoria em
[01-auditoria-codigo-atual.md](01-auditoria-codigo-atual.md) — não uma
pergunta genérica.

- **6 ângulos de busca**: formulação acadêmica de OR (SSAP), tradeoffs de
  família de algoritmos de bin-packing, técnicas de software comercial de
  planograma, heurísticas de fechamento de gap pós-greedy, precisão de
  largura contínua vs. arredondamento, padrão de arquitetura para retrofit
  incremental.
- **24 fontes** fetchadas, **82 alegações** extraídas, **25 verificadas**
  adversarialmente (3 votos por alegação, mata com 2/3 de refutação):
  **13 confirmadas, 12 derrubadas, 0 não verificadas**.
- Fontes primárias confirmadas: 2 patentes industriais (Oracle US8930235,
  Walmart US11715048) + 6 papers acadêmicos de pesquisa operacional
  (RAIRO-RO 2025, Annals of OR 2010, OPSEARCH 2023, 2× MDPI/Czerniachowska,
  European Journal of OR).

## Achados confirmados (com implicação de implementação)

### 1. O SSAP já trata min/max-facings + capacidade da prateleira como restrições de primeira classe

> O modelo canônico de shelf-space-allocation (Yang) é um programa inteiro em
> que contagens mínima/máxima de frentes por produto (Li, Ui) e uma restrição
> de capacidade por prateleira (soma das larguras de frentes ≤ comprimento da
> prateleira) são restrições centrais, de primeira classe — não algo que um
> time aparafusa depois.

**Confiança: alta.** Fontes: RAIRO-RO 2025 (survey) + corroboração
independente sobre o paper original de Yang (2001, EJOR).

**Implicação:** o conserto não é inventar restrições novas — o sistema já
tem `min_facings`/`max_facings`/largura de prateleira como primitivas
corretas (§8 da auditoria). É **resolver essas restrições conjuntamente por
prateleira** (como um bounded knapsack), em vez de escanear uma vez e
congelar decisões.

### 2. Variantes não-lineares do SSAP são NP-hard → resposta padrão é DP+busca local ou linearização por partes

> Devido à NP-hardness do modelo não-linear de SSA, o campo responde com (a)
> heurísticas de programação dinâmica + busca local, e (b) linearização por
> partes que reformula o modelo não-linear num programa inteiro solucionável
> quase-exatamente via branch-and-bound com um bound de relaxação LP
> comprovadamente apertado.

**Confiança: alta.** Fontes: RAIRO-RO 2025 + Gajjar & Adil 2010 (Annals of
Operations Research).

**Implicação:** se o objetivo eventualmente precisar refletir retornos
decrescentes de vendas por frente adicional (não só "caber"), linearizar por
partes essa curva num IP é uma técnica estabelecida e tratável — não é
necessário inventar uma heurística do zero.

### 3. Dispatch por tamanho de instância é um padrão de design aceito na literatura

> Um paper de 2023 propõe seis heurísticas para maximização de lucro de
> planograma explicitamente hierarquizadas por tamanho de instância — três
> ajustadas para instâncias pequenas, três para instâncias grandes
> processando dados em massa rapidamente.

**Confiança: média.** Fonte única (OPSEARCH 2023), mas verificação unânime.

**Implicação:** como as prateleiras variam de ~5 a ~40 produtos, um dispatch
sensível ao tamanho (solve exato/DP abaixo de um limiar de itens, heurística
construtiva mais rápida acima) é um padrão legítimo, não uma otimização
arbitrária.

### 4. Algoritmos genéticos tratam contagem de frentes como gene diretamente mutável — e batem CPLEX com tempo limitado

> GAs para SSA codificam contagem de frentes como um gene diretamente
> mutável (operadores como Min/Max-Swap e Rotation mutam valores de frente
> diretamente, não só flags de alocação), e variantes de GA validadas batem
> CPLEX com tempo limitado na maioria dos casos de teste (18-22 de 22) em
> média de 1.5-3.5% de lucro (até ~27% em casos isolados), em 25 instâncias
> de 10-50 produtos em planogramas de 4 prateleiras/100-500cm — escala
> comparável a um run de gôndola real.

**Confiança: alta.** Fontes: 2 papers MDPI (Czerniachowska et al.),
corroborantes entre si.

**Implicação:** o conserto estrutural-chave generaliza além do GA
especificamente — **tratar contagem de frentes como variável de
busca/decisão de primeira classe conjuntamente entre todos os itens de uma
prateleira**, não um valor fixado uma vez no posicionamento e só ajustado
para cima depois para itens já colocados (exatamente a limitação #2 da
auditoria).

### 5. Patente Oracle (US8930235) — o padrão de retrofit mais transferível

> Um sistema industrial patenteado implementa um híbrido de duas fases: uma
> metaheurística de Busca Randomizada (RS) roda até um platô, semeia um
> solve de MILP, e o ciclo RS↔MILP se repete — alternando e re-semeando —
> até que a solução MILP atinja um limiar de precisão ou um orçamento de
> tempo se esgote, i.e., reotimização iterativa guiada por convergência, não
> um único passe greedy nem uma única retentativa de overflow.

**Confiança: média** (uma única patente, mas 3 sub-alegações verificadas
independentemente, texto idêntico em re-fetches).

**Implicação:** este é o padrão de retrofit mais diretamente transferível
para o passe de overflow atual (que é uma tentativa única, §7 da auditoria)
— substituir por "heurística de construção → polimento local/MILP rápido →
recheca convergência/orçamento de tempo → repete" em vez de uma única
retentativa.

### 6. A mesma patente Oracle trata espaçamento entre itens como input de primeira classe

> A patente trata contagem de frentes por produto E localização de
> prateleira como variáveis de decisão explícitas, com contagens
> mínima/máxima de frentes por item/prateleira e **espaçamento entre itens**
> como inputs de primeira classe do otimizador que diretamente parametrizam
> o domínio viável da variável de decisão.

**Confiança: média** (patente única).

**Implicação:** espaçamento/margem entre produtos deveria ser modelado como
um parâmetro de input por item ou global que encolhe a largura útil
alimentada ao empacotador, em vez de deixar sem modelar — diretamente
acionável para a causa-raiz #5 da auditoria (sem conceito de espaçamento/
tolerância de gap).

### 7. Patente Walmart (US11715048) — curva de retornos decrescentes por item, resolvida via GA

> Modela a resposta de contagem-de-frentes→participação-de-demanda como uma
> curva power-law/retornos-decrescentes por item, ajustada via regressão
> linear restrita sobre demanda-log-transformada vs. contagem-de-frentes, e
> resolve a otimização mista-inteira não-linear multi-restrição resultante
> — maximizando receita de categoria total através de todos os itens
> simultaneamente — usando um algoritmo genético em vez de um empacotador
> sequencial greedy/first-fit.

**Confiança: média** (patente única, 3 extrações independentes idênticas).

**Implicação:** se/quando o time quiser que a expansão de frentes reflita
retornos de vendas reais decrescentes (não só "crescer no espaço sobrando
round-robin"), uma curva de elasticidade power-law por item ajustada por
regressão restrita simples é uma técnica leve, já patenteada, que poderia
informar uma função de scoring mais inteligente do `expandFacings` mesmo sem
adotar um GA completo.

### 8. Padrão de duas passagens do cutting-stock 1D com sobra utilizável

> Na literatura relacionada de cutting-stock 1D com sobra utilizável, existe
> um padrão de heurística de duas passagens comprovado: um procedimento de
> programação linear (LP) que satisfaz a maior parte da demanda, seguido de
> um procedimento heurístico sequencial que resolve a demanda restante,
> mais difícil de encaixar.

**Confiança: média.** Fonte: paper primário verificado via abstract + web
search corroborante.

**Implicação:** mapeia limpo para o retrofit do pipeline atual — manter algo
como o passe rápido/greedy atual para a maior parte da prateleira, mas
seguir com uma segunda passagem dedicada e mais estreita (não só expansão de
frentes de itens já posicionados) cujo único trabalho é encaixar o gap
específico restante, potencialmente reconsiderando produtos mais estreitos
previamente rejeitados — exatamente o que o passe de "overflow" atual
pretendia fazer mas não itera.

## Alegações derrubadas (NÃO reutilizar)

Fact-checking adversarial matou 12 das 25 alegações verificadas — incluindo
vários caminhos que pareciam "conserto barato". **Não citar nenhum destes
números/enquadramentos em trabalho futuro:**

1. Heurística clássica de Yang ser "apenas" um first-fit/greedy simples —
   **refutado** (na verdade é formulada como problema de knapsack
   multi-restrição, estruturalmente diferente do first-fit atual).
2. Uma das três heurísticas do OPSEARCH 2023 atingindo 99.59% do bound
   teórico em 320 instâncias — **refutado**.
3. O modelo canônico de SSA ser "exatamente" linear/IP com contagens de
   frentes como únicas variáveis, sem mais contexto — **refutado**
   (simplificação excessiva).
4. Heurísticas do OPSEARCH 2023 "superando" CPLEX em resolvibilidade
   (34 vs 23 instâncias viáveis) ou atingindo 95.25% de razão média de
   lucro — **refutado**.
5. ALNS (adaptive large neighborhood search) atingindo ~22-23% de melhoria
   média sobre construção greedy — **refutado**.
6. Comparação controlada greedy vs. Bottom-Left-Fill vs. GA+BLF isolando
   "melhor heurística de posicionamento" de "melhor busca de ordenação" —
   **refutado** como enquadramento.
7. GA-BLF reduzindo espaço vazio em ~30% vs. greedy puro em datasets de
   benchmark de 16-29 retângulos — **refutado**.
8. A otimização da patente Walmart impor uma restrição de
   igualdade-de-espaço rígida (frentes×largura = footage exato) — **refutado**.
9. Busca local sobre a formulação de Gajjar & Adil gerando soluções
   quase-ótimas para instâncias de até 200 produtos/50 prateleiras em ~1
   minuto de CPU — **refutado** como estava formulado.
10. GA atingindo apenas 78.64% do lucro ótimo do CPLEX em média — **refutado**
    (contradiz o achado #4 confirmado acima; não usar nenhum dos dois
    números extremos, só a comparação mais restrita e confirmada "bate
    CPLEX com tempo limitado em 1.5-3.5% médio").

## Lacunas / perguntas em aberto

- **Nenhuma fonte primária confirmada** descreve o algoritmo real dos
  concorrentes comerciais nomeados (Blue Yonder/JDA Space Planning,
  Symphony RetailAI, Nielsen/IRI Spaceman/Galleria, RELEX, DotActiv,
  Antares, ProSpace) — só as duas patentes (Oracle, Walmart) e papers
  acadêmicos foram confirmados como fontes primárias. Essa parte da
  pergunta original ficou sem resposta.
- Dados de runtime de GA/MILP (média 3.4min, até 15.6min para uma única
  instância de 3-4 prateleiras/10-50 produtos) vêm de hardware acadêmico de
  ~2020, single-threaded (Visual C#/.NET 4.6, CPLEX 12.10) — **não testados
  na escala real de produção** (centenas de prateleiras por run).
  Paralelização por prateleira é inferência de engenharia razoável (as
  prateleiras são majoritariamente independentes uma vez que a ordenação de
  categoria/bloco já está fixada a montante), mas não foi medida em nenhuma
  fonte encontrada.
- Nenhuma fonte tratou explicitamente de precisão sub-cm/mm como técnica
  anti-erro-de-arredondamento — o ponto sobre variáveis de decisão
  contínuas/racionais em formulações estilo MILP é inferido das formulações
  revisadas (que não forçam larguras em cm inteiro), não é uma técnica
  citada explicitamente em nenhuma fonte confirmada.
- Todos os achados quantitativos confirmados são baseados em instâncias de
  benchmark acadêmico pequenas (maior confirmada: 200 produtos/50
  prateleiras num paper, a maioria 10-50 produtos/3-4 prateleiras) ou em
  linguagem de reivindicação de patente (que descreve método
  pretendido/reivindicado, não performance de produção medida
  independentemente). **Performance real na escala de produção do sistema
  auditado permanece não verificada por nenhuma fonte.**

## Fontes (24 fetchadas)

| Qualidade | URL | Ângulo |
|---|---|---|
| primary | rairo-ro.org/.../ro240299.pdf | Formulação acadêmica SSAP |
| primary | researchgate.net/.../Heuristics_for_retail_shelf_space... | Formulação acadêmica SSAP |
| primary | researchgate.net/.../Shelf_space_allocation_in_retailing... | Formulação acadêmica SSAP |
| secondary | researchgate.net/.../A_Narrative_Review_Optimization_Algorithms... | Formulação acadêmica SSAP |
| primary | link.springer.com/article/10.1007/s12597-023-00636-1 | Tradeoffs de bin-packing |
| primary | mdpi.com/2076-3417/11/14/6401 | Tradeoffs de bin-packing |
| primary | arxiv.org/pdf/2001.07709 | Tradeoffs de bin-packing |
| primary | arxiv.org/pdf/1210.4502 | Tradeoffs de bin-packing |
| primary | image-ppubs.uspto.gov/.../8930235 (patente Oracle) | Software comercial / retrofit |
| primary | image-ppubs.uspto.gov/.../11715048 (patente Walmart) | Software comercial |
| primary | link.springer.com/article/10.1007/s10479-008-0455-6 | Fechamento de gap pós-greedy |
| primary | mdpi.com/2227-7390/8/11/1881 | Fechamento de gap pós-greedy |
| primary | sciencedirect.com/.../S0377221709007966 | Fechamento de gap pós-greedy |
| secondary | polettif.github.io/proporz/.../largest_remainder_method | Precisão de arredondamento |
| primary | chairelogistique.hec.ca/.../NEBPP.pdf | Precisão de arredondamento |
| secondary | researchgate.net/.../SSAP_...Systematic_Literature_Review | Padrão de retrofit |
| primary | mdpi.com/2073-8994/13/2/314 | Padrão de retrofit |

(fontes marcadas `unreliable` — sciencedirect.com sem acesso completo,
dotactiv.com knowledge-base, eprints.nottingham.ac.uk — foram descartadas,
0 alegações extraídas de cada).

## Síntese direta para decisão de arquitetura

Manter a ordenação/ranking de candidatos que já existe a montante (categoria/
ABC/zona) — nada encontrado contradiz ranking como input válido — e
substituir **só o passo final de empacotamento por prateleira** por um
pequeno solve de knapsack/IP limitado, onde a única variável inteira livre
por item é a contagem de frentes dentro de `[min, max]` — tratável em
5-40 itens/prateleira via branch-and-bound ou mesmo DP exaustivo, rodando uma
vez por prateleira (rápido, paralelizável entre as "centenas de
prateleiras"). Reservar um GA ou o loop alternado RS↔MILP da patente só para
um modo mais lento e opcional de "reotimização profunda" (ex.: passe
completo noturno de gôndola), não o caminho interativo/por-geração, dado seu
custo de multi-minutos mesmo em pequena escala.

Ver detalhamento em fases em [03-plano-implementacao.md](03-plano-implementacao.md).

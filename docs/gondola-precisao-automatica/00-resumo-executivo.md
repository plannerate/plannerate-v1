# Precisão da Geração Automática de Gôndola — Resumo Executivo

> **Status:** análise + plano concluídos. **Nada foi implementado ainda.**
> Aguardando aprovação para iniciar pela Fase 0 (ver [03-plano-implementacao.md](03-plano-implementacao.md)).

## O problema

O usuário relatou: *"não estamos conseguindo fechar a gôndola com precisão"* — a
geração automática/por template (`packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/`)
deixa espaço vazio (ou ocasionalmente estoura) nas prateleiras em vez de
preenchê-las com exatidão.

## O que já foi feito para investigar

1. **Auditoria completa do código atual** (fluxo automático e template, motor de
   posicionamento, expansão de frentes, passe de overflow, validação) —
   ver [01-auditoria-codigo-atual.md](01-auditoria-codigo-atual.md).
2. **Pesquisa externa aprofundada** (deep-research: 6 ângulos de busca, 24 fontes
   primárias fetchadas, 82 alegações extraídas, 25 verificadas
   adversarialmente 3-votos-por-alegação — 13 confirmadas, 12 derrubadas) sobre
   algoritmos de bin-packing/cutting-stock, o "Shelf Space Allocation Problem"
   (SSAP) na literatura de pesquisa operacional, patentes industriais (Oracle,
   Walmart) e técnicas de fechamento de gaps —
   ver [02-pesquisa-externa.md](02-pesquisa-externa.md).
3. **Plano de implementação em fases**, incorporando uma decisão de produto que
   o usuário confirmou explicitamente —
   ver [03-plano-implementacao.md](03-plano-implementacao.md).

## TL;DR da causa-raiz

**Não é um bug pontual — é uma limitação arquitetural já admitida no próprio
código.** O motor de posicionamento (`TemplatePlacementEngine`/`GreedyShelfPlacer`)
é um **first-fit sequencial de passagem única, sem backtracking**: percorre os
candidatos ranqueados uma vez, encaixa cada um no `min_facings` se couber, e
rejeita definitivamente se não couber. Nunca reconsidera a decisão, nunca tenta
menos frentes, nunca troca um produto já colocado por uma combinação mais
justa.

A prova está em [`AutoTemplateSynthesisOrchestrator.php:39-50`](../../packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/Synthesis/AutoTemplateSynthesisOrchestrator.php#L39-L50):
uma constante `SHELF_FILL_RATE_ESTIMATE = 0.75` com comentário explícito
admitindo ocupação típica de 70–80% da largura da prateleira — e o código
**compensa encolhendo a largura estimada**, em vez de consertar o empacotador.

A pesquisa confirma que isso é exatamente o SSAP estudado há décadas em
pesquisa operacional, e que a solução padrão é resolver min/max-facings +
capacidade da prateleira como um problema de otimização conjunta (knapsack/DP
ou IP) por prateleira — não como um scan único que "congela" decisões.

## Decisão de produto confirmada com o usuário

> *"um ponto que quero deixar claro: se a geração automática tiver que demorar,
> para gerar uma gôndola sem problema, se tiver que fazer, refazer, pode ser.
> Podemos usar filas, e avisar quando terminar. Mas temos que planejar salvar
> para futuras consultas."*

Ou seja: **precisão > velocidade**. Um motor de empacotamento exato (DP/knapsack)
por prateleira, com um loop de convergência que reitera até parar de melhorar
ou bater um orçamento de tempo, é aceitável mesmo que demore mais que o
request HTTP síncrono atual — **desde que rode em fila, notifique quando
terminar, e fique salvo para consulta futura** (comparar execuções, auditar o
que mudou, reabrir o relatório depois).

Boa notícia: o projeto **já tem esse padrão de fila+notificação pronto e em
produção** para relatórios de gôndola
(ver [`docs/relatorios-em-fila.md`](../relatorios-em-fila.md)) — `ShouldQueue` +
`TenantAware` + `AppNotification` (database + broadcast/Reverb) +
`NotificationsDropdown.vue`. O plano reaproveita 100% dessa infraestrutura,
apenas trocando "arquivo para baixar" por "relatório de execução para
consultar".

## Estrutura desta pasta

| Arquivo | Conteúdo |
|---|---|
| [00-resumo-executivo.md](00-resumo-executivo.md) | Este documento |
| [01-auditoria-codigo-atual.md](01-auditoria-codigo-atual.md) | Mapeamento do fluxo atual + 9 causas-raiz com `file:line` |
| [02-pesquisa-externa.md](02-pesquisa-externa.md) | Síntese da pesquisa (achados confirmados/derrubados, fontes, lacunas) |
| [03-plano-implementacao.md](03-plano-implementacao.md) | Plano em fases: arquitetura assíncrona + motor de empacotamento preciso |

## Próximo passo

Aprovar o início pela **Fase 0** (fundação assíncrona: job + persistência de
execuções + notificação) e **Fase 1** (correções rápidas de precisão), que são
a base de baixo risco sobre a qual a Fase 2 (motor de empacotamento exato) é
construída. Ver critérios de pronto de cada fase em
[03-plano-implementacao.md](03-plano-implementacao.md).

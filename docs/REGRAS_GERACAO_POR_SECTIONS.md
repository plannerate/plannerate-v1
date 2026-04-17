# Regras de Geracao por Sections

## Objetivo
Documentar de forma direta como a geracao por section funciona hoje e quais melhorias trazem mais impacto no resultado de alocacao.

## Contexto de Execucao
- Endpoint: `POST /api/tenant/plannerate/gondolas/{gondola}/generate-by-sections`
- Entrada validada por: `AutoGeneratePlanogramRequest`
- Orquestracao: `SectionPlanogramService::generateBySections`
- Alocacao por regras: `SectionRulesAllocator` + `MerchandisingRulesService`

## Regras de Geracao (Pipeline)
1. Carregar contexto
- Configura conexao tenant pelo cliente atual.
- Busca gondoIa com `sections.shelves`.
- Busca planograma da gondoIa.

2. Selecionar produtos candidatos
- Categoria usada: `category_id` do request, ou fallback para categoria do planograma.
- Busca categorias descendentes via CTE recursiva.
- Carrega produtos dessas categorias.

3. Calcular ranking dos produtos
- Tabela de vendas: `sales` ou `monthly_sales_summaries`.
- Periodo: `start_date/end_date`; sem periodo, usa intervalo do planograma no ano anterior.
- Score por estrategia:
- `abc`: 100% ABC.
- `sales`: 100% vendas normalizadas.
- `margin`: 100% margem normalizada.
- `mix`: 40% ABC + 40% vendas + 20% margem.
- Filtro opcional: remove produtos sem venda quando `include_products_without_sales=false`.

4. Processar section por section
- A ordem de processamento segue a ordem carregada de `gondola->sections`.
- Para cada section, executa:
- alocador IA se `use_ai=true` e `SectionAIAllocator` disponivel;
- senao alocador por regras.

5. Evitar duplicacao entre sections
- Apos persistir uma section, remove produtos alocados da lista restante.
- Produto alocado em uma section nao concorre nas proximas.

6. Persistir resultado por section
- Limpa segmentos da section atual.
- Cria 1 `Segment` por alocacao e 1 `Layer` com `quantity = facings`.
- Persistencia em transacao.

7. Fechamento
- `totalUnallocated` final = quantidade restante depois de todas as sections.

## Regras de Merchandising (Modo Regras)
1. Normalizacao de shelf
- Shelves ordenadas por `shelf_position`.
- Altura util da shelf usa gap para proxima shelf multiplicado por `scale_factor`.
- Largura da shelf pode ser substituida pela largura da section quando medida vier baixa/invalida.

2. Calculo de facings
- Base ABC: `A=3`, `B=2`, `C=1`, default `1`.
- Ajuste por `target_stock` quando disponivel.
- Ajuste por vendas com curva `sqrt`.
- Resultado final limitado por `min_facings` e `max_facings`.

3. Agrupamento de sortimento
- Se `group_by_subcategory=true`, processa em grupos de subcategoria.
- Se `false`, processa todos juntos.

4. Escolha de prateleira ideal
- Faixa por ABC:
- `A`: 60%-90% (mais alto)
- `B`: 30%-60%
- `C`: 5%-30%
- Sem ABC: 0%-20%
- Dentro da faixa, score relativo maior sobe o indice da shelf.

5. Regra de encaixe
- Produto so entra se altura do produto couber na shelf.
- Produto so entra se houver largura util disponivel.
- Se falhar na shelf ideal, tenta shelves vizinhas por distancia crescente.
- Se nao couber em nenhuma, vira nao alocado.

## Parametros de Entrada que Mais Influenciam Resultado
- `strategy`: muda completamente a ordenacao de prioridade.
- `min_facings` e `max_facings`: definem agressividade de ocupacao por SKU.
- `group_by_subcategory`: melhora bloco visual, mas pode reduzir taxa de encaixe em sections estreitas.
- `include_products_without_sales`: aumenta cobertura, reduz foco em giro.
- `table_type` e periodo: alteram base de score e sazonalidade.
- `use_ai`: troca mecanismo de decisao da section quando IA estiver disponivel.

## Limitacoes Atuais
### Criticas (Impacto Alto)
- ~~**Campo `flow` da gondola nao e considerado**~~ **[RESOLVIDO]**: Position_x calculado, ordering invertido quando RTL, e produtos ranqueados invertidos para hot zone correta.
- ~~**Position_x e position_y nao sao calculados**~~ **[RESOLVIDO]**: Position_x agora e calculado acumulando largura × facings.
- ~~**Sections iniciais monopolizam top SKUs**~~ **[RESOLVIDO]**: Sections agora sao processadas por capacidade (maior primeiro), reduzindo monopolizacao.

### Importantes (Impacto Medio)
- Regra de visibilidade usa ABC/score, mas sem ponderacao por margem por cm de frente.
- Nao ha rebalanceamento no final para reduzir concentracao por section.
- Sem metrica formal de qualidade (fill rate, fairness por section, dispersao por subcategoria) persistida apos geracao.

## Melhorias Implementadas
### ✅ Sprint 0 (Implementado - Fev/2026)
1. **Calculo de position_x com flow da gondola** ✅
   - `SectionPersistenceService` calcula position_x acumulando largura × facings.
   - Inverte ordering quando `flow=right_to_left`.
   - Campo `Segment.position` persiste posicao horizontal.
   - **Commits**: Ver `SectionPersistenceService::saveAllocation()`.

2. **Validacao de profundidade (BONUS)** ✅
   - `ShelfLayoutDTO` valida `product.depth <= shelf.depth` antes de alocar.
   - Evita produtos que excedem profundidade fisica da prateleira.
   - `SectionAllocationItemDTO` carrega dimensoes completas (width/depth/height).
   - **Commits**: Ver `ShelfLayoutDTO::addProduct()` e DTOs relacionados.

### ✅ Sprint 1 - Quick Wins (Implementado - Mar/2026)
3. **Hot zone por flow da gondola** ✅
   - `SectionRulesAllocator` inverte lista de produtos ranqueados quando `flow=right_to_left`.
   - Produtos de maior score começam na área de maior visibilidade (direita para RTL, esquerda para LTR).
   - Log de debug identifica quando inversão ocorre.
   - **Commits**: Ver `SectionRulesAllocator::allocate()`.

4. **Reordenação de sections por capacidade** ✅
   - `SectionPlanogramService` ordena sections por capacidade (largura × altura) DECRESCENTE.
   - Sections maiores processam primeiro, tendo acesso aos produtos de maior score.
   - Reduz monopolização de top SKUs por sections pequenas processadas cedo.
   - **Commits**: Ver `SectionPlanogramService::generateBySections()`.

### ✅ Sprint 2 - Balanceamento (Implementado - Mar/2026)
5. **Cota dinâmica por section antes da alocação** ✅
    - `SectionPlanogramService` calcula uma cota proporcional por capacidade para cada section.
    - A cota reserva produtos para sections restantes, evitando consumo total na primeira section.
    - A última section recebe todos os produtos remanescentes.
    - **Commits**: Ver `SectionPlanogramService::calculateSectionQuota()`.

6. **Métricas de qualidade da geração** ✅
    - `SectionPlanogramService` calcula `fill_rate`, `unallocated_rate`,
      `allocation_concentration_rate` e `average_quota_utilization_rate`.
     - Inclui `unallocated_by_reason_attempts` e `unallocated_by_reason_unique`
         com diagnóstico de rejeição:
         `height_exceeded`, `depth_exceeded`, `width_exceeded`, `no_shelf_found` e `unknown`.
     - Inclui `sectionDiagnostics` com detalhamento por módulo:
         quota, alocados, não alocados e taxa de utilização da quota.
     - Inclui métricas de spillover:
         `split_candidates`, `split_resolved`, `split_failed`, `split_resolution_rate`.
    - Métricas retornam no `SectionGenerateResultDTO::qualityMetrics`.
    - `AutoPlanogramController` exibe resumo das métricas no flash de sucesso.

### ✅ Refinamento: Spillover de Facings (Implementado - Mar/2026)
**Problema confirmado**
- Rejeições altas por `width_exceeded` mesmo com espaço somado disponível em múltiplas shelves.
- Causa: algoritmo antigo exigia que todos os facings coubessem em uma única prateleira.

**Solução aplicada**
- `SectionRulesAllocator` agora tenta plano de split de facings entre prateleiras elegíveis
    (respeitando altura/profundidade e ordem de proximidade da shelf ideal).
- Se o split completo for viável, a alocação é aplicada automaticamente.
- Se não for viável, mantém regra atual e registra motivo de rejeição.

**Evidência no JSON**
- `qualityMetrics.split_candidates`: casos em que split era candidato.
- `qualityMetrics.split_resolved`: casos resolvidos via split.
- `qualityMetrics.split_resolution_rate`: eficiência da estratégia.
- `sectionDiagnostics[].split_*`: diagnóstico por módulo.

## Sugestoes de Melhoria (Priorizadas)
### Prioridade ALTA
1. ~~**Introduzir cota dinamica por section antes da alocacao**~~ ✅ **IMPLEMENTADO**

### Prioridade MEDIA
2. **Criar etapa de rebalanceamento apos primeira passada**
- Trocar SKUs entre sections para melhorar cobertura sem piorar score total.
- **Impacto esperado**: Diminui nao alocados e melhora distribuicao do sortimento.
- **Complexidade**: Alta (1 semana).

3. **Evoluir score para "retorno por espaco"**
- Combinar score atual com largura ocupada (ex.: margem por cm frontal).
- **Impacto esperado**: Melhor rentabilidade por metro linear.
- **Complexidade**: Media (2-3 dias).

4. **Tornar pesos e faixas de ABC configuraveis por tenant/categoria**
- Externalizar `A/B/C` ranges e pesos de `mix` em configuracao.
- **Impacto esperado**: Adaptacao por formato de loja e categoria.
- **Complexidade**: Media (3-4 dias).

5. ~~**Persistir metricas de qualidade da geracao**~~ ✅ **IMPLEMENTADO (retorno + log)**
- Métricas já calculadas e retornadas no DTO/log.
- Próximo passo opcional: persistência em tabela histórica para analytics.

## Plano Curto de Implementacao (Sugestao)
### ~~Sprint 1 (Quick Wins - 1 semana)~~ ✅ CONCLUÍDO
1. ~~Implementar position_x e respeitar flow da gondola.~~ ✅
2. ~~Reordenar sections por capacidade antes de processar.~~ ✅
3. ~~Adicionar telemetria basica (log de nao alocados por section).~~ ✅

### Sprint 2 (Melhorias de Qualidade - 2 semanas)
4. ~~Implementar cota dinamica por section.~~ ✅ CONCLUÍDO
5. ~~Persistir metricas de qualidade no banco.~~ ✅ CONCLUÍDO (retorno + log)
6. ~~Ajustar hot zone baseado em flow.~~ ✅ CONCLUÍDO

### Sprint 3 (Otimizacao Avancada - 3 semanas)
7. Implementar rebalanceamento pos-alocacao.
8. Evoluir score para retorno por espaco.
9. Tornar pesos ABC configuraveis.

## Referencias de Codigo
- `app/Http/Controllers/Tenant/Plannerate/AutoPlanogramController.php`
- `app/Http/Requests/Tenant/Plannerate/AutoGeneratePlanogramRequest.php`
- `app/Services/Plannerate/SectionGenerate/SectionPlanogramService.php`
- `app/Services/Plannerate/SectionGenerate/SectionRulesAllocator.php`
- `app/Services/Plannerate/SectionGenerate/SectionPersistenceService.php`
- `app/Services/Plannerate/AutoGenerate/ProductSelectionService.php`
- `app/Services/Plannerate/AutoGenerate/MerchandisingRulesService.php`

## Exemplo de Implementacao (Flow + Position_X)
### 1. Adicionar campo flow ao DTO de Alocacao
```php
// SectionAllocationItemDTO.php
class SectionAllocationItemDTO
{
    public function __construct(
        public string $shelfId,
        public string $productId,
        public int $facings,
        public float $productWidth = 0,  // Adicionar
        public int $shelfOrder = 0,      // Adicionar
    ) {}
}
```

### 2. Modificar SectionPersistenceService para calcular position_x
```php
public function saveAllocation(Section $section, SectionAllocationResultDTO $result): int
{
    $section->loadMissing('shelf.section.gondola');
    $gondolaFlow = $section->gondola->flow ?? 'left_to_right';
    
    $positionByShelf = [];
    $orderingByShelf = [];
    
    DB::transaction(function () use ($result, $gondolaFlow, &$positionByShelf, &$orderingByShelf, &$created) {
        foreach ($result->allocation as $item) {
            $shelfId = $item->shelfId;
            
            // Calcular position_x acumulada
            $currentX = $positionByShelf[$shelfId] ?? 0;
            $productWidth = $item->productWidth * $item->facings;
            
            // Se flow right_to_left, processar ao contrario no final
            $order = $orderingByShelf[$shelfId] ?? 0;
            $orderingByShelf[$shelfId] = $order + 1;
            
            $segment = Segment::create([
                'id' => (string) Str::ulid(),
                'shelf_id' => $shelfId,
                'quantity' => 1,
                'ordering' => $order,
                'position' => (int) $currentX,  // position_x em cm
            ]);
            
            // ... criar layer ...
            
            $positionByShelf[$shelfId] = $currentX + $productWidth;
            $created++;
        }
    });
    
    // Se flow=right_to_left, inverter ordering de cada shelf
    if ($gondolaFlow === 'right_to_left') {
        $this->reverseShelfOrdering($section);
    }
    
    return $created;
}

protected function reverseShelfOrdering(Section $section): void
{
    $section->loadMissing('shelves.segments');
    
    foreach ($section->shelves as $shelf) {
        $segments = $shelf->segments()->orderBy('ordering')->get();
        $maxOrder = $segments->count() - 1;
        
        foreach ($segments as $index => $segment) {
            $segment->update(['ordering' => $maxOrder - $index]);
        }
    }
}
```

### 3. Modificar SectionRulesAllocator para passar largura do produto
```php
protected function shelvesToAllocationItems(array $shelves): array
{
    $items = [];
    foreach ($shelves as $layout) {
        $orderInShelf = 0;
        foreach ($layout->products as $ranked) {
            $items[] = new SectionAllocationItemDTO(
                shelfId: $layout->id,
                productId: $ranked->product->id,
                facings: $ranked->facings,
                productWidth: (float) ($ranked->product->width ?? 10),
                shelfOrder: $orderInShelf++,
            );
        }
    }
    return $items;
}
```

<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Leitura das métricas de produto que vivem em `product_store` por serem POR LOJA.
 *
 * `current_stock` e `last_purchase_date` chegam do feed do ERP já recortadas pela
 * unidade consultada: o estoque é o daquela loja e a última compra é a daquela
 * filial. Como o id do produto deriva de tenant+ean — sem loja —, guardar o valor
 * em `products` fazia a última cadeia de importação a terminar sobrescrever as
 * demais (medido na RP Info: 67 de 100 produtos com estoque diferente entre Matriz
 * e Filial). A importação passou a gravar só na pivot; estes scopes são o caminho
 * de leitura.
 *
 * O alias do subselect REPETE o nome da coluna de propósito. O SELECT vira
 * `select products.*, (subselect) as current_stock` e o driver mantém a última
 * ocorrência de cada nome, então `$product->current_stock` já devolve o valor da
 * loja sem que nenhum leitor, payload ou componente Vue precise mudar — e os casts
 * do model (`last_purchase_date` => date) continuam valendo, porque o atributo
 * continua se chamando `last_purchase_date`.
 *
 * Onde a query já lista colunas explicitamente, retire as duas da lista e chame o
 * scope DEPOIS do `select()`: `select()` zera as colunas acumuladas e levaria os
 * subselects junto.
 *
 * Sem linha na pivot o valor é nulo — de propósito. Blueprint que ainda gravar
 * essas métricas em `products` precisa migrar para `pivot_only_targets` +
 * `update_columns` (ver `.claude/nova-integracao.md`), não ganhar fallback aqui.
 */
trait HasStoreScopedMetrics
{
    /**
     * Estoque e última compra da loja informada.
     *
     * `$storeId` nulo cai em `withStoreMetrics()`: sem loja no contexto, o número
     * com significado é o consolidado do tenant, não o de uma loja arbitrária.
     */
    public function scopeForStore(Builder $query, ?string $storeId): Builder
    {
        if ($storeId === null || $storeId === '') {
            return $this->scopeWithStoreMetrics($query);
        }

        return $query->addSelect([
            'current_stock' => $this->storeMetricSubquery($query, 'current_stock', $storeId),
            'last_purchase_date' => $this->storeMetricSubquery($query, 'last_purchase_date', $storeId),
        ]);
    }

    /**
     * Consolidado de todas as lojas: soma do estoque e a compra mais recente.
     *
     * Para os leitores que não têm loja no contexto — a listagem de produtos, que
     * é tenant-wide, e o review de slot de template, que não é por loja.
     */
    public function scopeWithStoreMetrics(Builder $query): Builder
    {
        return $query->addSelect([
            'current_stock' => $this->pivotQuery($query)
                ->selectRaw('sum(product_store.current_stock)'),
            'last_purchase_date' => $this->pivotQuery($query)
                ->selectRaw('max(product_store.last_purchase_date)'),
        ]);
    }

    private function storeMetricSubquery(Builder $query, string $column, string $storeId): QueryBuilder
    {
        return $this->pivotQuery($query)
            ->select('product_store.'.$column)
            ->where('product_store.store_id', $storeId)
            ->limit(1);
    }

    private function pivotQuery(Builder $query): QueryBuilder
    {
        return $query->getQuery()
            ->newQuery()
            ->from('product_store')
            ->whereColumn('product_store.product_id', $this->qualifyColumn('id'));
    }
}

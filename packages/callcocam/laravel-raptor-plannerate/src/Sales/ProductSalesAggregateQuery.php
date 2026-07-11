<?php

namespace Callcocam\LaravelRaptorPlannerate\Sales;

use Callcocam\LaravelRaptorPlannerate\Models\MonthlySalesSummary;
use Callcocam\LaravelRaptorPlannerate\Models\Sale;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Builder do padrão de agregação "vendas por produto" compartilhado pelas análises
 * que somam vendas agrupadas por produto (ABC e Paper).
 *
 * Centraliza o plumbing que estava duplicado entre os pares getSalesQuery /
 * getMonthlySummariesQuery de cada service: seleção da fonte (sales vs
 * monthly_sales_summaries), o join em products por codigo_erp (sem global scopes),
 * os filtros de codigo_erp/product_id/loja e o agrupamento por produto.
 *
 * O filtro de PERÍODO fica fora de propósito: cada análise usa colunas/chaves e
 * transformações próprias (sale_date com date_from/to; sale_month com month_from/to
 * ou start_month/end_month transformados). Use dateColumn()/applyPeriod() para
 * aplicá-lo de forma consistente sem hardcodar o nome da tabela.
 */
class ProductSalesAggregateQuery
{
    /** Fonte de dados agregados (sumários mensais). */
    public const SOURCE_MONTHLY = 'monthly_summaries';

    /**
     * @param  class-string<Sale|MonthlySalesSummary>  $modelClass
     */
    private function __construct(
        private readonly string $modelClass,
        private readonly string $table,
        private readonly string $dateColumn,
    ) {}

    /**
     * Resolve a fonte a partir do tableType usado pelas análises.
     * 'monthly_summaries' → monthly_sales_summaries (coluna de período: sale_month);
     * qualquer outro valor → sales (coluna de período: sale_date).
     */
    public static function for(string $tableType): self
    {
        return $tableType === self::SOURCE_MONTHLY
            ? new self(MonthlySalesSummary::class, 'monthly_sales_summaries', 'sale_month')
            : new self(Sale::class, 'sales', 'sale_date');
    }

    /**
     * Nome físico da tabela de origem (para montar expressões SQL sem hardcode).
     */
    public function table(): string
    {
        return $this->table;
    }

    /**
     * Coluna de data da fonte (sale_date para sales, sale_month para sumários).
     */
    public function dateColumn(): string
    {
        return $this->dateColumn;
    }

    /**
     * Expressão SUM(<tabela>.<coluna>) as <alias> — vocabulário único das somas.
     */
    public function sum(string $column, string $alias): Expression
    {
        return DB::raw("SUM({$this->table}.{$column}) as {$alias}");
    }

    /**
     * Query base agrupada por produto: join em products por codigo_erp (sem global
     * scopes), seleção de identificação do produto, filtros de codigo_erp/product_id/
     * loja e agrupamento por produto/categoria. O chamador adiciona seus próprios
     * agregados via addSelect() e o filtro de período via applyPeriod().
     *
     * @param  array<int, string>  $codigosErp
     * @param  array<int, string>  $productIds
     * @param  array<string, mixed>  $filters
     * @return Builder<Sale|MonthlySalesSummary>
     */
    public function groupedByProduct(array $codigosErp, array $productIds, array $filters): Builder
    {
        /** @var Builder<Sale|MonthlySalesSummary> $query */
        $query = $this->modelClass::query()
            ->withoutGlobalScopes()
            ->join('products', 'products.codigo_erp', '=', "{$this->table}.codigo_erp")
            ->select([
                'products.id as product_id',
                'products.category_id',
            ])
            ->whereIn("{$this->table}.codigo_erp", $codigosErp)
            ->whereIn('products.id', $productIds)
            ->groupBy('products.id', 'products.category_id');

        if (isset($filters['store_id'])) {
            $query->where("{$this->table}.store_id", $filters['store_id']);
        }

        return $query;
    }

    /**
     * Resolve o período mensal a partir dos filtros, aceitando as DUAS convenções que
     * convivem no sistema:
     *
     *  - `month_from` / `month_to`: limites já em data completa (Y-m-d). É o que o
     *    auto-planograma manda (ProductSelectionService::computeAbcOnTheFly).
     *  - `start_month` / `end_month`: mês no formato Y-m. É o que a UI manda (o
     *    <input type="month"> das telas de análise).
     *
     * A convenção explícita vence, porque é a mais específica; na prática as duas
     * nunca chegam juntas. O mês em Y-m vira o intervalo fechado do mês inteiro
     * (2026-01 → 2026-01-01 .. 2026-01-31), já que `sale_month` é uma coluna date.
     *
     * Sem isso, quem manda só `start_month`/`end_month` era ignorado em silêncio e a
     * análise somava TODOS os meses da base.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: ?string, 1: ?string} [from, to] em Y-m-d, ou null
     */
    public static function monthPeriod(array $filters): array
    {
        $from = self::blankToNull($filters['month_from'] ?? null);
        $to = self::blankToNull($filters['month_to'] ?? null);

        if ($from === null && ($startMonth = self::blankToNull($filters['start_month'] ?? null)) !== null) {
            $from = Carbon::createFromFormat('Y-m', $startMonth)->startOfMonth()->format('Y-m-d');
        }

        if ($to === null && ($endMonth = self::blankToNull($filters['end_month'] ?? null)) !== null) {
            $to = Carbon::createFromFormat('Y-m', $endMonth)->endOfMonth()->format('Y-m-d');
        }

        return [$from, $to];
    }

    /**
     * Normaliza filtro ausente/vazio para null — string vazia não é período.
     */
    private static function blankToNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }

    /**
     * Aplica o filtro de período (limites inclusivos) na coluna de data da fonte.
     * Cada limite é aplicado quando não-nulo — preservando a semântica de isset()
     * dos services originais (string vazia ainda aplica o filtro).
     *
     * @param  Builder<Sale|MonthlySalesSummary>  $query
     */
    public function applyPeriod(Builder $query, ?string $from, ?string $to): void
    {
        if ($from !== null) {
            $query->where("{$this->table}.{$this->dateColumn}", '>=', $from);
        }

        if ($to !== null) {
            $query->where("{$this->table}.{$this->dateColumn}", '<=', $to);
        }
    }
}

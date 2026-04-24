<?php

namespace Callcocam\LaravelRaptorPlannerate\Models\Editor;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model para sumarização mensal de vendas
 *
 * Representa a agregação mensal dos dados da tabela 'sales'.
 * Mantém a mesma estrutura do modelo Sale para compatibilidade.
 *
 * Campos numéricos contêm a SOMA dos valores do mês:
 * - acquisition_cost: soma dos custos de aquisição
 * - sale_price: soma dos preços de venda
 * - total_profit_margin: soma das margens de lucro
 * - total_sale_quantity: soma das quantidades vendidas
 * - total_sale_value: soma dos valores totais de venda
 *
 * As vendas são agregadas separadamente por promoção (S/N)
 */
class MonthlySalesSummary extends Model
{
    use BelongsToTenant, HasFactory, HasUlids, SoftDeletes;

    /**
     * Nome da tabela
     */
    protected $table = 'monthly_sales_summaries';

    /**
     * Campos que podem ser preenchidos em massa
     */
    protected $fillable = [
        'tenant_id',
        'store_id',
        'product_id',
        'ean',
        'codigo_erp',
        'acquisition_cost',
        'sale_price',
        'total_profit_margin',
        'sale_month',
        'promotion',
        'total_sale_quantity',
        'total_sale_value',
        'margem_contribuicao',
        'extra_data',
    ];

    /**
     * Casts dos atributos
     */
    protected $casts = [
        'extra_data' => 'array',
        'sale_month' => 'date',
        'acquisition_cost' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'total_profit_margin' => 'decimal:2',
        'total_sale_quantity' => 'integer',
        'total_sale_value' => 'decimal:2',
        'margem_contribuicao' => 'decimal:2', // Soma das margens do período
    ];

    /**
     * Atributos adicionados automaticamente
     */
    protected $appends = [
        'margin_sale',
        'impostos_sale',
        'custo_medio_loja',
        'custo_medio_geral',
        'preco_efetivo',
    ];

    /**
     * Calcula a margem de venda agregada do mês
     *
     * Fórmula: Preço efetivo - Custo médio geral - Impostos = Margem
     */
    public function getMarginSaleAttribute()
    {
        // Evita divisão por zero
        if ($this->total_sale_quantity == 0) {
            return [
                'preco_efetivo' => 0,
                'custo_medio_geral' => 0,
                'impostos' => 0,
                'margin' => 0,
            ];
        }

        $preco_efetivo = $this->total_sale_value / $this->total_sale_quantity;
        $custo_medio_geral = data_get($this->extra_data, 'custo_medio_loja', 0) / $this->total_sale_quantity;
        $impostos = data_get($this->extra_data, 'valor_impostos', 0) / $this->total_sale_quantity;

        return [
            'preco_efetivo' => round($preco_efetivo, 2),
            'custo_medio_geral' => round($custo_medio_geral, 2),
            'impostos' => round($impostos, 2),
            'margin' => round($preco_efetivo - $custo_medio_geral - $impostos, 2),
        ];
    }

    /**
     * Retorna os impostos agregados do mês
     */
    public function getImpostosSaleAttribute()
    {
        return data_get($this->extra_data, 'valor_impostos', 0);
    }

    /**
     * Retorna o custo médio da loja agregado do mês
     */
    public function getCustoMedioLojaAttribute()
    {
        return data_get($this->extra_data, 'custo_medio_loja', 0);
    }

    /**
     * Retorna o custo médio geral agregado do mês
     */
    public function getCustoMedioGeralAttribute()
    {
        return data_get($this->extra_data, 'custo_medio_geral', 0);
    }

    /**
     * Retorna o preço efetivo agregado do mês
     */
    public function getPrecoEfetivoAttribute()
    {
        return data_get($this->extra_data, 'preco_efetivo', 0);
    }

    /**
     * Relacionamento com Produto
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relacionamento com Loja
     */
    // Cross-database relationships - use custom methods instead of Eloquent relations

    public function getStoreAttribute()
    {
        if (! $this->store_id) {
            return null;
        }

        return cache()->remember("store:{$this->store_id}", 3600, function () {
            return DB::connection(config('raptor.database.landlord_connection_name', 'landlord'))
                ->table('stores')
                ->where('id', $this->store_id)
                ->first();
        });
    }

    /**
     * Relacionamento com Tenant (substitui client - cada tenant tem seu próprio banco)
     */
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    /**
     * Relacionamento com Usuário
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Desabilita geração de slug
     */
    protected function slugTo()
    {
        return false;
    }

    /**
     * Scope: Filtrar por mês específico
     *
     * @param  Builder  $query
     * @param  string  $month  Formato: YYYY-MM
     * @return Builder
     */
    public function scopeByMonth($query, string $month)
    {
        return $query->whereRaw('DATE_FORMAT(sale_month, "%Y-%m") = ?', [$month]);
    }

    /**
     * Scope: Filtrar por range de meses
     *
     * @param  Builder  $query
     * @param  string  $from  Formato: YYYY-MM
     * @param  string  $to  Formato: YYYY-MM
     * @return Builder
     */
    public function scopeByMonthRange($query, string $from, string $to)
    {
        return $query->whereBetween('sale_month', [
            $from.'-01',
            $to.'-01',
        ]);
    }

    /**
     * Scope: Filtrar vendas promocionais
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopePromotional($query)
    {
        return $query->where('promotion', 'S');
    }

    /**
     * Scope: Filtrar vendas não promocionais
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeNonPromotional($query)
    {
        return $query->where('promotion', 'N');
    }

    /**
     * Scope: Filtrar por ano
     *
     * @param  Builder  $query
     * @param  int  $year  Ano (ex: 2025)
     * @return Builder
     */
    public function scopeByYear($query, int $year)
    {
        return $query->whereYear('sale_month', $year);
    }
}

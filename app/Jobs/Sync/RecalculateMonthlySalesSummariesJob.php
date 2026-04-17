<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Jobs\Sync;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class RecalculateMonthlySalesSummariesJob implements ShouldQueue
{
    use \App\Concerns\BelongsToConnection, Queueable;

    /**
     * Número de tentativas em caso de falha
     */
    public int $tries = 3;

    /**
     * Timeout em segundos
     */
    public int $timeout = 600; // 10 minutos

    /**
     * Create a new job instance.
     *
     * @param  Client  $client  Instância do client
     */
    public function __construct(
        public Client $client,
        public $chunk

    ) {}

    /**
     * Executa o job
     */
    public function handle(): void
    {
        $this->setupClientConnection($this->client);
        
        $salesIds = collect($this->chunk)->pluck('id')->toArray();
        $aggregated = $this->aggregateSales($this->client->id, $salesIds);
        $this->insertSummaries($aggregated);
    }

    /**
     * Agrega dados da tabela sales por mês
     */
    private function aggregateSales(?string $clientId, array $salesIds = [], ?string $month = null)
    {
        $query = DB::connection($this->getClientConnection())->table('sales')
            ->select([
                'tenant_id',
                'client_id',
                'store_id',
                'codigo_erp',
                'ean',
                'product_id',
                'promotion',
                DB::raw("DATE_TRUNC('month', sale_date)::date as sale_month"),
                DB::raw('SUM(acquisition_cost) as acquisition_cost'),
                DB::raw('SUM(sale_price) as sale_price'),
                DB::raw('SUM(total_profit_margin) as total_profit_margin'),
                DB::raw('CAST(SUM(total_sale_quantity) AS INTEGER) as total_sale_quantity'),
                DB::raw('SUM(total_sale_value) as total_sale_value'),
                DB::raw('SUM(margem_contribuicao) as margem_contribuicao'),
                DB::raw('MAX(extra_data) as extra_data_sample'),
            ])
            ->whereNotNull('sale_date')
            ->whereNotNull('codigo_erp')
            ->groupBy([
                'tenant_id',
                'client_id',
                'store_id',
                'codigo_erp',
                'ean',
                'product_id',
                'promotion',
                DB::raw("DATE_TRUNC('month', sale_date)"),
            ]);

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        if (! empty($salesIds)) {
            $query->whereIn('id', $salesIds);
        }

        if ($month) {
            $query->whereRaw("DATE_TRUNC('month', sale_date)::date = ?", ["{$month}-01"]);
        }

        return $query->get();
    }

    /**
     * Insere os dados agregados na tabela monthly_sales_summaries
     */
    private function insertSummaries($aggregated)
    {

        $chunks = $aggregated->chunk(500);

        foreach ($chunks as $chunk) {
            $data = $chunk->map(function ($item) {
                // Processa extra_data - usa uma amostra (pode ser melhorado para agregar valores)
                $extraData = null;
                if ($item->extra_data_sample) {
                    try {
                        $decoded = json_decode($item->extra_data_sample, true);
                        if (is_array($decoded)) {
                            $extraData = $decoded;
                        }
                    } catch (\Exception $e) {
                        // Se falhar, usa null
                    }
                }

                return [
                    'id' => \Illuminate\Support\Str::ulid(),
                    'tenant_id' => $item->tenant_id,
                    'client_id' => $item->client_id,
                    'store_id' => $item->store_id,
                    'product_id' => $item->product_id,
                    'ean' => $item->ean,
                    'codigo_erp' => $item->codigo_erp,
                    'acquisition_cost' => $item->acquisition_cost ?? 0,
                    'sale_price' => $item->sale_price ?? 0,
                    'total_profit_margin' => $item->total_profit_margin ?? 0,
                    'sale_month' => $item->sale_month,
                    'promotion' => $item->promotion ?? 'N',
                    'total_sale_quantity' => $item->total_sale_quantity ?? 0,
                    'total_sale_value' => $item->total_sale_value ?? 0,
                    'margem_contribuicao' => $item->margem_contribuicao ?? 0,
                    'extra_data' => $extraData ? json_encode($extraData) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            // Usa upsert para evitar duplicatas - chave única é: tenant_id + client_id + store_id + codigo_erp + sale_month + promotion
            DB::connection($this->getClientConnection())->table('monthly_sales_summaries')->upsert(
                $data,
                ['tenant_id', 'client_id', 'store_id', 'codigo_erp', 'sale_month', 'promotion'], // Chaves únicas
                [
                    'product_id',
                    'ean',
                    'acquisition_cost',
                    'sale_price',
                    'total_profit_margin',
                    'total_sale_quantity',
                    'total_sale_value',
                    'margem_contribuicao',
                    'extra_data',
                    'updated_at',
                ]
            );
        }
    }
}

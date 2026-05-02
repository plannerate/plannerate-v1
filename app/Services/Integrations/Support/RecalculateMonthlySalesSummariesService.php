<?php

namespace App\Services\Integrations\Support;

use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecalculateMonthlySalesSummariesService
{
    /**
     * @return array{tenant_id: string, tenant_name: string, sales_linked: int, deleted: int, inserted: int, summaries_linked: int}
     */
    public function recalculate(Tenant $tenant, ?string $month = null): array
    {
        return $tenant->execute(function () use ($month, $tenant): array {
            $connection = $this->tenantConnectionName();
            $tenantId = (string) $tenant->id;

            $salesLinked = $this->linkSalesToProducts($connection, $tenantId);
            $deleted = $this->deleteExistingSummaries($connection, $tenantId, $month);
            $aggregated = $this->aggregateSales($connection, $tenantId, $month);
            $inserted = $this->insertSummaries($connection, $aggregated);
            $summariesLinked = $this->linkMonthlySalesSummariesToProducts($connection, $tenantId);

            return [
                'tenant_id' => $tenantId,
                'tenant_name' => (string) $tenant->name,
                'sales_linked' => $salesLinked,
                'deleted' => $deleted,
                'inserted' => $inserted,
                'summaries_linked' => $summariesLinked,
            ];
        });
    }

    private function tenantConnectionName(): string
    {
        $connection = config('multitenancy.tenant_database_connection_name');

        return is_string($connection) && $connection !== '' ? $connection : (string) config('database.default');
    }

    private function deleteExistingSummaries(string $connection, string $tenantId, ?string $month): int
    {
        $database = DB::connection($connection);
        $query = $database
            ->table('monthly_sales_summaries')
            ->where('tenant_id', $tenantId);

        if ($month) {
            $query->whereRaw($this->monthFilterExpression($database->getDriverName(), 'sale_month'), [$month]);
        }

        return $query->delete();
    }

    /**
     * @return Collection<int, object>
     */
    private function aggregateSales(string $connection, string $tenantId, ?string $month): Collection
    {
        $database = DB::connection($connection);
        $driver = $database->getDriverName();
        $saleMonthExpression = $this->monthStartExpression($driver, 'sale_date');

        $query = $database
            ->table('sales')
            ->select([
                'tenant_id',
                'store_id',
                'codigo_erp',
                'ean',
                'product_id',
                'promotion',
                DB::raw("{$saleMonthExpression} as sale_month"),
                DB::raw('SUM(acquisition_cost) as acquisition_cost'),
                DB::raw('SUM(sale_price) as sale_price'),
                DB::raw('SUM(total_profit_margin) as total_profit_margin'),
                DB::raw('SUM(total_sale_quantity) as total_sale_quantity'),
                DB::raw('SUM(total_sale_value) as total_sale_value'),
                DB::raw('SUM(margem_contribuicao) as margem_contribuicao'),
                DB::raw('MAX(extra_data) as extra_data_sample'),
            ])
            ->where('tenant_id', $tenantId)
            ->whereNotNull('sale_date')
            ->whereNotNull('codigo_erp')
            ->whereNull('deleted_at')
            ->groupBy([
                'tenant_id',
                'store_id',
                'codigo_erp',
                'ean',
                'product_id',
                'promotion',
                DB::raw($saleMonthExpression),
            ]);

        if ($month) {
            $query->whereRaw($this->monthFilterExpression($driver, 'sale_date'), [$month]);
        }

        return $query->get();
    }

    private function monthStartExpression(string $driver, string $column): string
    {
        return match ($driver) {
            'pgsql' => "DATE_TRUNC('month', {$column})::date",
            'sqlite' => "date({$column}, 'start of month')",
            default => "DATE_TRUNC('month', {$column})::date",
        };
    }

    private function monthFilterExpression(string $driver, string $column): string
    {
        return match ($driver) {
            'pgsql' => "TO_CHAR({$column}, 'YYYY-MM') = ?",
            'sqlite' => "strftime('%Y-%m', {$column}) = ?",
            default => "TO_CHAR({$column}, 'YYYY-MM') = ?",
        };
    }

    private function linkSalesToProducts(string $connection, string $tenantId): int
    {
        $database = DB::connection($connection);

        return $database->affectingStatement('
            UPDATE sales
            SET
                product_id = p.id,
                ean = p.ean,
                updated_at = CURRENT_TIMESTAMP
            FROM products p
            WHERE sales.tenant_id = ?
              AND p.tenant_id = sales.tenant_id
              AND sales.codigo_erp = p.codigo_erp
              AND p.deleted_at IS NULL
              AND sales.codigo_erp IS NOT NULL
              AND sales.deleted_at IS NULL
              AND (
                sales.product_id IS NULL
                OR sales.ean IS NULL
                OR sales.product_id <> p.id
                OR COALESCE(sales.ean, \'\') <> COALESCE(p.ean, \'\')
              )
        ', [$tenantId]);
    }

    private function insertSummaries(string $connection, Collection $aggregated): int
    {
        $inserted = 0;

        foreach ($aggregated->chunk(500) as $chunk) {
            $data = $chunk->map(function ($item): array {
                $extraData = null;
                if ($item->extra_data_sample) {
                    $decoded = json_decode($item->extra_data_sample, true);
                    if (is_array($decoded)) {
                        $extraData = $decoded;
                    }
                }

                return [
                    'id' => Str::ulid()->toString(),
                    'tenant_id' => $item->tenant_id,
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

            DB::connection($connection)->table('monthly_sales_summaries')->insert($data);
            $inserted += count($data);
        }

        return $inserted;
    }

    private function linkMonthlySalesSummariesToProducts(string $connection, string $tenantId): int
    {
        $database = DB::connection($connection);

        return $database->affectingStatement('
            UPDATE monthly_sales_summaries
            SET
                product_id = p.id,
                ean = p.ean,
                updated_at = CURRENT_TIMESTAMP
            FROM products p
            WHERE monthly_sales_summaries.tenant_id = ?
              AND p.tenant_id = monthly_sales_summaries.tenant_id
              AND monthly_sales_summaries.codigo_erp = p.codigo_erp
              AND p.deleted_at IS NULL
              AND monthly_sales_summaries.codigo_erp IS NOT NULL
              AND (
                monthly_sales_summaries.product_id IS NULL
                OR monthly_sales_summaries.ean IS NULL
                OR monthly_sales_summaries.product_id <> p.id
                OR COALESCE(monthly_sales_summaries.ean, \'\') <> COALESCE(p.ean, \'\')
              )
        ', [$tenantId]);
    }
}

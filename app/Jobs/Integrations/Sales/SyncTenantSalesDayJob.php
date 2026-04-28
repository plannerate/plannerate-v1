<?php

namespace App\Jobs\Integrations\Sales;

use App\Models\IntegrationSyncDay;
use App\Models\Store;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\IntegrationServiceResolver;
use App\Services\Integrations\Support\TenantIntegrationConfigNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Throwable;

class SyncTenantSalesDayJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public string $integrationId,
        public string $referenceDate,
    ) {}

    public function handle(
        IntegrationServiceResolver $integrationServiceResolver,
        TenantIntegrationConfigNormalizer $configNormalizer,
    ): void {
        $integration = TenantIntegration::query()
            ->whereKey($this->integrationId)
            ->where('is_active', true)
            ->first();

        if (! $integration) {
            return;
        }

        $syncDay = IntegrationSyncDay::query()->firstOrCreate(
            [
                'tenant_integration_id' => $integration->id,
                'resource' => 'sales',
                'reference_date' => $this->referenceDate,
            ],
            [
                'status' => 'pending',
            ]
        );

        $syncDay->markRunning();

        try {
            $salesIntegrationService = $integrationServiceResolver->resolveSalesService($integration);
            $processing = $configNormalizer->normalize($integration)['processing'];
            $stores = Store::query()
                ->where('tenant_id', $integration->tenant_id)
                ->whereNull('deleted_at')
                ->get(['id', 'code', 'document']);
            $referenceDate = Carbon::parse($this->referenceDate)->toDateString();
            $executed = false;

            foreach ($stores as $store) {
                $empresa = $this->resolveEmpresaForStore($store->code, $store->document, $processing);

                if ($empresa === null) {
                    continue;
                }

                $salesIntegrationService->fetchSales($integration, [
                    'date' => $referenceDate,
                    'store_id' => (string) $store->id,
                    'empresa' => $empresa,
                    'page_size' => (int) ($processing['sales_page_size'] ?? 20000),
                    'tipo_consulta' => (string) ($processing['sales_tipo_consulta'] ?? 'produto'),
                    'partner_key' => (string) ($processing['partner_key'] ?? ''),
                ]);

                $executed = true;
            }

            if (! $executed) {
                $salesIntegrationService->fetchSales($integration, [
                    'date' => $referenceDate,
                    'empresa' => (string) ($processing['empresa'] ?? ''),
                    'page_size' => (int) ($processing['sales_page_size'] ?? 20000),
                    'tipo_consulta' => (string) ($processing['sales_tipo_consulta'] ?? 'produto'),
                    'partner_key' => (string) ($processing['partner_key'] ?? ''),
                ]);
            }

            $syncDay->markSuccess();
        } catch (Throwable $exception) {
            $syncDay->markFailed($exception->getMessage());

            throw $exception;
        }
    }

    /**
     * A Sysmo espera o identificador da loja no campo "empresa".
     *
     * @param  array<string, mixed>  $processing
     */
    private function resolveEmpresaForStore(?string $storeCode, ?string $storeDocument, array $processing): ?string
    {
        if (is_string($storeCode) && trim($storeCode) !== '') {
            return trim($storeCode);
        }

        if (is_string($storeDocument) && trim($storeDocument) !== '') {
            return trim($storeDocument);
        }

        $fallbackEmpresa = $processing['empresa'] ?? null;

        if (is_string($fallbackEmpresa) && trim($fallbackEmpresa) !== '') {
            return trim($fallbackEmpresa);
        }

        return null;
    }
}

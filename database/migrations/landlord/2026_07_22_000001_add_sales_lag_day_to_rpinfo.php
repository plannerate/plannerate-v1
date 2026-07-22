<?php

use App\Models\IntegrationApi;
use Illuminate\Database\Migrations\Migration;

/**
 * Faz a busca de vendas do RP Info começar em ONTEM, não hoje.
 *
 * O ERP só materializa a tabela de movimento do dia depois do fechamento. Com o
 * import rodando às 06:00, a API responde HTTP 200 com
 * "Não localizada tabela de movimento detalhe de produtos (movprodd0726) para a
 * data:<hoje>". O IntegrationResponseGuard trata isso como falha — e está certo,
 * senão o dia entraria como coberto e vazio, sumindo do radar —, mas o efeito
 * colateral é retentativa com backoff e um ERROR no log por loja, todo dia.
 *
 * Observado em 22/07/2026: às 08:36 a data de hoje dava erro; às 11:45 a mesma
 * data já respondia normalmente (vazia). Transitório, e evitável.
 *
 * Nada se perde: `integrations.recheck_days` (3) faz a janela recente ser
 * re-buscada em toda execução, então o dia de hoje entra amanhã.
 *
 * Guardada e idempotente.
 */
return new class extends Migration
{
    protected $connection = 'landlord';

    private const SLUG = 'rpinfo';

    public function up(): void
    {
        $this->setLagDays(1);
    }

    public function down(): void
    {
        $this->setLagDays(null);
    }

    private function setLagDays(?int $lagDays): void
    {
        $api = IntegrationApi::query()->where('slug', self::SLUG)->first();

        if ($api === null || data_get($api->requests, 'paths.sales') === null) {
            return;
        }

        $requests = $api->requests;

        if ($lagDays === null) {
            data_forget($requests, 'paths.sales.lag_days');
        } else {
            data_set($requests, 'paths.sales.lag_days', $lagDays);
        }

        // Update pela query base: o HasSlug regenera o slug a partir do `name`
        // em todo save e renomearia o blueprint ("RP Info" → "rp-info").
        IntegrationApi::query()
            ->whereKey($api->getKey())
            ->toBase()
            ->update(['requests' => json_encode($requests), 'updated_at' => now()]);
    }
};

<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Reoptimization;

use Callcocam\LaravelRaptorPlannerate\Enums\ProposalStatus;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramReoptimizationProposal;
use Illuminate\Support\Facades\Log;

/**
 * Manutenção da fila de propostas: expira as abandonadas e descarta os snapshots que já não servem.
 *
 * São dois problemas diferentes e vale não confundi-los:
 *
 * 1. EXPIRAR é correção de comportamento, não faxina. O agendador não analisa gôndola que já tem
 *    proposta pendente (duas partiriam do mesmo baseline, e aprovar as duas aplicaria só a última).
 *    Logo, uma proposta que ninguém decide TRAVA a reotimização daquela gôndola para sempre — o
 *    usuário desiste de uma proposta e, sem perceber, desliga a feature na gôndola.
 *
 * 2. LIMPAR OS SNAPSHOTS é armazenamento. Cada proposta carrega ~150 KB de layout (o "antes", o
 *    "depois" e os rejeitados). Depois de decidida, esse layout nunca mais é lido: quem o usa é o
 *    ProposalApplier, e só enquanto a proposta pode ser aplicada. A LINHA fica — `diff_summary`,
 *    status e motivo da rejeição são o histórico que ensina a ajustar o template. Só os blobs vão.
 */
final class ProposalPruner
{
    /** Dias sem decisão até uma proposta pendente ser considerada abandonada. */
    public const EXPIRE_AFTER_DAYS = 30;

    /**
     * Dias após a decisão até os snapshots serem descartados.
     *
     * Não é zero (nada impede descartar na hora da decisão) para deixar uma janela de investigação:
     * "aprovei ontem e a gôndola ficou estranha" ainda tem o snapshot exato que foi aplicado.
     */
    public const DISCARD_SNAPSHOTS_AFTER_DAYS = 7;

    /**
     * @return array{expired: int, snapshots_discarded: int}
     */
    public function prune(): array
    {
        return [
            'expired' => $this->expireAbandoned(),
            'snapshots_discarded' => $this->discardDecidedSnapshots(),
        ];
    }

    /**
     * Marca como expiradas as propostas pendentes velhas demais — e, com isso, destrava o
     * agendador para voltar a analisar aquelas gôndolas.
     */
    private function expireAbandoned(): int
    {
        $count = PlanogramReoptimizationProposal::query()
            ->pending()
            ->where('created_at', '<', now()->subDays(self::EXPIRE_AFTER_DAYS))
            ->update([
                'status' => ProposalStatus::Expired,
                // Sem reviewed_by: ninguém decidiu. Marcar um revisor aqui seria mentira no
                // histórico — "expirou" é justamente o registro de que a decisão não foi tomada.
                'reviewed_at' => now(),
            ]);

        if ($count > 0) {
            Log::info('Propostas de reotimização expiradas por falta de decisão', ['count' => $count]);
        }

        return $count;
    }

    /**
     * Zera os snapshots de layout das propostas já decididas há tempo suficiente.
     *
     * `whereNotNull('proposed_layout')` evita reescrever, todo dia, linhas que já foram limpas.
     */
    private function discardDecidedSnapshots(): int
    {
        $count = PlanogramReoptimizationProposal::query()
            ->where('status', '!=', ProposalStatus::Pending)
            ->where('updated_at', '<', now()->subDays(self::DISCARD_SNAPSHOTS_AFTER_DAYS))
            ->whereNotNull('proposed_layout')
            ->update([
                'baseline_layout' => null,
                'proposed_layout' => null,
                'proposed_rejected' => null,
            ]);

        if ($count > 0) {
            Log::info('Snapshots de layout descartados de propostas decididas', ['count' => $count]);
        }

        return $count;
    }
}

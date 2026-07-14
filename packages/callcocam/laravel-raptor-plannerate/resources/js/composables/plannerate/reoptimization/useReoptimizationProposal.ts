import { onMounted, ref } from 'vue';

/**
 * Busca a proposta de reotimização pendente da gôndola, para o banner do editor.
 *
 * Uma consulta só, no mount — nada de polling. As propostas nascem de um agendador que roda
 * de madrugada, uma vez por semana ou por mês: ficar perguntando de minuto em minuto gastaria
 * requisições para descobrir, quase sempre, que nada mudou. Quando a proposta é gerada pelo
 * "analisar agora", o usuário já é avisado pela notificação.
 */
export interface PendingProposal {
    id: string;
    changes_count: number;
    summary: Record<string, number>;
    created_at: string | null;
    url: string;
}

export function useReoptimizationProposal(gondolaId: string | null | undefined) {
    const proposal = ref<PendingProposal | null>(null);
    const loading = ref(false);

    async function fetchPending(): Promise<void> {
        if (!gondolaId) {
            return;
        }

        loading.value = true;

        try {
            const response = await fetch(`/api/gondolas/${gondolaId}/reoptimization/pending`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                return;
            }

            const data = (await response.json()) as { proposal: PendingProposal | null };
            proposal.value = data.proposal;
        } catch {
            // O banner é informativo: se a consulta falhar, o editor segue normalmente.
            proposal.value = null;
        } finally {
            loading.value = false;
        }
    }

    onMounted(fetchPending);

    return { proposal, loading, refresh: fetchPending };
}

<?php

/**
 * Reotimização contínua: cadência por gôndola, análise agendada e revisão da proposta.
 */
return [
    'notification' => [
        'ready_title' => 'Proposta de reotimização pronta',
        'ready_message' => ':count mudança(s) sugerida(s) para a gôndola :gondola. Clique para revisar.',
        'no_changes_title' => 'Nenhuma mudança sugerida',
        'no_changes_message' => 'A análise não encontrou melhorias — a gôndola continua adequada aos dados de venda atuais.',
    ],

    'messages' => [
        'applied' => 'Proposta aprovada e aplicada à gôndola.',
        'rejected' => 'Proposta rejeitada.',
        'queued' => 'Análise iniciada. Você será avisado quando a proposta estiver pronta.',
        'cadence_saved' => 'Cadência de reotimização salva.',
    ],

    'errors' => [
        'stale' => 'A gôndola mudou depois que esta proposta foi calculada. Ela não pode mais ser aplicada — gere uma análise nova.',
        'already_decided' => 'Esta proposta já foi decidida.',
        'locked' => 'Outra aplicação está em andamento nesta gôndola. Tente novamente em instantes.',
        'requires_template' => 'A reotimização exige uma gôndola em modo template. Gere o planograma uma vez para que o template seja definido.',
        'blocked' => 'Não é possível analisar agora: já existe uma proposta aguardando revisão ou uma geração em andamento.',
        'no_previous_generation' => 'Esta gôndola nunca foi gerada. Gere o planograma uma vez antes de habilitar a reotimização.',
        'reason_required' => 'Informe o motivo da rejeição.',
    ],

    'cadence' => [
        'title' => 'Reotimização automática',
        'description' => 'Reprocessa a gôndola periodicamente com os dados de venda atualizados e sugere mudanças — sem alterar nada até você aprovar.',
        'enabled' => 'Reotimizar automaticamente',
        'frequency' => 'Frequência',
        'next_run' => 'Próxima análise em :date',
        'never_run' => 'Nenhuma análise realizada ainda.',
        'last_run' => 'Última análise em :date',
        'run_now' => 'Analisar agora',
        'requires_template_hint' => 'Disponível apenas para gôndolas em modo template. Gere o planograma uma vez para habilitar.',
        'help' => 'A análise roda em simulação: nada é escrito na gôndola. Se houver mudanças a sugerir, você recebe uma proposta com o comparativo antes/depois para aprovar ou rejeitar.',
    ],

    'frequency' => [
        'weekly' => 'Semanal',
        'biweekly' => 'Quinzenal',
        'monthly' => 'Mensal',
    ],

    'banner' => [
        'title' => 'Proposta de reotimização aguardando revisão',
        'message' => ':count mudança(s) sugerida(s) com base nas vendas mais recentes.',
        'action' => 'Revisar proposta',
    ],

    'proposal' => [
        'title' => 'Proposta de reotimização',
        'subtitle' => 'Comparativo entre o planograma atual e o que a análise sugere.',
        'back_to_editor' => 'Voltar ao editor',
        'sales_period' => 'Período de vendas analisado',
        'occupancy' => 'Ocupação média',
        'occupancy_before' => 'Atual',
        'occupancy_after' => 'Proposta',
        'created_at' => 'Analisada em',
        'no_changes' => 'A análise não encontrou mudanças a sugerir. A gôndola continua adequada aos dados de venda atuais.',
        'reviewed_by' => 'Decidida por :name em :date',
        'rejection_reason' => 'Motivo da rejeição',
        'error' => 'A análise falhou',

        'summary' => [
            'total' => 'Produtos afetados',
            'unchanged' => 'Sem alteração',
        ],

        'table' => [
            'product' => 'Produto',
            'changes' => 'O que muda',
            'facings' => 'Frentes',
            'position' => 'Posição',
            'module_shelf' => 'Módulo :module · Prateleira :shelf',
            'absent' => '—',
            'empty' => 'Nenhuma mudança neste filtro.',
        ],

        'changes' => [
            'added' => 'Entra na gôndola',
            'removed' => 'Sai da gôndola',
            'facings_increased' => 'Ganha frentes',
            'facings_decreased' => 'Perde frentes',
            'moved' => 'Muda de lugar',
            'stacking_changed' => 'Muda o empilhamento',
            'rejected_added' => 'Passa a ser rejeitado',
            'rejected_resolved' => 'Deixa de ser rejeitado',
        ],

        'actions' => [
            'approve' => 'Aprovar e aplicar',
            'reject' => 'Rejeitar',
            'approve_confirm_title' => 'Aplicar a proposta inteira?',
            'approve_confirm_message' => 'Todas as mudanças acima serão aplicadas de uma vez — não é possível escolher apenas algumas, porque as posições foram calculadas considerando o conjunto. Qualquer ajuste manual feito na gôndola será substituído.',
            'approve_confirm_cta' => 'Aplicar à gôndola',
            'reject_title' => 'Rejeitar proposta',
            'reject_message' => 'Por que esta proposta não serve? O motivo fica registrado e ajuda a ajustar a configuração do template.',
            'reject_placeholder' => 'Ex.: a marca própria precisa ficar na altura dos olhos.',
            'reject_cta' => 'Rejeitar proposta',
            'cancel' => 'Cancelar',
        ],
    ],
];

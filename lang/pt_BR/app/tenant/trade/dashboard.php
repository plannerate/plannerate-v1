<?php

return [
    'navigation' => 'Dashboard',
    'title' => 'Dashboard de Trade',
    'description' => 'Visão do gestor: ocupação, ações, atividades, comprovações e performance.',

    'tabs' => [
        'overview' => 'Visão geral',
        'acoes' => 'Ações',
        'atividades' => 'Atividades',
        'foto_check' => 'Foto Check',
        'performance' => 'Performance',
    ],

    'overview' => [
        'occupancy' => 'Ocupação',
        'active_actions' => 'Ações ativas',
        'negotiations' => 'Negociações abertas',
        'expiring_7d' => 'Vencendo em 7 dias',
        'open_activities' => 'Atividades abertas',
        'late_activities' => 'Atividades atrasadas',
        'proofs_awaiting' => 'Comprovações a revisar',
    ],

    'acoes' => [
        'active' => 'Ativas',
        'active_value' => 'Valor ativo',
        'scheduled' => 'Agendadas',
        'expiring_7d' => 'Vencendo em 7d',
        'suppliers' => 'Fornecedores ativos',
        'by_supplier' => 'Por fornecedor',
        'by_store' => 'Por loja',
        'by_type' => 'Por tipo de espaço',
        'occupant' => 'Ocupante',
        'space' => 'Espaço',
        'store' => 'Loja',
        'ends' => 'Termina',
        'days_left' => 'Dias restantes',
    ],

    'atividades' => [
        'open' => 'Abertas',
        'late' => 'Atrasadas',
        'done_30d' => 'Concluídas (30d)',
        'on_time' => 'No prazo',
        'avg_time' => 'Tempo médio',
        'rejected' => 'Reprovadas',
        'by_situation' => 'Por situação',
        'by_type' => 'Por tipo',
        'by_store' => 'Por loja',
    ],

    'foto_check' => [
        'awaiting_review' => 'Aguardando revisão',
        'approved_30d' => 'Aprovadas (30d)',
        'rejected_30d' => 'Reprovadas (30d)',
        'approval_rate' => 'Taxa de aprovação',
        'review_time' => 'Tempo de revisão',
        'awaiting_submit' => 'Aguardando envio',
        'review_queue' => 'Fila de revisão',
        'recent_rejected' => 'Reprovadas recentes',
    ],

    'performance' => [
        'revenue' => 'Receita',
        'expenses' => 'Despesas',
        'clients' => 'Clientes ativos',
        'occupancy_trend' => 'Ocupação (mês)',
        'revenue_by_month' => 'Receita por mês',
        'by_type' => 'Por tipo de espaço',
        'client' => 'Cliente',
        'spaces' => 'Espaços',
        'avg_duration' => 'Duração média',
        'vs_previous' => 'Mês anterior',
    ],
];
